#!/usr/bin/env python3
"""GSCサイト全体分析: 期間内のクエリ・ページ・ページタイプ別バランスをレポート"""

import argparse
import re
import sys
from collections import defaultdict
from datetime import date, datetime, timedelta

sys.path.insert(0, "/Users/kaikyotaro/repository/video-meikan/gsc")
from fetch import SITE_URL, get_service

ACTRESS_RE = re.compile(r"^https://av-hakase\.com/[a-z0-9][a-z0-9-]*/$")
GENRE_RE = re.compile(r"^https://av-hakase\.com/[a-z0-9][a-z0-9-]*/[a-z0-9][a-z0-9-]*/$")
TOP_RE = re.compile(r"^https?://(www\.)?av-hakase\.com/$")


def classify(url: str) -> str:
    if "/article" in url:
        return "article"
    if TOP_RE.match(url):
        return "top"
    if GENRE_RE.match(url):
        return "genre"
    if ACTRESS_RE.match(url):
        return "actress"
    return "other"


def fetch(service, start, end, dimensions, row_limit=25000):
    rows, start_row = [], 0
    while True:
        body = {
            "startDate": start, "endDate": end, "dimensions": dimensions,
            "rowLimit": row_limit, "startRow": start_row,
        }
        resp = service.searchanalytics().query(siteUrl=SITE_URL, body=body).execute()
        chunk = resp.get("rows", [])
        rows.extend(chunk)
        if len(chunk) < row_limit:
            break
        start_row += row_limit
    return rows


def split_into_weeks(start: date, end: date):
    """指定期間を7日刻みのブロックに分割。最後のブロックは余りに合わせる"""
    weeks = []
    cur = start
    i = 1
    while cur <= end:
        wend = min(cur + timedelta(days=6), end)
        weeks.append((f"W{i}", cur.isoformat(), wend.isoformat()))
        cur = wend + timedelta(days=1)
        i += 1
    return weeks


def report_weekly_queries(service, weeks, exclude_article=True):
    print("=" * 80)
    print("[1] 週次クエリ変化")
    print("=" * 80)

    week_clicks = {}
    week_imp = {}
    for label, s, e in weeks:
        rows = fetch(service, s, e, ["query", "page"])
        qc = defaultdict(int)
        qi = defaultdict(int)
        for r in rows:
            q, p = r["keys"]
            if exclude_article and "/article" in p:
                continue
            qc[q] += int(r["clicks"])
            qi[q] += int(r["impressions"])
        week_clicks[label] = qc
        week_imp[label] = qi
        top = sorted(qc.items(), key=lambda x: -x[1])[:10]
        print(f"\n--- {label} ({s}〜{e}) Top10 ---")
        for q, c in top:
            print(f"  {c:>5} clicks  imp={qi[q]:>7}  {q}")

    total_q = defaultdict(int)
    for label in [w[0] for w in weeks]:
        for q, c in week_clicks[label].items():
            total_q[q] += c
    top20 = [q for q, _ in sorted(total_q.items(), key=lambda x: -x[1])[:20]]

    print("\n--- 期間Top20 クエリ 週次推移 ---")
    header = "クエリ".ljust(35) + " ".join(f"{w[0]:>5}" for w in weeks) + f" {'合計':>6} {'最終/初週':>9}"
    print(header)
    for q in top20:
        cs = [week_clicks[w[0]].get(q, 0) for w in weeks]
        total = sum(cs)
        ratio = f"{cs[-1]/cs[0]:.1f}x" if cs[0] > 0 else ("new" if cs[-1] > 0 else "-")
        q_disp = (q[:33] + "..") if len(q) > 35 else q
        line = q_disp.ljust(35) + " ".join(f"{c:>5}" for c in cs) + f" {total:>6} {ratio:>9}"
        print(line)

    print("\n--- 急上昇クエリ (初週<5 かつ 最終>=10、または 最終/初週>=3 かつ 最終>=20) ---")
    risers = []
    for q, total in total_q.items():
        cs = [week_clicks[w[0]].get(q, 0) for w in weeks]
        if (cs[0] < 5 and cs[-1] >= 10) or (cs[0] > 0 and cs[-1] / cs[0] >= 3 and cs[-1] >= 20):
            risers.append((q, cs, total))
    risers.sort(key=lambda x: -x[1][-1])
    for q, cs, total in risers[:15]:
        print(f"  {' '.join(f'{c:>3}' for c in cs)}  合計={total:>4}  {q}")

    print("\n--- 急落クエリ (初週>=10 かつ 最終<=初週*0.3) ---")
    fallers = []
    for q, total in total_q.items():
        cs = [week_clicks[w[0]].get(q, 0) for w in weeks]
        if cs[0] >= 10 and cs[-1] <= cs[0] * 0.3:
            fallers.append((q, cs, total))
    fallers.sort(key=lambda x: -x[1][0])
    for q, cs, total in fallers[:15]:
        print(f"  {' '.join(f'{c:>3}' for c in cs)}  合計={total:>4}  {q}")


