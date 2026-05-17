# sitemap-index の lastmod を実コンテンツ更新日に修正

## 依頼
2026-05-17 の Google 検索流入急落原因として最有力の「sitemap-index の `<lastmod>` が毎リクエスト "今日" を返している問題」を修正する。

> 詳細な根拠は `docs/sessions/2026-05-17-sitemap-regression.md` を参照。

## 実施内容

`meikan/src/controllers/SitemapController.php::index()` を修正:

- **Before**: `$today = date('Y-m-d')` を 4 つの子サイトマップ全てに一律で適用
- **After**: 各子サイトマップ内 URL の最大更新日時を計算して返す

### 子サイトマップごとの lastmod 算出ロジック

| 子サイトマップ | lastmod の根拠 |
|---|---|
| `sitemap-core.xml` | `MAX(actresses.updated_at, works.updated_at)` — TOP/meikan が動的にこれらを表示するため |
| `sitemap-articles.xml` | 記事 frontmatter の `updated_at`（無ければ `published_at`）の最大値、noindex は除外 |
| `sitemap-actresses.xml` | `SELECT MAX(updated_at) FROM actresses` |
| `sitemap-genres.xml` | `SELECT MAX(updated_at) FROM works` — 作品データが変わるとジャンルページの内容も変わるため |

### 副次的な実装ポイント

- 各算出結果は `Cache::set()` で 1 時間 TTL キャッシュ（毎リクエスト DB を叩かない）
- DB クエリは `MAX(updated_at)` のみ・既存インデックス対象なので軽量
- 値が空のときは `<lastmod>` タグ自体を出力しない（既存の `sitemap-index.php` テンプレが `!empty()` でガード済）

## 変更ファイル
- `meikan/src/controllers/SitemapController.php:9-37` — `index()` を書き換え、`$today` 一律から実日付算出に
- `meikan/src/controllers/SitemapController.php:39-93` — `articlesMaxLastmod()` / `actressesMaxLastmod()` / `worksMaxLastmod()` / `dbMaxDate()` / `pickMax()` をプライベートヘルパーとして追加

## 動作確認（ローカル）

```bash
php -S localhost:8766 -t meikan meikan/dev-server.php
curl http://localhost:8766/sitemap.xml
```

### Before（本番現状）
```xml
<sitemap>
  <loc>https://av-hakase.com/sitemap-core.xml</loc>
  <lastmod>2026-05-17</lastmod>   ← 毎日「今日」
</sitemap>
... 残り3本も全部 2026-05-17
```

### After（ローカル確認結果）
```xml
<sitemap>
  <loc>http://localhost:8766/sitemap-core.xml</loc>
  <lastmod>2026-05-04</lastmod>   ← 実コンテンツの最新日
</sitemap>
<sitemap>
  <loc>http://localhost:8766/sitemap-articles.xml</loc>
  <lastmod>2026-05-05</lastmod>   ← 直近の月次新人記事追加日と一致
</sitemap>
<sitemap>
  <loc>http://localhost:8766/sitemap-actresses.xml</loc>
  <lastmod>2026-05-04</lastmod>
</sitemap>
<sitemap>
  <loc>http://localhost:8766/sitemap-genres.xml</loc>
  <lastmod>2026-05-04</lastmod>
</sitemap>
```

- 4 本それぞれ異なる実日付になっている ✓
- 2 回目リクエストでも同じ値 = キャッシュが効いている ✓
- 子サイトマップ（articles.xml）の中身は従来通り URL/lastmod 出力 ✓
- `php -l` 構文チェック OK

## 確認・残タスク

### デプロイ後にやること
- [ ] 本番 `/sitemap.xml` を curl して各子サイトマップに実日付が入っていることを確認
- [ ] Search Console の「サイトマップ」レポートで再フェッチさせる
- [ ] 7-14 日後に unique impressed page 数 / Google organic セッションの回復を観察

### 今回の修正に含めなかったもの（次の打ち手）
1. **`sitemap-genres.xml` の各 URL に lastmod を付与**（`docs/sessions/2026-05-17-sitemap-regression.md` の #2）— 効果切り分けのため次フェーズに分離
2. **`image:loc` の HTTP → HTTPS 化** — 別 commit で対応
3. **`GENRE_MIN_WORKS = 3 → 10` で URL 圧縮** — 効果が出ない場合の追加打ち手

## ソース
- [Google: Build and submit a sitemap (lastmod ガイダンス)](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap)
- [Google: Manage your sitemaps with sitemap index files](https://developers.google.com/search/docs/crawling-indexing/sitemaps/large-sitemaps)
