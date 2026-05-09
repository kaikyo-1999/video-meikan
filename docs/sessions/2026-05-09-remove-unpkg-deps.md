# unpkg.com 依存（web-vitals / htmx / alpine.js）を全削除

## 依頼
TOP モバイル重さ調査の続き。layout.php の unpkg 依存 3 つを整理:
- A: htmx / alpine.js は未使用 → 削除
- B: web-vitals は self-host する案だったが、そもそも不要と判断 → 削除

## 調査根拠

| ライブラリ | 使用状況 |
|---|---|
| htmx | `hx-*` 属性ゼロ件（templates/src/public 全 grep） |
| alpine.js | `x-data` `x-show` `@click` 等ゼロ件、`[x-cloak]` CSS は使われてないのに残置 |
| web-vitals | layout.php:48-58 で onLCP/onCLS/... を GA4 イベント送信中。ただし `ga4/` `gsc/` の集計スクリプトでこれらのイベント名は参照されておらず、実際のレポート用途は無い |

→ 3 つとも削除して問題なし。

## 変更ファイル
- `meikan/templates/layout.php` — 以下を削除
  - `<script type="module">` の web-vitals ブロック（旧 47-59 行）
  - `<link rel="dns-prefetch" href="https://unpkg.com">`（旧 81 行）
  - `<!-- HTMX (partial swap) + Alpine.js (UI state) — Phase 0.5 -->` コメント
  - `<script defer src="https://unpkg.com/htmx.org@2.0.4"></script>`
  - `<script defer src="https://unpkg.com/alpinejs@3.14.3/dist/cdn.min.js"></script>`
  - `<style>[x-cloak]{display:none !important}</style>`

## 検証
- `php -l meikan/templates/layout.php` → No syntax errors
- 残存 grep `unpkg|web-vitals|x-cloak|htmx|alpine` → 0 件

## 期待される効果
- クリティカルパス短縮見込み: 約 1,039ms → 500ms 程度（PSI 計測値）
  - web-vitals.js (unpkg) 891ms 消失
  - htmx / alpine.js の defer ロード消失
- 外部依存ドメイン削減: unpkg.com への接続消失（DNS/TCP/TLS オーバーヘッド削減）
- バンドル： 体感は再計測で確認

## リスク
- 失われる機能: GA4 への web-vitals 送信のみ（既に未使用なので影響なし）
- ロールバック: 単純な revert で復元可

## 確認・残タスク
- [ ] PSI 再計測（デプロイ後、クォータ復活を待ってから）
- [ ] 本番反映: コミット → デプロイの判断はユーザーに委ねる
