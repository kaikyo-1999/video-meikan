# DBサイトSEO ギャップ分析 (2026-04)

対象: av-hakase.com
調査日: 2026-04-10
スコープ: 直近2〜3年 (2024 H1 〜 2026 Q1) のSEOベストプラクティスを踏まえた、現状コードベースとの差分棚卸し。

関連ドキュメント:
- `docs/seo-launch-checklist-speed.md` — Core Web Vitals 観点は既に整理済。本書ではコンテンツ/構造/クロール/スキーマを中心に扱う。

---

## 0. エグゼクティブサマリ

DBサイトは「大量の自動生成ページ」という性質上、2024年3月の "scaled content abuse" ポリシー刷新以降、**テンプレート品質 × ページ単体の独自価値 × 内部リンク設計**の3点で差がつく構造になった。現状の実装は基礎 (canonical/OGP/JSON-LD/sitemap/breadcrumb) は押さえているが、以下の7点が2024-2026基準で弱い。

| 優先度 | 項目 | 影響 |
|:---:|---|---|
| **P0** | CLAUDE.md記載の「ジャンル2つ以上でindex」ルールが**実装されていない** | 低品質ページがindexされscaled-content判定リスク |
| **P0** | 記事 (`/article/{slug}/`) がsitemapに**含まれていない** | 記事の発見が遅い・lastmodが伝わらない |
| **P0** | `robots.txt` が**存在しない** | sitemap位置の明示・クロール制御ができない |
| **P1** | LCP画像 `fetchpriority="high"` 未対応 / 全画像lazy | LCP悪化（launch-checklist既出） |
| **P1** | 女優ページに `Person` 以外の詳細スキーマなし (`birthDate`, `height`, `image`, `sameAs` 等が欠落) | エンティティ認識・ナレッジパネル化が進まない |
| **P1** | 作品 (Work) の `Product`/`VideoObject`/`Review` スキーマ未実装 | rich result 機会損失 |
| **P2** | WebP/AVIF, `srcset`, 画像サイトマップ未対応 | LCP・画像検索流入機会損失 |

---

## 1. 2024-2026 ベストプラクティス サマリ

### 1.1 コンテンツ品質 (最重要シフト)
- **Scaled Content Abuse ポリシー (2024年3月)**: 「検索順位操作目的で大量生成された、独自価値の乏しいページ」を手動/アルゴリズム両方でペナルティ対象化。AIかどうかではなく "unoriginal at scale" が基準。DBサイトの自動生成ジャンルページは直撃リスク。
- **Helpful Content 統合 → Core Updateへ (2024年3月)**: Helpful Content Systemは独立シグナルではなくCore Updateに吸収。**ページ単体ではなくサイト全体**で品質評価される。1ページの低品質が全体に波及する。
- **E-E-A-T の "Experience" 強化 (2025-2026)**: 一次情報・実体験・著者の検証可能なアイデンティティが重視。著者ページに `Person` + `sameAs` (SNS, Wikipediaなど) を置いて Knowledge Graph と結びつけることが推奨。

### 1.2 技術/構造
- **Core Web Vitals (2024-03〜): FID廃止 → INPへ**。閾値は LCP ≤2.5s / INP ≤200ms / CLS ≤0.1。75パーセンタイルで評価。
- **LCP対策の実務**: LCP画像は `fetchpriority="high"` + `loading="eager"`, それ以外は `loading="lazy"`。WebP/AVIF化で25〜50%の転送削減。
- **Sitemap**: `priority` / `changefreq` はGoogleが無視。**`lastmod` のみ重要**。ただし lastmod は "実際に主要コンテンツが更新された時" だけ更新すべき (毎日更新するとシグナル無効化)。5万URL超 or 50MB超で index分割。
- **Faceted Navigation**: フィルタ組み合わせでできる薄いページは `noindex, follow` + 安定した需要のある軸のみ `index`。GSCのURLパラメータツールは廃止済なので、コード側で制御必須。
- **内部リンク = Hub & Spoke**: 重要ページへは3クリック以内、pillar⇔spokeを双方向リンク、アンカーテキストに意図を込める。

### 1.3 構造化データ
- JSON-LD推奨。Google公式リッチリザルト対応は `Article`, `BreadcrumbList`, `FAQPage`, `HowTo`, `Product`, `VideoObject`, `Organization`, `WebSite (SearchAction)`, `ItemList(carousel)` など。
- `Person` はGoogleリッチリザルトに未対応だが**エンティティ認識・AI検索 (SGE/ChatGPT) 経由で読まれる**ため依然価値あり。
- 同一ページ内で複数の `@type` を `@graph` で束ねる書き方が推奨。

---

## 2. 現状実装の棚卸し

