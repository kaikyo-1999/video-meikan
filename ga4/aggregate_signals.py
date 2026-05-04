#!/usr/bin/env python3
"""
GA4 シグナル集計バッチ — TOP「ホット作品 / PV急上昇女優」用。
直近7日 / 前週(8〜14日前) の fanza_click 数 / sessions 数を取得し、
work_signals / actress_signals テーブルへ UPSERT する。

実行頻度: 1日2回 (cron 推奨: 03:00 / 15:00)
キャッシュクリア: 完了後に Cache::clear() (PHP) を呼び出すか、本スクリプト内で cache/ を削除。

使い方:
    python ga4/aggregate_signals.py            # 集計→DB UPSERT→キャッシュクリア
    python ga4/aggregate_signals.py --dry-run  # DB に書き込まず標準出力のみ
"""

import argparse
import os
import re
import sys
from datetime import datetime, timedelta
from pathlib import Path
from typing import Dict

from dotenv import load_dotenv

ROOT = Path(__file__).resolve().parent.parent
load_dotenv(Path(__file__).parent / ".env")
load_dotenv(ROOT / "meikan" / ".env")  # DB 接続用

from google.oauth2 import service_account
from googleapiclient.discovery import build
import pymysql

KEY_FILE = ROOT / "marke-analytics-fa4cf49cfeef.json"
PROPERTY_ID = "529336238"

# パスから女優 slug を抽出 (例: "/mihinanami" "/mihinanami/" )
ACTRESS_PATH_RE = re.compile(r"^/([a-z0-9][a-z0-9-]*)/?$")


# ---------- GA4 ----------

def ga_client():
    creds = service_account.Credentials.from_service_account_file(
        str(KEY_FILE), scopes=["https://www.googleapis.com/auth/analytics.readonly"]
    )
    return build("analyticsdata", "v1beta", credentials=creds)


def fetch_fanza_clicks(client, start: str, end: str) -> Dict[str, int]:
    """date範囲内の fanza_click イベント数を CID 別に集計"""
    body = {
        "dateRanges": [{"startDate": start, "endDate": end}],
        "dimensions": [{"name": "customEvent:fanza_cid"}],
        "metrics": [{"name": "eventCount"}],
        "dimensionFilter": {
            "filter": {
                "fieldName": "eventName",
                "stringFilter": {"value": "fanza_click"},
            }
        },
        "limit": 100000,
    }
    resp = client.properties().runReport(
        property=f"properties/{PROPERTY_ID}", body=body
    ).execute()

    counts: Dict[str, int] = {}
    for row in resp.get("rows", []):
        cid = row["dimensionValues"][0]["value"]
        if not cid or cid == "(not set)":
            continue
        counts[cid] = int(row["metricValues"][0]["value"])
    return counts


def fetch_actress_sessions(client, start: str, end: str) -> Dict[str, int]:
    """date範囲内の女優ページ sessions 数を slug 別に集計"""
    body = {
        "dateRanges": [{"startDate": start, "endDate": end}],
        "dimensions": [{"name": "landingPage"}],
        "metrics": [{"name": "sessions"}],
        "limit": 100000,
    }
    resp = client.properties().runReport(
        property=f"properties/{PROPERTY_ID}", body=body
    ).execute()

    counts: Dict[str, int] = {}
    for row in resp.get("rows", []):
        path = row["dimensionValues"][0]["value"]
        m = ACTRESS_PATH_RE.match(path)
        if not m:
            continue
        slug = m.group(1)
        counts[slug] = counts.get(slug, 0) + int(row["metricValues"][0]["value"])
    return counts


# ---------- DB ----------

def db_conn():
    return pymysql.connect(
        host=os.environ["DB_HOST"],
        user=os.environ["DB_USER"],
        password=os.environ["DB_PASS"],
        db=os.environ["DB_NAME"],
        charset="utf8mb4",
        autocommit=False,
    )


def cid_to_work_id(conn, cids):
    """fanza CID (source_id) → works.id への変換マップ"""
    if not cids:
        return {}
    cur = conn.cursor()
    placeholders = ",".join(["%s"] * len(cids))
    cur.execute(f"SELECT id, source_id FROM works WHERE source = 'fanza' AND source_id IN ({placeholders})", list(cids))
    return {row[1]: row[0] for row in cur.fetchall()}


def slug_to_actress_id(conn, slugs):
    if not slugs:
        return {}
    cur = conn.cursor()
    placeholders = ",".join(["%s"] * len(slugs))
    cur.execute(f"SELECT id, slug FROM actresses WHERE slug IN ({placeholders})", list(slugs))
    return {row[1]: row[0] for row in cur.fetchall()}


