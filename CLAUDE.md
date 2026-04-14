# CLAUDE.md — video-meikan

## プロジェクト概要

AV女優のジャンル別作品データベースサイト「AV博士」(av-hakase.com) のソースコード。
PHP 8+ のカスタムMVCアーキテクチャ（フレームワーク不使用）。

## 技術スタック

- **Backend**: PHP 8+, カスタムRouter/Controller/Model
- **DB**: MySQL (utf8mb4) — actresses, works, genres + junction tables
- **Frontend**: PHP テンプレート + CSS（JS最小限）
- **コンテンツ**: Markdown記事 → ArticleControllerでHTML変換
- **キャッシュ**: ファイルベース（TTL 3600秒）
- **外部API**: FANZA アフィリエイトAPI

## ディレクトリ構成

```
meikan/
  src/controllers/   — ActressController, ArticleController, GenreController 等
  src/models/        — Actress, Work, Genre（キャッシュ付きクエリ）
  src/               — Router, Database, Cache, helpers
  batch/             — バッチスクリプト群（run_all.phpで一括実行）
  batch/data/        — バッチ用データファイル（JSON等）
  config/            — app.php（定数）, database.php（DB接続）
  templates/         — レイアウト・ページ・パーシャル
  content/articles/  — Markdown記事ファイル
  sql/               — スキーマ・マイグレーション
  public/            — CSS, JS, 画像等の静的ファイル
  cache/             — ランタイムキャッシュ
  logs/              — バッチログ（batch_YYYY-MM-DD.log）
```

## ルーティング

| パス | コントローラ |
|------|-------------|
| `/` | HomeController@index |
| `/meikan/` | TopController@index |
| `/article/` | ArticleController@index |
| `/article/{slug}/` | ArticleController@show |
| `/author/` | AuthorController@show |
| `/{actress_slug}/` | ActressController@show |
| `/{actress_slug}/{genre_slug}/` | GenreController@show |
| `/sitemap.xml` | SitemapController@index |

slug形式: `/^[a-z0-9][a-z0-9-]*$/`

## バッチ実行順序（厳守）

```
php batch/run_all.php [JSONファイルパス]
```

1. `register_actresses.php` — 新規女優登録
2. `fetch_actress_profiles.php` — プロフィール画像取得
3. `fetch_fanza.php` — 作品データ取得
4. `assign_title_genres.php` — ジャンル紐付け
5. `calculate_debut_dates.php` — デビュー日算出
6. `calculate_similar_actresses.php` — 似ている女優計算
7. `calculate_related_actresses.php` — 関連女優計算
8. `clear_cache.php` — キャッシュクリア

**順序を変えると作品画像が女優サムネイルに設定される問題が再発する。**

## 環境変数

```
DB_HOST / DB_NAME / DB_USER / DB_PASS
FANZA_API_ID
FANZA_AFFILIATE_ID          — API用アフィリエイトID
FANZA_DISPLAY_AFFILIATE_ID  — 表示用アフィリエイトID
```

## GA4 / アナリティクス

- **Measurement ID**: `G-XP1BJTKX3S`
- **Property ID**: `529336238`（GA4 Data API用）
- **サービスアカウントキー**: `marke-analytics-fa4cf49cfeef.json`（リポジトリルート）
- **カスタムイベント**: `fanza_click`（`data-fanza-cid` 属性付きリンクのクリック）
- **GA4レポートスクリプト**: `ga4/daily_report.py`

読み込み優先順位: `.env.local` > `.env`

## 記事システム

Markdown記事は `content/articles/` に配置。ArticleControllerが独自パーサーでHTMLに変換する。

### 独自記法

| 記法 | 用途 |
|------|------|
| `@actress[slug]` | 女優カード埋め込み |
| `[text](dmm.co.jp/...cid=xxx)` | FANZA商品カード埋め込み |
| `:::samples` ... `:::` | サンプル画像グリッド |
| `:::say` ... `:::` | AV博士コメント吹き出し |
| `:::faq タイトル` ... `:::` | FAQアコーディオン |
| `!img[url]` | インライン画像 |
| `[btn text](url)` | ボタンリンク |
| `==text==` / `==[red]text==` | マーカー |

### 品質チェック

```
php batch/validate_articles.php
```

`@actress[slug]` の存在チェック、画像URL検証、CID検証を実行。

## 本番環境

- サーバー: sv6810.wpx.ne.jp（Shinserver）
- パス: `~/av-hakase.com/public_html/`
- リポジトリ: `~/repo/`
- 本番バッチ実行: `nohup php batch/run_all.php > ~/run_all.log 2>&1 &`

## 開発ルール

### 絶対に守ること

- **URL構造を変更しない** — SEOに致命的影響。変更する場合は必ずユーザー承認を得る
- **バッチ実行順序を変えない** — データ不整合が発生する
- **デプロイ前に必ずコミットする** — 外部ツールが変更を上書きするリスクがある
- **本番の長時間バッチはnohupで実行** — SSH切断でプロセスが死ぬ

### SEOポリシー

- 女優ページは**ジャンル2つ以上**でindex、それ以下はnoindex
- SEO提案はGoogle公式ドキュメントで裏付けること（推測で提案しない）

### データ判定

- タイトルベースのフィルタリングは不正確。`actress_work`テーブルの件数で判定する
- 似ている女優: コサイン類似度ベース（作品数10本以上）
- 関連女優: タグ重複+デビュー時期（作品数10本未満のフォールバック）

### FANZA CID ルール

- **CIDの先頭数字プレフィックスを除去しない** — `1mgold00045` の `1` はプレフィックスではなくCIDの一部。`preg_replace('/^\d+/', '', $cid)` のような処理を加えると404になるケースがある
- 画像パスは `pics.dmm.co.jp/digital/video/{cid}/{cid}pl.jpg` で参照するため、CIDを変形してはならない
- 記事Markdownの `cid=` パラメータもDBのCIDもすべて元のCIDをそのまま使用する

### 記事Markdown ルール

- **画像は `!img[url]` で記述する** — 標準Markdown の `![](url)`（空alt）はカスタムパーサーが認識しないためテキスト表示になる
- **`![alt](url)` はalt必須** — altが空だとパーサーの正規表現 `(.+?)` にマッチしない
- **会話ブロックは `:::chat` を使う** — `![名前](avatar_url)` + テキスト形式はアバター画像が全幅表示される
- **筆者プロフィールブロックは移行しない** — WordPress固有要素。`![この記事の筆者](url)` + bio は除外対象

## ローカル開発

```bash
php -S localhost:8000 -t meikan meikan/dev-server.php
```

## Claude Code スキル

| スキル | 用途 |
|--------|------|
| `article-review` | 記事の公開前品質チェック |
| `create-debut-article` | 月別デビュー記事の生成 |
| `create-sokkuri-article` | そっくり女優比較記事の生成 |
| `migrate-article` | 本番記事のローカル取り込み |
| `research-actress` | 女優プロフィール調査 |
| `write-say-comments` | 記事内コメント吹き出し生成 |
