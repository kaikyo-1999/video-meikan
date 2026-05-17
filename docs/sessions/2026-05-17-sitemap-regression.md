# サイトマップ変更（2026-05-04）が悪影響を与えている可能性の検証

## 依頼
"サイトマップの変更が悪影響になってない？" — 5/4 のサイトマップ分割（commit `bb4312b`）が
今回の流入急落の主因かどうかを検証。

## 結論

**強く悪影響と疑われる。少なくとも主要な共犯**。
具体的な不具合 3 点（最大の問題は #1）:

1. 🔴 **`<sitemap>` の `lastmod` が毎リクエスト "今日" を返している（明確な anti-pattern）**
2. 🟠 **`sitemap-genres.xml` の 21,375 URL 全てに `lastmod` が無い**
3. 🟡 **5/4 に sitemap 形式が `urlset` → `sitemapindex` に変わり、再発見コストを Google に強いている**

タイミングは drop と整合（5/4 デプロイ → 5/5 から unique 表示ページが減り始める → 5/12 に cliff）。

---

## 1. `<sitemap>` の lastmod が毎リクエスト "今日" 問題（最も重大）

### コード
`meikan/src/controllers/SitemapController.php:9-20`

```php
public function index(array $params): void
{
    header('Content-Type: application/xml; charset=UTF-8');
    $today = date('Y-m-d');                    // ← 毎リクエスト評価される
    $sitemaps = [
        ['loc' => fullUrl('sitemap-core.xml'),      'lastmod' => $today],
        ['loc' => fullUrl('sitemap-articles.xml'),  'lastmod' => $today],
        ['loc' => fullUrl('sitemap-actresses.xml'), 'lastmod' => $today],
        ['loc' => fullUrl('sitemap-genres.xml'),    'lastmod' => $today],
    ];
    render('sitemap-index', ['noLayout' => true, 'sitemaps' => $sitemaps]);
}
```

### 実際のレスポンス（2026-05-17 確認）
```xml
<sitemapindex>
  <sitemap>
    <loc>https://av-hakase.com/sitemap-core.xml</loc>
    <lastmod>2026-05-17</lastmod>   ← 今日
  </sitemap>
  <sitemap>
    <loc>https://av-hakase.com/sitemap-articles.xml</loc>
    <lastmod>2026-05-17</lastmod>   ← 今日
  </sitemap>
  <sitemap>
    <loc>https://av-hakase.com/sitemap-actresses.xml</loc>
    <lastmod>2026-05-17</lastmod>   ← 今日
  </sitemap>
  <sitemap>
    <loc>https://av-hakase.com/sitemap-genres.xml</loc>
    <lastmod>2026-05-17</lastmod>   ← 今日
  </sitemap>
</sitemapindex>
```

→ **どの子サイトマップも実際の内容変更とは無関係に "今日" を返している**。
明日リクエストすれば 2026-05-18 になる。デプロイ以降この状態が **13 日連続**。

### Google 公式ガイダンスに違反

[Google: Sitemaps and the lastmod tag](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap):

> "Be honest about lastmod. Don't lie about your lastmod values. Google might detect when lastmod is incorrect and **will start to distrust them**."

> "Mistakes such as updating the `<lastmod>` value when the sitemap is generated rather than when the individual page was last modified may result in this signal being ignored by search engines."

**当サイトの実装は、まさにこの "Google が ignore する" パターンに合致**:
- 子サイトマップの中身が変わっていなくても毎日 "今日" を返す
- 13 日間連続で「全 4 サイトマップが更新された」と主張
- Google が "この site の lastmod は信用できない" と判定する根拠が十分

### Google の挙動（仮説）

1. 5/4: sitemap 形式変更。Google は新フォーマットを解析。
2. 5/5–5/10: 毎日 "全部 lastmod=today" を見る。"21,000+ ページが毎日変わる" は明らかに不自然と判定。
3. 5/10 以降: lastmod シグナル全体を distrust。crawl 優先度判定で sitemap を補助情報として使わなくなる。
4. 結果: クロールバジェット配分が "established 強ページ" に偏り、薄いジャンルページの再評価が止まる → impressions 獲得ページが激減