def report_page_changes(service, current_start, current_end, prior_start, prior_end, exclude_article=True):
    print("\n" + "=" * 80)
    print(f"[2] ページ別クリック変動 ({current_start}〜{current_end} vs {prior_start}〜{prior_end})")
    print("=" * 80)

    cur = fetch(service, current_start, current_end, ["page"])
    pri = fetch(service, prior_start, prior_end, ["page"])

    def to_map(rows):
        return {r["keys"][0]: int(r["clicks"]) for r in rows
                if not (exclude_article and "/article" in r["keys"][0])}

    cmap = to_map(cur)
    pmap = to_map(pri)
    diffs = [(u, pmap.get(u, 0), cmap.get(u, 0)) for u in set(cmap) | set(pmap)]
    diffs = [(u, p, c, c - p) for u, p, c in diffs]

    print(f"\n前期合計: {sum(pmap.values()):,}  /  当期合計: {sum(cmap.values()):,}")
    print(f"対象URL数: 前期={len(pmap):,}  当期={len(cmap):,}")

    print("\n--- 急増ページ Top15 ---")
    for u, p, c, d in sorted(diffs, key=lambda x: -x[3])[:15]:
        print(f"  前期={p:>4}  当期={c:>4}  差={d:>+5}  {u}")

    print("\n--- 急減ページ Top15 ---")
    for u, p, c, d in sorted(diffs, key=lambda x: x[3])[:15]:
        print(f"  前期={p:>4}  当期={c:>4}  差={d:>+5}  {u}")

    print("\n--- 当期新規流入ページ (前期=0, 当期>=20) Top15 ---")
    new_pages = sorted([(u, c) for u, p, c, _ in diffs if p == 0 and c >= 20], key=lambda x: -x[1])
    for u, c in new_pages[:15]:
        print(f"  当期={c:>4}  {u}")

    print("\n--- 当期消滅ページ (前期>=20, 当期=0) Top15 ---")
    dead = sorted([(u, p) for u, p, c, _ in diffs if c == 0 and p >= 20], key=lambda x: -x[1])
    for u, p in dead[:15]:
        print(f"  前期={p:>4}  {u}")


def report_page_type_balance(service, weeks):
    print("\n" + "=" * 80)
    print("[3] ページタイプ別バランス（週次）")
    print("=" * 80)
    print(f"\n{'Week':<6} {'女優':>10} {'ジャンル':>12} {'記事':>10} {'トップ':>8} {'その他':>8} {'合計':>8}  {'女優%':>7} {'ジャンル%':>9} {'記事%':>7}")
    print("-" * 110)
    week_data = {}
    for label, s, e in weeks:
        rows = fetch(service, s, e, ["page"])
        clicks = defaultdict(int)
        urls = defaultdict(set)
        for r in rows:
            url = r["keys"][0]
            t = classify(url)
            clicks[t] += int(r["clicks"])
            if int(r["clicks"]) > 0:
                urls[t].add(url)
        total = sum(clicks.values())
        a_pct = clicks["actress"] / total * 100 if total else 0
        g_pct = clicks["genre"] / total * 100 if total else 0
        ar_pct = clicks["article"] / total * 100 if total else 0
        print(f"{label:<6} {clicks['actress']:>10} {clicks['genre']:>12} {clicks['article']:>10} "
              f"{clicks['top']:>8} {clicks['other']:>8} {total:>8}  "
              f"{a_pct:>6.1f}% {g_pct:>8.1f}% {ar_pct:>6.1f}%")
        week_data[label] = (clicks, urls)

    print("\n--- ユニーク流入URL数 (clicks>=1) ---")
    print(f"{'Week':<6} {'女優':>8} {'ジャンル':>10} {'記事':>8}")
    for label, _, _ in weeks:
        c, u = week_data[label]
        print(f"{label:<6} {len(u['actress']):>8} {len(u['genre']):>10} {len(u['article']):>8}")


def main():
    parser = argparse.ArgumentParser(description="GSCサイト全体分析")
    parser.add_argument("--from", dest="date_from", help="開始日 YYYY-MM-DD（省略時: 28日前）")
    parser.add_argument("--to", dest="date_to", help="終了日 YYYY-MM-DD（省略時: 3日前＝GSC確定日）")
    parser.add_argument("--include-article", action="store_true", help="/article ページも含める")
    parser.add_argument("--skip-page-changes", action="store_true", help="[2] ページ別変動レポートをスキップ")
    parser.add_argument("--skip-balance", action="store_true", help="[3] ページタイプ別バランスをスキップ")
    args = parser.parse_args()

    today = date.today()
    end = date.fromisoformat(args.date_to) if args.date_to else today - timedelta(days=3)
    start = date.fromisoformat(args.date_from) if args.date_from else end - timedelta(days=27)
    exclude_article = not args.include_article

    print(f"対象期間: {start} 〜 {end}  ({(end - start).days + 1}日)")
    print(f"/article 除外: {exclude_article}")

    service = get_service()
    weeks = split_into_weeks(start, end)
    print(f"週次ブロック: {len(weeks)}週\n")

    report_weekly_queries(service, weeks, exclude_article=exclude_article)

    if not args.skip_page_changes:
        period_days = (end - start).days + 1
        prior_end = start - timedelta(days=1)
        prior_start = prior_end - timedelta(days=period_days - 1)
        report_page_changes(service, start.isoformat(), end.isoformat(),
                            prior_start.isoformat(), prior_end.isoformat(),
                            exclude_article=exclude_article)

    if not args.skip_balance:
        report_page_type_balance(service, weeks)


if __name__ == "__main__":
    main()