def upsert_work_signals(conn, click_7d, click_30d, click_prev_7d, dry=False):
    cids = set(click_7d) | set(click_30d) | set(click_prev_7d)
    cid_map = cid_to_work_id(conn, cids)

    rows = []
    for cid, wid in cid_map.items():
        c7 = click_7d.get(cid, 0)
        c30 = click_30d.get(cid, 0)
        cp = click_prev_7d.get(cid, 0)
        velocity = (c7 - cp) / max(cp, 1)
        rows.append((wid, c7, c30, cp, round(velocity, 4)))

    if dry:
        print(f"[dry-run] work_signals UPSERT {len(rows)} rows (sample): {rows[:3]}")
        return

    cur = conn.cursor()
    sql = """
        INSERT INTO work_signals (work_id, click_7d, click_30d, click_prev_7d, velocity_score)
        VALUES (%s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            click_7d = VALUES(click_7d),
            click_30d = VALUES(click_30d),
            click_prev_7d = VALUES(click_prev_7d),
            velocity_score = VALUES(velocity_score),
            updated_at = CURRENT_TIMESTAMP
    """
    cur.executemany(sql, rows)
    conn.commit()
    print(f"work_signals UPSERT: {len(rows)} rows")


def upsert_actress_signals(conn, sess_7d, sess_prev_7d, dry=False):
    slugs = set(sess_7d) | set(sess_prev_7d)
    slug_map = slug_to_actress_id(conn, slugs)

    rows = []
    for slug, aid in slug_map.items():
        s7 = sess_7d.get(slug, 0)
        sp = sess_prev_7d.get(slug, 0)
        velocity = (s7 - sp) / max(sp, 1)
        rows.append((aid, s7, sp, round(velocity, 4)))

    if dry:
        print(f"[dry-run] actress_signals UPSERT {len(rows)} rows (sample): {rows[:3]}")
        return

    cur = conn.cursor()
    sql = """
        INSERT INTO actress_signals (actress_id, sessions_7d, sessions_prev_7d, pv_velocity_score)
        VALUES (%s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE
            sessions_7d = VALUES(sessions_7d),
            sessions_prev_7d = VALUES(sessions_prev_7d),
            pv_velocity_score = VALUES(pv_velocity_score),
            updated_at = CURRENT_TIMESTAMP
    """
    cur.executemany(sql, rows)
    conn.commit()
    print(f"actress_signals UPSERT: {len(rows)} rows")


def clear_top_cache():
    """TOP で使うキャッシュキー (hot_works_*, sale_works_*, hot_actresses_pv_*) を削除"""
    cache_dir = ROOT / "meikan" / "cache"
    cleared = 0
    for d in [cache_dir, cache_dir / "opc"]:
        if not d.exists():
            continue
        for f in d.iterdir():
            if f.is_file() and (f.suffix in {".php", ".cache"}):
                # キャッシュキーが md5 化されているのでパターンマッチ不可。
                # → 安全のためすべて削除 (再生成コストは小さい)
                f.unlink()
                cleared += 1
    print(f"cache cleared: {cleared} files")


# ---------- main ----------

def main():
    p = argparse.ArgumentParser()
    p.add_argument("--dry-run", action="store_true")
    args = p.parse_args()

    today = datetime.utcnow().date()
    end_7d = today - timedelta(days=1)
    start_7d = end_7d - timedelta(days=6)         # 直近7日
    end_30d = end_7d
    start_30d = end_7d - timedelta(days=29)       # 直近30日
    end_prev = start_7d - timedelta(days=1)
    start_prev = end_prev - timedelta(days=6)     # 前週(8〜14日前)

    fmt = lambda d: d.isoformat()
    print(f"7d: {fmt(start_7d)}〜{fmt(end_7d)}")
    print(f"prev_7d: {fmt(start_prev)}〜{fmt(end_prev)}")
    print(f"30d: {fmt(start_30d)}〜{fmt(end_30d)}")

    ga = ga_client()
    print("[GA4] fetching fanza_click...")
    click_7d = fetch_fanza_clicks(ga, fmt(start_7d), fmt(end_7d))
    click_30d = fetch_fanza_clicks(ga, fmt(start_30d), fmt(end_30d))
    click_prev = fetch_fanza_clicks(ga, fmt(start_prev), fmt(end_prev))
    print(f"  7d CIDs: {len(click_7d)}, 30d: {len(click_30d)}, prev: {len(click_prev)}")

    print("[GA4] fetching actress sessions...")
    sess_7d = fetch_actress_sessions(ga, fmt(start_7d), fmt(end_7d))
    sess_prev = fetch_actress_sessions(ga, fmt(start_prev), fmt(end_prev))
    print(f"  7d slugs: {len(sess_7d)}, prev: {len(sess_prev)}")

    conn = db_conn()
    try:
        upsert_work_signals(conn, click_7d, click_30d, click_prev, dry=args.dry_run)
        upsert_actress_signals(conn, sess_7d, sess_prev, dry=args.dry_run)
    finally:
        conn.close()

    if not args.dry_run:
        clear_top_cache()

    print("done")


if __name__ == "__main__":
    main()
