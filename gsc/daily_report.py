#!/usr/bin/env python3
"""GSC日次推移グラフを生成してSlackに投稿する"""

import os
import sys
import tempfile
from datetime import datetime, timedelta

from dotenv import load_dotenv

load_dotenv(os.path.join(os.path.dirname(__file__), ".env"))

import matplotlib
import matplotlib.dates as mdates
import matplotlib.pyplot as plt

matplotlib.rcParams["font.family"] = "Hiragino Sans"
from slack_sdk import WebClient

from fetch import SITE_URL, get_service, fetch_performance

# Slack設定（環境変数から読む）
SLACK_BOT_TOKEN = os.environ.get("SLACK_BOT_TOKEN", "")
SLACK_CHANNEL = os.environ.get("SLACK_CHANNEL", "")

DAYS = 90


def fetch_daily_data():
    """日別のクリック・表示回数・平均順位を取得する"""
    service = get_service()

    end_date = datetime.now() - timedelta(days=3)
    start_date = end_date - timedelta(days=DAYS - 1)

    body = {
        "startDate": start_date.strftime("%Y-%m-%d"),
        "endDate": end_date.strftime("%Y-%m-%d"),
        "dimensions": ["date"],
        "rowLimit": DAYS,
    }
    response = service.searchanalytics().query(siteUrl=SITE_URL, body=body).execute()
    rows = response.get("rows", [])

    dates, clicks, impressions, positions = [], [], [], []
    for row in sorted(rows, key=lambda r: r["keys"][0]):
        dates.append(datetime.strptime(row["keys"][0], "%Y-%m-%d"))
        clicks.append(int(row["clicks"]))
        impressions.append(int(row["impressions"]))
        positions.append(row["position"])

    return dates, clicks, impressions, positions


def create_chart(dates, clicks, impressions, positions):
    """3段グラフを生成して一時ファイルパスを返す"""
    fig, (ax1, ax2, ax3) = plt.subplots(3, 1, figsize=(12, 8), sharex=True)
    fig.suptitle(
        f"av-hakase.com GSC日次推移\n{dates[0].strftime('%Y/%m/%d')} 〜 {dates[-1].strftime('%Y/%m/%d')}",
        fontsize=14,
        fontweight="bold",
    )

    # 表示回数
    ax1.fill_between(dates, impressions, alpha=0.3, color="#4285F4")
    ax1.plot(dates, impressions, color="#4285F4", linewidth=1.2)
    ax1.set_ylabel("表示回数")
    ax1.grid(True, alpha=0.3)

    # クリック数
    ax2.fill_between(dates, clicks, alpha=0.3, color="#34A853")
    ax2.plot(dates, clicks, color="#34A853", linewidth=1.2)
    ax2.set_ylabel("クリック数")
    ax2.grid(True, alpha=0.3)

    # 平均順位（上が良い＝軸反転）
    ax3.plot(dates, positions, color="#EA4335", linewidth=1.2)
    ax3.invert_yaxis()
    ax3.set_ylabel("平均順位")
    ax3.grid(True, alpha=0.3)

    ax3.xaxis.set_major_locator(mdates.WeekdayLocator(byweekday=mdates.MO))
    ax3.xaxis.set_major_formatter(mdates.DateFormatter("%m/%d"))
    plt.xticks(rotation=45)
    plt.tight_layout()

    path = os.path.join(tempfile.gettempdir(), "gsc_daily_report.png")
    fig.savefig(path, dpi=150, bbox_inches="tight")
    plt.close(fig)
    return path


def fetch_top_pages(dates):
    """クリック数上位5ページを取得する。5件未満ならNoneを返す"""
    service = get_service()
    start_date = dates[0].strftime("%Y-%m-%d")
    end_date = dates[-1].strftime("%Y-%m-%d")
    rows = fetch_performance(service, start_date, end_date, dimensions=["page"], row_limit=5)
    if len(rows) < 5:
        return None
    rows.sort(key=lambda r: r["clicks"], reverse=True)
    return rows[:5]


def post_to_slack(image_path, dates, clicks, impressions, top_pages):
    """画像をSlackにアップロードする"""
    if not SLACK_BOT_TOKEN or not SLACK_CHANNEL:
        print("SLACK_BOT_TOKEN / SLACK_CHANNEL が未設定。Slack投稿をスキップ。")
        return

    client = WebClient(token=SLACK_BOT_TOKEN)

    period = f"{dates[0].strftime('%Y/%m/%d')} 〜 {dates[-1].strftime('%Y/%m/%d')}"
    total_clicks = sum(clicks)
    total_imp = sum(impressions)
    comment = f"*GSC日次レポート* ({period})\n表示: {total_imp:,} / クリック: {total_clicks:,}"

    if top_pages:
        comment += "\n\n*クリック上位5ページ*"
        for i, row in enumerate(top_pages, 1):
            url = row["keys"][0]
            c = int(row["clicks"])
            imp = int(row["impressions"])
            pos = f"{row['position']:.1f}"
            comment += f"\n{i}. {url}\n    clicks: {c} / imp: {imp:,} / pos: {pos}"

    client.files_upload_v2(
        channel=SLACK_CHANNEL,
        file=image_path,
        filename="gsc_daily_report.png",
        initial_comment=comment,
    )
    print(f"Slackに投稿しました: {SLACK_CHANNEL}")


def main():
    dates, clicks, impressions, positions = fetch_daily_data()
    if not dates:
        print("データなし")
        sys.exit(1)

    print(f"取得日数: {len(dates)}日 ({dates[0].strftime('%Y/%m/%d')} 〜 {dates[-1].strftime('%Y/%m/%d')})")

    image_path = create_chart(dates, clicks, impressions, positions)
    print(f"グラフ生成: {image_path}")

    top_pages = fetch_top_pages(dates)
    post_to_slack(image_path, dates, clicks, impressions, top_pages)


if __name__ == "__main__":
    main()
