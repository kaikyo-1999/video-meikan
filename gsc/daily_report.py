#!/usr/bin/env python3
"""GSC日次レポート: 記事/女優/ジャンル別の積み上げグラフを生成してSlackに投稿する"""

import os
import re
import sys
import tempfile
from collections import defaultdict
from datetime import datetime, timedelta

from dotenv import load_dotenv

load_dotenv(os.path.join(os.path.dirname(__file__), ".env"))

import matplotlib
import matplotlib.dates as mdates
import matplotlib.pyplot as plt

matplotlib.rcParams["font.family"] = "Hiragino Sans"
from slack_sdk import WebClient

from fetch import SITE_URL, get_service

SLACK_BOT_TOKEN = os.environ.get("SLACK_BOT_TOKEN", "")
SLACK_CHANNEL = os.environ.get("SLACK_CHANNEL", "")

DAYS = 90

CATEGORIES = ["記事", "女優", "ジャンル", "その他"]
COLORS = {
    "記事": "#e74c3c",
    "女優": "#3498db",
    "ジャンル": "#f39c12",
    "その他": "#95a5a6",
}

ACTRESS_RE = re.compile(r"^https://av-hakase\.com/[a-z0-9][a-z0-9-]*/$")
GENRE_RE = re.compile(r"^https://av-hakase\.com/[a-z0-9][a-z0-9-]*/[a-z0-9][a-z0-9-]*/$")


def classify(url: str) -> str:
    if "/article" in url:
        return "記事"
    if GENRE_RE.match(url):
        return "ジャンル"
    if ACTRESS_RE.match(url):
        return "女優"
    return "その他"


def fetch_daily_by_category():
    """日別×カテゴリ別の clicks/imp/重み付き順位 を返す"""
    service = get_service()
    end_date = datetime.now() - timedelta(days=3)
    start_date = end_date - timedelta(days=DAYS - 1)

    rows = []
    start_row = 0
    LIMIT = 25000
    while True:
        body = {
            "startDate": start_date.strftime("%Y-%m-%d"),
            "endDate": end_date.strftime("%Y-%m-%d"),
            "dimensions": ["date", "page"],
            "rowLimit": LIMIT,
            "startRow": start_row,
        }
        resp = service.searchanalytics().query(siteUrl=SITE_URL, body=body).execute()
        chunk = resp.get("rows", [])
        rows.extend(chunk)
        if len(chunk) < LIMIT:
            break
        start_row += LIMIT

    # date -> category -> {clicks, imp, pos_imp_sum}
    agg = defaultdict(lambda: defaultdict(lambda: {"clicks": 0, "imp": 0, "pos_imp_sum": 0.0}))
    for r in rows:
        date_str, url = r["keys"]
        cat = classify(url)
        d = datetime.strptime(date_str, "%Y-%m-%d")
        clicks = int(r["clicks"])
        imp = int(r["impressions"])
        pos = float(r["position"])
        bucket = agg[d][cat]
        bucket["clicks"] += clicks
        bucket["imp"] += imp
        bucket["pos_imp_sum"] += pos * imp
    return agg


