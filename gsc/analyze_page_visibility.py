#!/usr/bin/env python3
"""
GSC: 直近で表示がなくなったページ vs. 引き続き表示されているページ を比較分析。

期間設計:
- BEFORE: 2026-04-28 ~ 2026-05-10 (13日, cliff 前)
- AFTER : 2026-05-12 ~ 2026-05-15 (4日, cliff 後・GSC最新)

ページ単位で impressions / clicks / position を取得し、以下にカテゴライズして
URLパターン別の傾向を出す。
- vanished : BEFORE で imp>=20 だったが AFTER で imp==0
- shrunk   : BEFORE で imp>=20、AFTER で imp/日 が 70%以上減
- retained : BEFORE / AFTER ともに表示あり、減少 70%未満
- emerged  : BEFORE で imp==0、AFTER で imp>0
"""

import sys
import os
from collections import defaultdict
from urllib.parse import urlparse

sys.path.insert(0, os.path.dirname(__file__))
from fetch import get_service, SITE_URL  # noqa


BEFORE_START = "2026-04-28"
BEFORE_END = "2026-05-10"  # 13 days
AFTER_START = "2026-05-12"
AFTER_END = "2026-05-15"   # 4 days

BEFORE_DAYS = 13
AFTER_DAYS = 4


def fetch_pages(service, start, end, row_limit=25000):
    """page 単位で impressions, clicks, position を取得（pagination 対応）"""
    rows = []
    start_row = 0
    while True:
        body = {
            "startDate": start,
            "endDate": end,
            "dimensions": ["page"],
            "rowLimit": row_limit,
            "startRow": start_row,
        }
        resp = service.searchanalytics().query(siteUrl=SITE_URL, body=body).execute()
        chunk = resp.get("rows", [])
        rows.extend(chunk)
        if len(chunk) < row_limit:
            break
        start_row += row_limit
    return rows


def page_type(url):
    """URL からページタイプを推定"""
    path = urlparse(url).path
    if path == "/" or path == "":
        return "top"
    if path.startswith("/article/"):
        return "article"
    if path.startswith("/meikan"):
        return "meikan"
    if path.startswith("/favorites"):
        return "favorites"
    if path.startswith("/cross-link"):
        return "cross-link"
    if path.startswith("/fc2"):
        return "fc2"
    if path.startswith("/author"):
        return "author"
    # /{slug}/ or /{slug}/{genre}/
    parts = [p for p in path.split("/") if p]
    if len(parts) == 1:
        return "actress"
    if len(parts) == 2:
        return "genre"
    return "other"


