#!/usr/bin/env python3
"""GA4 日次レポート: 記事/女優/ジャンル別セッション（積み上げ）と滞在時間を可視化してSlackに投稿"""

import os
import re
import tempfile
from collections import defaultdict
from datetime import datetime

from dotenv import load_dotenv

load_dotenv(os.path.join(os.path.dirname(__file__), ".env"))

import matplotlib
matplotlib.use("Agg")
matplotlib.rcParams["font.family"] = "Hiragino Sans"
import matplotlib.pyplot as plt
import matplotlib.dates as mdates

from google.oauth2 import service_account
from googleapiclient.discovery import build
from slack_sdk import WebClient

KEY_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "marke-analytics-fa4cf49cfeef.json")
if not os.path.exists(KEY_FILE):
    KEY_FILE = "/Users/kaikyotaro/repository/video-meikan/marke-analytics-fa4cf49cfeef.json"
PROPERTY_ID = "529336238"

SLACK_BOT_TOKEN = os.environ.get("SLACK_BOT_TOKEN", "")
SLACK_CHANNEL = os.environ.get("SLACK_CHANNEL", "")

CATEGORIES = ["記事", "女優", "ジャンル", "その他"]
COLORS = {
    "記事": "#e74c3c",
    "女優": "#3498db",
    "ジャンル": "#f39c12",
    "その他": "#95a5a6",
}

# landingPage は path のみで末尾スラッシュなしのことが多い（"/mihinanami" "/sui/anal"）
ACTRESS_RE = re.compile(r"^/[a-z0-9][a-z0-9-]*/?$")
GENRE_RE = re.compile(r"^/[a-z0-9][a-z0-9-]*/[a-z0-9][a-z0-9-]*/?$")


def classify(landing_page: str) -> str:
    if "/article" in landing_page:
        return "記事"
    if GENRE_RE.match(landing_page):
        return "ジャンル"
    if ACTRESS_RE.match(landing_page):
        return "女優"
    return "その他"


def get_client():
    credentials = service_account.Credentials.from_service_account_file(
        KEY_FILE, scopes=["https://www.googleapis.com/auth/analytics.readonly"]
    )
    return build("analyticsdata", "v1beta", credentials=credentials)


def fetch_by_landing_page(client, date_range):
    """date × landingPage で取得し、Python側でカテゴリ集約する"""
    LIMIT = 100000
    body = {
        "dateRanges": [date_range],
        "dimensions": [{"name": "date"}, {"name": "landingPage"}],
        "metrics": [
            {"name": "averageSessionDuration"},
            {"name": "screenPageViewsPerSession"},
            {"name": "sessions"},
        ],
        "limit": LIMIT,
    }
    response = client.properties().runReport(
        property=f"properties/{PROPERTY_ID}", body=body
    ).execute()
    return response.get("rows", [])


def fetch_event_count_daily(client, date_range, event_name):
    body = {
        "dateRanges": [date_range],
        "dimensions": [{"name": "date"}],
        "metrics": [{"name": "eventCount"}],
        "dimensionFilter": {
            "filter": {
                "fieldName": "eventName",
                "stringFilter": {"matchType": "EXACT", "value": event_name},
            }
        },
        "orderBys": [{"dimension": {"dimensionName": "date"}}],
    }
    response = client.properties().runReport(
        property=f"properties/{PROPERTY_ID}", body=body
    ).execute()
    return {
        row["dimensionValues"][0]["value"]: int(row["metricValues"][0]["value"])
        for row in response.get("rows", [])
    }


def aggregate_by_category(rows):
    """date -> category -> {sessions, dur_sess_sum, pps_sess_sum} に集計"""
    agg = defaultdict(lambda: defaultdict(lambda: {
        "sessions": 0,
        "dur_sess_sum": 0.0,  # sum(avg_duration * sessions) for weighted avg
        "pps_sess_sum": 0.0,  # sum(pages_per_session * sessions)
    }))
    for row in rows:
        date = row["dimensionValues"][0]["value"]
        landing = row["dimensionValues"][1]["value"]
        cat = classify(landing)
        avg_dur = float(row["metricValues"][0]["value"])
        pps = float(row["metricValues"][1]["value"])
        sess = int(row["metricValues"][2]["value"])
        b = agg[date][cat]
        b["sessions"] += sess
        b["dur_sess_sum"] += avg_dur * sess
        b["pps_sess_sum"] += pps * sess
    return agg


def fmt_duration(seconds):
    m, s = divmod(int(seconds), 60)
    return f"{m}:{s:02d}"


