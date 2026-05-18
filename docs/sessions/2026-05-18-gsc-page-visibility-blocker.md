# GSC ページ別 visibility 比較分析（着手・ブロッカー）

## 依頼
直近で表示がなくなったページと引き続き表示されているページの違いを、GSC APIを叩いて検討する。

## 状況：API認証ブロッカー
本セッションは Claude Code on the web の ephemeral container で動作しており、
GSC API のサービスアカウントキー `marke-analytics-fa4cf49cfeef.json`（gitignored）が
コンテナ内に存在しない。

確認したこと:
- `find / -name "marke-analytics*"` — ヒットなし
- 環境変数（`GOOGLE_APPLICATION_CREDENTIALS` 等）も未設定
- `/run/secrets/`, `/etc/secrets/` も存在しない

フォールバック候補も不可:
- **Ahrefs MCP `gsc-pages`**: `management-projects` を確認したが、
  Ahrefs 側に登録されているプロジェクトは `lucky.mixh.jp / hot.jetboy.jp / hpkenkyu.mixh.jp` のみ。
  **`av-hakase.com` は未登録**のため Ahrefs 経由でも GSC データを取得できない。

## 用意したもの
分析スクリプトはコミット済み: `gsc/analyze_page_visibility.py`

- 期間: BEFORE = 2026-04-28 ~ 2026-05-10 (13日, cliff前) / AFTER = 2026-05-12 ~ 2026-05-15 (4日, cliff後)
- ページ単位で `clicks / impressions / position` を取得し、pagination 対応
- カテゴリ分類:
  - `vanished` : BEFORE で imp≥20 だが AFTER で imp==0
  - `shrunk`   : BEFORE imp≥20、AFTER の日次 imp が 30%未満に縮小
  - `retained` : 両期間で表示あり、減少 30%未満
  - `emerged`  : BEFORE imp==0、AFTER imp>0
- ページタイプ別（article / actress / genre / top）×カテゴリのクロス集計を出力

ローカルで以下を実行すれば結果が得られる:
```bash
python3 gsc/analyze_page_visibility.py | tee gsc_page_visibility_$(date +%F).txt
```

## 確認事項（ユーザー宛て）
以下のいずれかで進められる:
1. **ローカルで実行**して出力を貼ってもらう（最速・安全）
2. キーファイルを安全な経路（コンテナの secret 機構）で供給する設計に変える
3. Ahrefs 側に av-hakase.com を登録する（コスト要相談）

おすすめは 1。出力 (`gsc_page_visibility_*.txt`) を貼ってもらえれば、続きの比較分析を本セッションで仕上げる。