---

## 2. `sitemap-genres.xml` の 21,375 URL に lastmod が無い

### コード
`meikan/src/controllers/SitemapController.php:94-118`

```php
$urls[] = [
    'loc' => fullUrl($actress['slug'] . '/' . $genreSlug . '/'),
    'changefreq' => 'weekly',
    'priority' => '0.5',
    // lastmod なし
];
```

### 実際のレスポンス
```xml
<url>
    <loc>https://av-hakase.com/mikami-yua/kyonyu/</loc>
    <changefreq>weekly</changefreq>
    <priority>0.5</priority>
</url>
```

### 問題
- `lastmod` がない → Google は「いつ変わったか」分からないので **再クロール優先度を下げる**
- ジャンルページは新作の発売で内容が変わる（最新作リスト・件数）はずだが、その signal が伝わらない
- 結果: 21,375 ページ全体の re-crawl が後回しになり、SERP からじわじわ消える

### 修正方法
DB の `works.released_at` の最大値（actress × genre の最新作品の発売日）を lastmod に使う:
```php
$urls[] = [
    'loc' => ...,
    'lastmod' => Work::latestReleaseDate($actressObj['id'], $genre['id']),
    'changefreq' => 'monthly', // weekly → monthly のほうが現実的
    'priority' => '0.5',
];
```

---

## 3. 5/4 の sitemap 形式変更（urlset → sitemapindex）

### 変化
| | Before 5/4 | After 5/4 |
|---|---|---|
| 形式 | 単一 `<urlset>` | `<sitemapindex>` + 4 子 |
| URL 数 | ~22,500（推定） | core 6 / articles 44 / actresses 1,108 / genres 21,375 |
| Search Console 登録 | `sitemap.xml`（urlset 直接読み込み） | `sitemap.xml`（index）→ 子を再発見 |

### 影響
- Google Search Console は新フォーマットを認識・再解析する必要がある
- 子サイトマップを **新規 sitemap として再登録** する処理が裏側で発生
- 数日〜2 週間程度、URL カバレッジの再構築期間が発生する（Google のドキュメントには明記されていないが SEO 業界では一般論）
- この間、indexing がノイジーになる

### タイミングの整合

```
5/4    sitemap 分割 + 形式変更
5/5    unique impressed pages: 603
5/8    427 (-29%)  ← Google が新サイトマップ を処理し始めて再評価ノイズ
5/9    370
5/10   342
5/11   291         ← nofollow 撤去デプロイ
5/12   275
5/13   141 (cliff) ← lastmod distrust + nofollow 効果 + crawl 再分配の複合
5/14   109
```

→ **sitemap 変更は drop の "じわじわ部分"（5/5-5/10 の 603→342）を最もよく説明する**。
5/11 の nofollow 撤去 + 累積した sitemap シグナルの劣化が 5/12-5/14 の cliff を引き起こした、という二段構造で解釈できる。

---

## 副次的な sitemap 問題

### 3-1. actress sitemap の `<image:loc>` が HTTP

```xml
<image:image>
    <image:loc>http://pics.dmm.co.jp/mono/actjpgs/mikami_yua.jpg</image:loc>
    <image:title>三上悠亜</image:title>
</image:image>
```

集計: 1,035 件が HTTP（HTTPS は 44 件のみ）。サイトマップ自体は HTTPS で配信されているのに、参照画像が HTTP。Google から見れば「HTTPS サイトなのに HTTP リソースを sitemap で宣言する」=混在コンテンツ的なシグナル。

### 3-2. ジャンルページの薄さと URL 数のミスマッチ

ランダム 8 件サンプル:
| URL | 作品数 |
|---|---:|
| /oohara-amu/shiroto/ | 4 |
| /miru/onna-kyoushi/ | 4 |
| /tsujii-honoka/gyakunan/ | 8 |
| /ruri/nakadashi/ | 79 |
| /reika/m-otoko/ | 6 |
| /yuri/ane-imouto/ | 4 |
| /mio/onna-joushi/ | 4 |
| /arisu/nurse/ | 5 |