### 2.1 ある (OK)
| 項目 | 場所 |
|---|---|
| title/description/canonical/OGP/Twitter Card | `meikan/templates/layout.php:14-30` |
| 共通 `WebSite` JSON-LD | `layout.php:37` |
| `BreadcrumbList` JSON-LD | `templates/partials/breadcrumb.php` |
| `ItemList` (ジャンル/作品) | `ActressController.php:45-71`, `GenreController`, `TopController` |
| `Article` JSON-LD (datePublished/Modified/author/publisher) | `ArticleController.php:40-60` |
| `Person` (著者) | `AuthorController.php` |
| `Organization` (HomeController) | `HomeController.php:59` |
| Sitemap (単一XML) | `SitemapController.php` |
| 301リダイレクト (旧slug→新slug) | `GenreController.php`, `slug_redirects.php` |
| .htaccess: HTTPS強制, HSTS, gzip, 1年キャッシュ, セキュリティヘッダ | `meikan/.htaccess` |
| 画像 alt / width / height / lazy | `actress-card.php` 他 |
| noindexフラグ (記事) | `ArticleController.php:65` (Markdownフロントマター起点) |
| preconnect `pics.dmm.co.jp` | `layout.php:31-32` |

### 2.2 ない / 不足 (NG)

#### P0 — 即対応

**① 女優ページのnoindex条件が実装されていない**
- `CLAUDE.md` には「女優ページは**ジャンル2つ以上**でindex、それ以下はnoindex」と明記されている。
- 実コード `ActressController.php:5-104` には `noindex` を `render()` に渡す処理がない。`isFewWorks` 判定 (L15) は画面表示の分岐のみ。
- `SitemapController.php:34-53` は **全女優** を sitemap に登録 (ジャンルページのみ除外)。
- → 作品数≤10の女優ページが現状indexされている。これが多数なら scaled-content リスク。
- **対応**: `ActressController::show()` で `count($genres) < 2` なら `noindex => true` を render に渡す。Sitemapからも同条件で除外する。

**② 記事がsitemapに入っていない**
- `SitemapController.php:27-31` は `/article/` 一覧ページのみ。個別記事 `/article/{slug}/` は登録なし。
- `ArticleController::allArticles()` で published_at / updated_at は取得可能なので `lastmod` も付与できる。
- **対応**: `SitemapController` に記事ループを追加。`noindex: true` のフロントマターが付いた記事は除外。`lastmod` は `updated_at ?: published_at` を使用。

**③ robots.txt がない**
- `meikan/public/` 配下に `robots.txt` が存在しない。
- **対応**: `meikan/public/robots.txt` を作成し、最低限以下:
  ```
  User-agent: *
  Allow: /
  Disallow: /cache/
  Disallow: /logs/
  Sitemap: https://av-hakase.com/sitemap.xml
  ```

#### P1 — 早めに対応

**④ LCP画像最適化 (既出)**
- `docs/seo-launch-checklist-speed.md` の "NG" 項目そのまま。ファーストビュー画像の `fetchpriority="high"` + `loading="eager"`。
- 該当: HomeController の1stビュー、ActressControllerの女優メイン画像、記事のヒーロー画像。

**⑤ 女優ページのスキーマが薄い**
- 現状 `ItemList` のみ。女優エンティティ自体のスキーマがない。
- **対応**: `@graph` で以下を束ねる:
  ```json
  {
    "@context": "https://schema.org",
    "@graph": [
      { "@type": "Person", "name": "...", "birthDate": "...",
        "height": "...", "image": "...", "url": "...",
        "sameAs": ["https://twitter.com/..."] },
      { "@type": "BreadcrumbList", ... },
      { "@type": "ItemList", "itemListElement": [...] }
    ]
  }
  ```
- `sameAs` にFANZA女優ページ/Wikipedia/SNSを列挙するとエンティティ認識に効く。

**⑥ 作品 (Work) のスキーマ未実装**
- 女優ページ/ジャンルページ内で作品カードを列挙しているが、各作品に `Product` or `VideoObject` の item-level schema がない。
- `ItemList` の `itemListElement` に `@type: ListItem` だけでなく `item: { @type: "Product", name, image, offers: {...} }` をネスト可能。carousel rich result の条件を満たしうる。
- アフィリエイトコンテンツの性質上 `Product` + `offers.url` が自然。`sku` にCIDを使える。

**⑦ 構造化データ全般: `@graph` 化**
- 現在、`layout.php:37` の `WebSite` と各コントローラの `jsonLd` が別々の `<script>` として出力されている。
- `@graph` に統合するとGoogleがエンティティ関係を解釈しやすい (公式推奨)。
- 副次効果: `WebSite` に `potentialAction: SearchAction` を追加するとサイトリンク検索ボックスの条件を満たす。

#### P2 — 余裕があるとき

**⑧ 画像フォーマット**
- 全画像が JPEG/PNG。WebP/AVIFは未対応。
- DMM側の画像URLを変更できないため、`<picture>` での srcset は DMM画像には適用困難。
- 自サイトホスト画像 (`public/images/`, `public/favicon*.png`, OG画像) のみWebP化する価値あり。
- **画像サイトマップ** (`image:image` ネームスペース) を `sitemap.xml` に追加すると画像検索流入に寄与。