def create_chart(agg):
    dates = sorted(agg.keys())
    if not dates:
        return None, dates

    # カテゴリ別系列
    series_imp = {c: [agg[d][c]["imp"] for d in dates] for c in CATEGORIES}
    series_clicks = {c: [agg[d][c]["clicks"] for d in dates] for c in CATEGORIES}
    series_pos = {}
    for c in CATEGORIES:
        positions = []
        for d in dates:
            b = agg[d][c]
            positions.append(b["pos_imp_sum"] / b["imp"] if b["imp"] > 0 else None)
        series_pos[c] = positions

    period = f"{dates[0].strftime('%Y/%m/%d')} 〜 {dates[-1].strftime('%Y/%m/%d')}"
    fig, axes = plt.subplots(3, 1, figsize=(14, 10), sharex=True)
    fig.suptitle(f"av-hakase.com GSC日次推移\n{period}", fontsize=14, fontweight="bold")

    # 1) 表示回数（積み上げ）
    ax = axes[0]
    bottom = [0] * len(dates)
    for c in CATEGORIES:
        ax.bar(dates, series_imp[c], bottom=bottom, color=COLORS[c], alpha=0.8, label=c, width=0.8)
        bottom = [b + v for b, v in zip(bottom, series_imp[c])]
    ax.set_ylabel("表示回数")
    ax.legend(loc="upper left")
    ax.grid(True, alpha=0.3)

    # 2) クリック数（積み上げ）
    ax = axes[1]
    bottom = [0] * len(dates)
    for c in CATEGORIES:
        ax.bar(dates, series_clicks[c], bottom=bottom, color=COLORS[c], alpha=0.8, label=c, width=0.8)
        bottom = [b + v for b, v in zip(bottom, series_clicks[c])]
    ax.set_ylabel("クリック数")
    ax.legend(loc="upper left")
    ax.grid(True, alpha=0.3)

    # 3) 平均順位（カテゴリ別折れ線、軸反転）
    ax = axes[2]
    for c in CATEGORIES:
        ys = series_pos[c]
        if all(v is None for v in ys):
            continue
        ax.plot(dates, ys, "o-", color=COLORS[c], label=c, markersize=3, linewidth=1.2)
    ax.invert_yaxis()
    ax.set_ylabel("平均順位")
    ax.legend(loc="upper left")
    ax.grid(True, alpha=0.3)

    axes[2].xaxis.set_major_locator(mdates.WeekdayLocator(byweekday=mdates.MO))
    axes[2].xaxis.set_major_formatter(mdates.DateFormatter("%m/%d"))
    plt.xticks(rotation=45)
    plt.tight_layout()

    path = os.path.join(tempfile.gettempdir(), "gsc_daily_report.png")
    fig.savefig(path, dpi=150, bbox_inches="tight")
    plt.close(fig)
    return path, dates


def post_to_slack(image_path, agg, dates):
    if not SLACK_BOT_TOKEN or not SLACK_CHANNEL:
        print("SLACK_BOT_TOKEN / SLACK_CHANNEL が未設定。Slack投稿をスキップ。")
        return

    period = f"{dates[0].strftime('%Y/%m/%d')} 〜 {dates[-1].strftime('%Y/%m/%d')}"

    totals = {c: {"clicks": 0, "imp": 0, "pos_imp_sum": 0.0} for c in CATEGORIES}
    for d in dates:
        for c in CATEGORIES:
            b = agg[d][c]
            totals[c]["clicks"] += b["clicks"]
            totals[c]["imp"] += b["imp"]
            totals[c]["pos_imp_sum"] += b["pos_imp_sum"]

    grand_clicks = sum(t["clicks"] for t in totals.values())
    grand_imp = sum(t["imp"] for t in totals.values())

    lines = [f"*GSC日次レポート* ({period})", f"表示: {grand_imp:,} / クリック: {grand_clicks:,}", ""]
    for c in CATEGORIES:
        t = totals[c]
        if t["imp"] == 0 and t["clicks"] == 0:
            continue
        ctr = (t["clicks"] / t["imp"] * 100) if t["imp"] else 0
        pos = (t["pos_imp_sum"] / t["imp"]) if t["imp"] else 0
        lines.append(
            f"*{c}* — 表示: {t['imp']:,} / クリック: {t['clicks']:,} / CTR: {ctr:.2f}% / 平均順位: {pos:.1f}"
        )
    comment = "\n".join(lines)

    client = WebClient(token=SLACK_BOT_TOKEN)
    client.files_upload_v2(
        channel=SLACK_CHANNEL,
        file=image_path,
        filename="gsc_daily_report.png",
        initial_comment=comment,
    )
    print(f"Slackに投稿しました: {SLACK_CHANNEL}")


def main():
    agg = fetch_daily_by_category()
    image_path, dates = create_chart(agg)
    if not dates:
        print("データなし")
        sys.exit(1)
    print(f"取得日数: {len(dates)}日 ({dates[0].strftime('%Y/%m/%d')} 〜 {dates[-1].strftime('%Y/%m/%d')})")
    print(f"グラフ生成: {image_path}")
    post_to_slack(image_path, agg, dates)


if __name__ == "__main__":
    main()