def create_chart(agg, click_data):
    all_dates = sorted(agg.keys())
    if not all_dates:
        return None, all_dates
    dates = [datetime.strptime(d, "%Y%m%d") for d in all_dates]

    series_sess = {c: [agg[d][c]["sessions"] for d in all_dates] for c in CATEGORIES}
    series_dur = {}
    series_pps = {}
    for c in CATEGORIES:
        durs, ppss = [], []
        for d in all_dates:
            b = agg[d][c]
            if b["sessions"] > 0:
                durs.append(b["dur_sess_sum"] / b["sessions"] / 60)  # 分
                ppss.append(b["pps_sess_sum"] / b["sessions"])
            else:
                durs.append(None)
                ppss.append(None)
        series_dur[c] = durs
        series_pps[c] = ppss

    period = f"{all_dates[0][:4]}/{all_dates[0][4:6]}/{all_dates[0][6:]} 〜 {all_dates[-1][:4]}/{all_dates[-1][4:6]}/{all_dates[-1][6:]}"
    fig, axes = plt.subplots(3, 1, figsize=(14, 10), sharex=True)
    fig.suptitle(f"av-hakase.com GA4日次推移\n{period}", fontsize=14, fontweight="bold")

    # 1) 平均滞在時間 (分)
    ax = axes[0]
    for c in CATEGORIES:
        ys = series_dur[c]
        if all(v is None for v in ys):
            continue
        ax.plot(dates, ys, "o-", color=COLORS[c], label=c, markersize=4, linewidth=1.5)
    ax.set_ylabel("平均滞在時間 (分)")
    ax.legend(loc="upper left")
    ax.grid(True, alpha=0.3)

    # 2) ページ/セッション
    ax = axes[1]
    for c in CATEGORIES:
        ys = series_pps[c]
        if all(v is None for v in ys):
            continue
        ax.plot(dates, ys, "o-", color=COLORS[c], label=c, markersize=4, linewidth=1.5)
    ax.set_ylabel("ページ/セッション")
    ax.legend(loc="upper left")
    ax.grid(True, alpha=0.3)

    # 3) セッション数（積み上げ）+ FANZAクリック（折れ線・右軸）
    ax = axes[2]
    bottom = [0] * len(all_dates)
    for c in CATEGORIES:
        vals = series_sess[c]
        ax.bar(dates, vals, bottom=bottom, color=COLORS[c], alpha=0.8, label=c, width=0.8)
        bottom = [b + v for b, v in zip(bottom, vals)]
    ax.set_ylabel("セッション数")
    ax.grid(True, alpha=0.3)

    ax2 = ax.twinx()
    clicks = [click_data.get(d, 0) for d in all_dates]
    ax2.plot(dates, clicks, "s-", color="#2ecc71", label="FANZAクリック", markersize=4, linewidth=1.5)
    ax2.set_ylabel("FANZAクリック数")

    h1, l1 = ax.get_legend_handles_labels()
    h2, l2 = ax2.get_legend_handles_labels()
    ax.legend(h1 + h2, l1 + l2, loc="upper left")

    axes[2].xaxis.set_major_formatter(mdates.DateFormatter("%m/%d"))
    axes[2].xaxis.set_major_locator(mdates.WeekdayLocator(interval=1))
    plt.xticks(rotation=45)
    plt.tight_layout()

    path = os.path.join(tempfile.gettempdir(), "ga4_daily_report.png")
    fig.savefig(path, dpi=150, bbox_inches="tight")
    plt.close(fig)
    return path, all_dates


def post_to_slack(image_path, agg, all_dates, click_data):
    if not SLACK_BOT_TOKEN or not SLACK_CHANNEL:
        print("SLACK_BOT_TOKEN / SLACK_CHANNEL が未設定。Slack投稿をスキップ。")
        return

    totals = {c: {"sessions": 0, "dur_sess_sum": 0.0, "pps_sess_sum": 0.0} for c in CATEGORIES}
    for d in all_dates:
        for c in CATEGORIES:
            b = agg[d][c]
            totals[c]["sessions"] += b["sessions"]
            totals[c]["dur_sess_sum"] += b["dur_sess_sum"]
            totals[c]["pps_sess_sum"] += b["pps_sess_sum"]
    total_clicks = sum(click_data.values())

    period = f"{all_dates[0][:4]}/{all_dates[0][4:6]}/{all_dates[0][6:]} 〜 {all_dates[-1][:4]}/{all_dates[-1][4:6]}/{all_dates[-1][6:]}"
    lines = [f"*GA4日次レポート* ({period})", ""]
    for c in CATEGORIES:
        t = totals[c]
        if t["sessions"] == 0:
            continue
        dur = t["dur_sess_sum"] / t["sessions"]
        pps = t["pps_sess_sum"] / t["sessions"]
        lines.append(
            f"*{c}* — セッション: {t['sessions']:,} / 滞在: {fmt_duration(dur)} / ページ/S: {pps:.2f}"
        )
    lines.append(f"*FANZAクリック* — 合計: {total_clicks:,}")
    comment = "\n".join(lines)

    client = WebClient(token=SLACK_BOT_TOKEN)
    client.files_upload_v2(
        channel=SLACK_CHANNEL,
        file=image_path,
        filename="ga4_daily_report.png",
        initial_comment=comment,
    )
    print(f"Slackに投稿しました: {SLACK_CHANNEL}")


def main():
    client = get_client()
    date_range = {"startDate": "90daysAgo", "endDate": "yesterday"}

    print("データ取得中...")
    rows = fetch_by_landing_page(client, date_range)
    agg = aggregate_by_category(rows)
    click_data = fetch_event_count_daily(client, date_range, "fanza_click")

    image_path, all_dates = create_chart(agg, click_data)
    if not all_dates:
        print("データなし")
        return
    print(f"取得日数: {len(all_dates)}日 / 行数: {len(rows)} / FANZAクリック: {sum(click_data.values()):,}件")
    print(f"グラフ生成: {image_path}")

    post_to_slack(image_path, agg, all_dates, click_data)


if __name__ == "__main__":
    main()