def main():
    service = get_service()

    print(f"Fetching BEFORE: {BEFORE_START} ~ {BEFORE_END} ({BEFORE_DAYS}d)...", file=sys.stderr)
    before_rows = fetch_pages(service, BEFORE_START, BEFORE_END)
    print(f"  {len(before_rows)} pages", file=sys.stderr)

    print(f"Fetching AFTER : {AFTER_START} ~ {AFTER_END} ({AFTER_DAYS}d)...", file=sys.stderr)
    after_rows = fetch_pages(service, AFTER_START, AFTER_END)
    print(f"  {len(after_rows)} pages", file=sys.stderr)

    def to_map(rows):
        m = {}
        for r in rows:
            url = r["keys"][0]
            m[url] = {
                "clicks": int(r["clicks"]),
                "imp": int(r["impressions"]),
                "ctr": r["ctr"],
                "pos": r["position"],
            }
        return m

    bmap = to_map(before_rows)
    amap = to_map(after_rows)

    all_urls = set(bmap) | set(amap)
    cats = defaultdict(list)

    for url in all_urls:
        b = bmap.get(url, {"clicks": 0, "imp": 0, "pos": None})
        a = amap.get(url, {"clicks": 0, "imp": 0, "pos": None})

        b_imp_per_day = b["imp"] / BEFORE_DAYS
        a_imp_per_day = a["imp"] / AFTER_DAYS

        entry = {
            "url": url,
            "type": page_type(url),
            "b_clicks": b["clicks"], "b_imp": b["imp"], "b_pos": b.get("pos"),
            "a_clicks": a["clicks"], "a_imp": a["imp"], "a_pos": a.get("pos"),
            "b_imp_per_day": b_imp_per_day,
            "a_imp_per_day": a_imp_per_day,
        }

        # 表示ベース判定。BEFORE が薄いページは "minor" にまとめる
        if b["imp"] < 20 and a["imp"] < 5:
            cats["minor"].append(entry)
            continue
        if b["imp"] >= 20 and a["imp"] == 0:
            cats["vanished"].append(entry)
        elif b["imp"] >= 20 and a_imp_per_day < b_imp_per_day * 0.3:
            cats["shrunk"].append(entry)
        elif b["imp"] == 0 and a["imp"] > 0:
            cats["emerged"].append(entry)
        else:
            cats["retained"].append(entry)

    # サマリ
    print("\n" + "=" * 80)
    print("カテゴリ別件数（page 単位）")
    print("=" * 80)
    for k in ["vanished", "shrunk", "retained", "emerged", "minor"]:
        print(f"  {k:10s}: {len(cats[k])} pages")

    # ページタイプ × カテゴリ クロス
    print("\n" + "=" * 80)
    print("ページタイプ × カテゴリ クロス（件数）")
    print("=" * 80)
    types = ["top", "article", "actress", "genre", "other"]
    print(f"{'type':12s}" + "".join(f"{c:>11s}" for c in ["vanished", "shrunk", "retained", "emerged"]))
    for t in types:
        row = [sum(1 for e in cats[c] if e["type"] == t) for c in ["vanished", "shrunk", "retained", "emerged"]]
        print(f"{t:12s}" + "".join(f"{n:>11d}" for n in row))

    # ページタイプ × imp/clicks
    print("\n" + "=" * 80)
    print("ページタイプ × インプレッション合計（BEFORE/日 → AFTER/日）")
    print("=" * 80)
    print(f"{'type':12s}{'BEFORE imp/day':>20s}{'AFTER imp/day':>20s}{'change':>10s}{'BEFORE clk/day':>20s}{'AFTER clk/day':>20s}")
    for t in types:
        b_imp = sum(bmap[u]["imp"] for u in bmap if page_type(u) == t) / BEFORE_DAYS
        a_imp = sum(amap[u]["imp"] for u in amap if page_type(u) == t) / AFTER_DAYS
        b_clk = sum(bmap[u]["clicks"] for u in bmap if page_type(u) == t) / BEFORE_DAYS
        a_clk = sum(amap[u]["clicks"] for u in amap if page_type(u) == t) / AFTER_DAYS
        chg = (a_imp / b_imp - 1) * 100 if b_imp else 0
        print(f"{t:12s}{b_imp:>20.1f}{a_imp:>20.1f}{chg:>9.0f}%{b_clk:>20.1f}{a_clk:>20.1f}")

    # 詳細リスト
    def dump(cat, n=30, sort_key=None):
        items = cats[cat]
        if sort_key:
            items = sorted(items, key=sort_key, reverse=True)
        print(f"\n--- {cat} (top {min(n, len(items))} of {len(items)}) ---")
        print(f"{'type':9s}{'b_imp':>7s}{'a_imp':>7s}{'b_clk':>6s}{'a_clk':>6s}{'b_pos':>7s}{'a_pos':>7s}  url")
        for e in items[:n]:
            bpos = f"{e['b_pos']:.1f}" if e['b_pos'] else "-"
            apos = f"{e['a_pos']:.1f}" if e['a_pos'] else "-"
            print(f"{e['type']:9s}{e['b_imp']:>7d}{e['a_imp']:>7d}{e['b_clicks']:>6d}{e['a_clicks']:>6d}{bpos:>7s}{apos:>7s}  {e['url']}")

    dump("vanished", n=40, sort_key=lambda e: e["b_imp"])
    dump("shrunk", n=40, sort_key=lambda e: e["b_imp"] - e["a_imp"] * (BEFORE_DAYS / AFTER_DAYS))
    dump("retained", n=40, sort_key=lambda e: e["a_imp"])
    dump("emerged", n=20, sort_key=lambda e: e["a_imp"])


if __name__ == "__main__":
    main()