→ 7/8 が 4-8 本の薄いページ。21,375 件のうち大多数がこのレンジに分布していると推定。
- thin pages が 90%+ の sitemap = Google から見て "noisy aggregator"
- `GENRE_MIN_WORKS=3` を 10+ に引き上げれば、sitemap URL を 1/3〜1/4 に圧縮できる見込み

### 3-3. `changefreq=weekly` の過剰主張

`actresses.xml` も `genres.xml` も `<changefreq>weekly</changefreq>`。
- ジャンルページが毎週変わるサイトはほぼない（新作追加は月単位）
- Google は changefreq を hint としてしか扱わないが、weekly + lastmod なしの組み合わせは「中身は分からないが頻繁に更新と主張するページ」として扱われやすい

---

## 推奨修正（優先順）

### 即時対応（コード変更）

#### 1. sitemap-index の lastmod を「子サイトマップ内 URL の最大 lastmod」に変更
```php
public function index(array $params): void
{
    header('Content-Type: application/xml; charset=UTF-8');
    $sitemaps = [
        ['loc' => fullUrl('sitemap-core.xml'),      'lastmod' => self::coreLastmod()],
        ['loc' => fullUrl('sitemap-articles.xml'),  'lastmod' => self::articlesLastmod()],
        ['loc' => fullUrl('sitemap-actresses.xml'), 'lastmod' => self::actressesLastmod()],
        ['loc' => fullUrl('sitemap-genres.xml'),    'lastmod' => self::genresLastmod()],
    ];
    render('sitemap-index', ['noLayout' => true, 'sitemaps' => $sitemaps]);
}
```
それぞれ DB の `MAX(updated_at)` or 記事 frontmatter の最新 updated_at で算出。

#### 2. sitemap-genres.xml に lastmod を追加
ジャンルごとに該当作品の最新 `released_at` を lastmod とする。

#### 3. `image:loc` を `http://` → `https://` に書き換え
pics.dmm.co.jp は HTTPS 対応済。

#### 4. `GENRE_MIN_WORKS=3` を 10 に引き上げ
sitemap URL 数を圧縮（21,375 → 推定 8,000–12,000 程度）。
crawl budget が強いページに集中する。

#### 5. `changefreq` を現実に合わせる
- actress: weekly → monthly
- genre: weekly → monthly
- article: monthly のまま

### 中期対応（Search Console 側）

- 子サイトマップを Search Console に個別登録（`sitemap-articles.xml`, `sitemap-actresses.xml`, `sitemap-genres.xml`）
- カバレッジレポートで「Discovered – currently not indexed」「Crawled – currently not indexed」の URL 数推移をモニタリング
- 「Sitemap」レポートで子サイトマップごとに submitted/indexed 比率を観察

---

## 検証方法

1. **修正前の baseline 記録**
   - GSC: 5/15-5/17 の unique impressed page 数（GSC データが揃い次第）
   - GA4: Google organic sessions の日次

2. **修正デプロイ後 7-14 日でモニタリング**
   - `lastmod` 修正だけ先行デプロイし、他は据え置きで効果を切り分ける
   - GSC のクロール統計（リクエスト/日）に変化があるか
   - 「インデックス カバレッジ」で indexed URL 数が回復するか

3. **失敗時の rollback**
   - 修正コードは小さい（SitemapController.php のみ）ので revert は容易

---

## ソース

- [Google: Sitemaps — Build and submit a sitemap (lastmod ガイダンス)](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap)
- [Google: Manage your sitemaps with sitemap index files](https://developers.google.com/search/docs/crawling-indexing/sitemaps/large-sitemaps)
- [sitemaps.org Protocol — lastmod 定義](https://www.sitemaps.org/protocol.html)
- [Yoast: Google and Bing stress the importance of lastmod in XML sitemaps](https://yoast.com/lastmod-xml-sitemaps-google-bing/)
- [Bing Blog: The Importance of Setting the "lastmod" Tag (2023)](https://blogs.bing.com/webmaster/february-2023/The-Importance-of-Setting-the-lastmod-Tag-in-Your-Sitemap)