**⑨ 記事の構造化データ拡充**
- `:::faq` ブロックに対応する `FAQPage` JSON-LD を自動生成すれば検索結果に展開される可能性あり (ただし2023年以降Googleは FAQ rich result を health/gov 以外で縮小しているため効果は限定的)。
- `:::say` 会話ブロックはスキーマ対象外。
- 記事内の画像に `ImageObject` を付けるより、`Article.image` に代表画像URL (16:9, 4:3, 1:1の3アスペクト) を配列で渡すのがGoogle推奨フォーマット。→ 現状 `ArticleController.php:40-60` の `Article` スキーマに `image` プロパティがない。追加すべき。

**⑩ ページネーション**
- `rel="prev/next"` はGoogleが2019年以降未使用と明言済。**対応不要**だが、代わりに canonical を `?page=N` 付きで自己参照させること (現状 `currentFullUrl()` がそれを満たしているか要確認)。
- ページネーション先が薄い場合は `noindex` が無難。

**⑪ 著者ページの E-E-A-T 強化**
- `/author/` の `Person` スキーマに `sameAs` (SNS, 外部プロフィール) が入っていれば強いが未検証。
- "av博士" はペルソナなので、運営会社情報 (`Organization` の `founder`/`employee`) で補強するのも一案。
- 「なぜこのサイトが信頼できるか」をAbout/運営情報ページで明文化するとQRG的に加点。

**⑫ CSP未設定**
- `docs/seo-launch-checklist-speed.md` で既に指摘済。SEO直接影響は小さいが、E-E-A-T の Trust 項目としてゼロではない。

---

## 3. 推奨アクションプラン (優先度順)

| # | タスク | 工数 | 想定ファイル |
|---|---|---|---|
| 1 | 薄い女優ページのnoindex実装 | 小 | `ActressController.php`, `SitemapController.php` |
| 2 | 記事をsitemapに追加 (lastmod付き) | 小 | `SitemapController.php` |
| 3 | `robots.txt` 追加 | 極小 | `meikan/public/robots.txt` |
| 4 | LCP画像 `fetchpriority="high"` + 1stビュー `eager` 化 | 小 | 各テンプレート (home, actress, articles) |
| 5 | 女優ページの `Person` スキーマ追加 + `@graph` 統合 | 中 | `ActressController.php`, `layout.php` |
| 6 | `Article` スキーマに `image` プロパティ追加 | 小 | `ArticleController.php` |
| 7 | 作品カードに `Product` スキーマ (ItemListにネスト) | 中 | `ActressController.php`, `GenreController.php` |
| 8 | `WebSite` に `potentialAction: SearchAction` | 小 | `layout.php` |
| 9 | 画像サイトマップ (自ホスト画像のみ) | 中 | `SitemapController.php` |
| 10 | WebP化 (ロゴ・OG画像等 自ホスト画像のみ) | 中 | `public/images/` |

## 4. やらなくてよいこと (ベストプラクティス上 "無効" とされたもの)

- `priority` / `changefreq` タグのチューニング → Google無視。現状値の調整より削除でも可。
- `rel="prev/next"` → 2019年以降未使用。
- `meta name="keywords"` → 20年前から無視。現状未使用でOK。
- FAQ rich result 目当ての `FAQPage` 乱用 → 2023以降 health/gov 以外は表示されにくい。

---

## 参考資料 (2024-2026)

- [Google: March 2024 core update and new spam policies](https://developers.google.com/search/blog/2024/03/core-update-spam-policies)
- [Google Search Central: Core Web Vitals](https://developers.google.com/search/docs/appearance/core-web-vitals)
- [Google Search Central: Build and submit a sitemap](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap)
- [Google Search Central: Managing crawling of faceted navigation URLs](https://developers.google.com/crawling/docs/faceted-navigation)
- [Google Search Central: Helpful content guide](https://developers.google.com/search/docs/fundamentals/creating-helpful-content)
- [Google Search Central: Structured data general guidelines](https://developers.google.com/search/docs/appearance/structured-data/sd-policies)
- [Google Search Central: Carousel (ItemList) structured data](https://developers.google.com/search/docs/appearance/structured-data/carousel)
- [web.dev: Defining Core Web Vitals thresholds](https://web.dev/articles/defining-core-web-vitals-thresholds)
- [MDN Blog: Fix LCP by optimizing image loading](https://developer.mozilla.org/en-US/blog/fix-image-lcp/)
- [Search Engine Land: SEO priorities for 2025](https://searchengineland.com/seo-priorities-2025-453418)
- [Search Engine Land: Guide to E-E-A-T](https://searchengineland.com/guide/google-e-e-a-t-for-seo)
- [Search Engine Journal: Google Spam Policies deep dive](https://www.searchenginejournal.com/in-depth-look-at-google-spam-policies-updates/511005/)
