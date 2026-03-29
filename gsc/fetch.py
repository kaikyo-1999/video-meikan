#!/usr/bin/env python3
"""Google Search Console データ取得スクリプト"""

import json
import os
import sys
from datetime import datetime, timedelta

from google.oauth2 import service_account
from googleapiclient.discovery import build

SITE_URL = "sc-domain:av-hakase.com"
KEY_FILE = os.path.join(os.path.dirname(__file__), "..", "marke-analytics-fa4cf49cfeef.json")
SCOPES = ["https://www.googleapis.com/auth/webmasters.readonly"]


def get_service():
    credentials = service_account.Credentials.from_service_account_file(KEY_FILE, scopes=SCOPES)
    return build("searchconsole", "v1", credentials=credentials)


def fetch_performance(service, start_date, end_date, dimensions=None, row_limit=1000):
    """Search Analytics データを取得する"""
    if dimensions is None:
        dimensions = ["query"]

    body = {
        "startDate": start_date,
        "endDate": end_date,
        "dimensions": dimensions,
        "rowLimit": row_limit,
    }
    response = service.searchanalytics().query(siteUrl=SITE_URL, body=body).execute()
    return response.get("rows", [])


def main():
    days = int(sys.argv[1]) if len(sys.argv) > 1 else 28
    dimensions = sys.argv[2].split(",") if len(sys.argv) > 2 else ["query"]

    end_date = (datetime.now() - timedelta(days=3)).strftime("%Y-%m-%d")
    start_date = (datetime.now() - timedelta(days=3 + days)).strftime("%Y-%m-%d")

    print(f"期間: {start_date} ~ {end_date}")
    print(f"ディメンション: {', '.join(dimensions)}")
    print("-" * 60)

    service = get_service()
    rows = fetch_performance(service, start_date, end_date, dimensions)

    if not rows:
        print("データなし")
        return

    for row in rows:
        keys = " | ".join(row["keys"])
        clicks = int(row["clicks"])
        impressions = int(row["impressions"])
        ctr = f"{row['ctr'] * 100:.1f}%"
        position = f"{row['position']:.1f}"
        print(f"{keys}\t clicks={clicks}\t imp={impressions}\t ctr={ctr}\t pos={position}")


if __name__ == "__main__":
    main()
