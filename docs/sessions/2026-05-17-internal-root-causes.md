# 「外部アルゴリズム変更がない」と仮定したときに考えられる内部問題

## 依頼
2026-05-17 の Google 検索流入急落について、外部の Google アップデート要因を一旦除外して、
内部要因として何が問題と考えられるかを整理。

> 前提データは `docs/sessions/2026-05-17-google-search-drop-investigation.md` を参照。
> 主要事実: 5/11 → 5/12 で site clicks -70% / impressions -45%、goumou-osusume が pos 2.9 → 8.7 に降格、
> Google で表示を獲得した unique ページ数が **2,254 → 465（-79%）** に激減。

---

## 結論（優先度順）

| # | 問題 | 影響 | 修正コスト |
|---|---|---|---|
| 1 | **21,375 件のジャンルページが boilerplate な thin content の集合（aggregator/doorway パターン）** | 大 | 高 |
| 2 | **5/11 デプロイの内部リンク dofollow 化が、上位記事の PageRank を 91 件の薄いページに分散** | 大 | 中（低品質ページへの link nofollow を選択的に戻す） |
| 3 | **5/4 の TOP リニューアル + サイトマップ分割で内部リンク構造が大幅変更** | 中〜大 | 中 |
| 4 | **主力記事の `description:` が空（goumou / daeki / iramachio / yuuryou-adult-hikaku の 4 本）** | 中 | 低（フロントマター埋めるだけ） |
| 5 | **goumou-osusume の本文が 1,082 words しかない（"30人比較" 記事として薄い）** | 中 | 中（執筆量を増やす） |
| 6 | **記事画像の 250/298 が外部の旧 WordPress (`hpkenkyu.mixh.jp`) 依存。29 件は HTTP（mixed content）** | 中 | 中（自ドメインへ移行 / HTTPS 化） |
| 7 | **ジャンル title / description のテンプレ重複（21,375 件で 2 変数しか変わらない）** | 中 | 中（テンプレ差別化） |
| 8 | **noindex 閾値が `作品数 < 3` のままで甘い** | 中 | 低（閾値引き上げ） |

---

## 1. 21,375 件のジャンルページが aggregator/doorway パターン

### 観測事実
```
sitemap-genres.xml URL 数: 21,375
表示獲得していたページ (5/5-5/10): 2,254
表示獲得していたページ (5/12-5/14):   465
消えたページ:                      2,085 件
  ├── 2,025 件 = ジャンルページ（消失の 97%）
  ├──    51 件 = 女優ページ
  ├──     9 件 = 記事ページ
```

→ 急落の主因は **ジャンルページの大量 deindex/降格**。

### なぜ薄いのか

実測（`/sumire-4/jukujo/`, 64 作品ジャンル）:
- HTML 全体: 229KB / 892 words
- うち unique なのは「水川スミレ × 熟女」というキーワードと作品サムネ + タイトル群
- title/description は全 21,375 ページで **2 変数（女優名・ジャンル名）しか変わらない**

3 作品のページ（`/hibikiren/tekoki/`）:
- HTML 全体: 355 words
- ほぼテンプレート骨組みのみ

### Google から見た見え方
- 同じテンプレに変数だけ差し替えた 2 万ページ群
- 一次情報なし（FANZA データの並べ替えのみ）
- ジャンル「{actress}×{genre}」というクエリは検索ボリュームがほぼゼロのロングテールばかり
- → "scaled content abuse" / "doorway pages" の典型例として降格対象になりやすい

### 修正アプローチ
- `GENRE_MIN_WORKS` を 3 → 10 以上に引き上げ（noindex 範囲拡大）
- ジャンル内 description にユニークな評価文（DB から動的生成: 平均レビュー点, 最頻共演者, 作品本数ランキング等）
- 上位ジャンル（手コキ・熟女・痴女 等）には独自の解説本文を 1 本ずつ手書き

---

## 2. 5/11 デプロイの内部リンク dofollow 化が PageRank を分散させた

### 何が変わったか
- `commit f9a6e91` (5/11 23:46): 記事内の `[text](/...)` と `[btn ...](/...)` のリンク 300+ 件から `nofollow` を撤去
- 自ドメイン判定 (`isInternalUrl()`) を追加し、内部リンクは dofollow + same-tab に

### なぜ問題かもしれないか
- 一般論として内部 dofollow は SEO プラスだが、**リンク先の品質次第**
- goumou-osusume には `<a href="/...">` で 91 件の内部リンク先がある（女優ページ / 他記事 / ジャンル）
- そのうち 50+ 件が女優ページ (`/usuisaryuu/`, `/sumire-2/` 等)
- 女優ページの一部は薄く（作品数の少ない女優ページ）、PageRank の受け皿として弱い
- → 直前まで pos 2.3 で大量の link equity を保持していた goumou-osusume が、突然 50+ 件の薄い受け皿に分散させたタイミング = **5/12 の cliff と一致**

### 時系列の整合性
```
5/11 23:46 デプロイ
5/12     Googlebot 再クロール（深夜 → 日中で順次反映想定）
5/12〜   goumou-osusume が pos 2.9 → 8.7、サイト全体 -70%
```

「dofollow 化で平準化が起きた直後に上位ページが降格する」のは link equity の経済学として説明可能。

### 修正アプローチ
- 内部リンクのうち、**ジャンルページ / 単発の薄い女優ページ** に向くものを再度 `nofollow` 化（または `rel="ugc"` で抑制）
- 強いページ（記事間, トップ女優ページ）への内部リンクのみ dofollow を維持
- 或いは「nofollow 化を一時的に revert」して回復するか確認（仮説検証として最速）

---

## 3. 5/4 TOP リニューアル + サイトマップ分割で内部リンク構造が大幅変更

### 何が変わったか
- 5/4 `2dc0edb`: Filmarks 風 TOP リニューアル
- 5/4 `bb4312b`: サイトマップを 4 分割 + 作品数 <3 ジャンルページ noindex 化
- 5/4 `e9f88df`: 相互リンクページ `/cross-link/` 新設

### 観測事実
- 旧 TOP からのリンク構造が変わった → Google 内部 PageRank マップが再計算される
- 直後の 5/5-5/8 にかけて表示 unique ページが 603 → 427 に減少（既にじわじわ起きていた）

### 注意点
- TOP からの内部リンクは 47 件のみ（再確認済）
- うち 17 件がジャンルページへの直リンク
- これは「サイトの第一印象」として Google に提示される URL セット

### 修正アプローチ
- TOP からのリンク先を強いページ（記事 + ジャンルでは上位ボリュームのもの）に絞る
- 弱いジャンルページへの導線は二階層下に下げる

---

## 4. 主力記事の `description:` が空

### 観測事実
`grep` 結果（44 記事中 4 件が `description:` 空）:
```
EMPTY: daeki-osusume.md       (5/7-5/10: 15c/50i → 5/12-5/14: 9c/57i)
EMPTY: goumou-osusume.md      (76→7c, 主因)
EMPTY: iramachio-osusume.md
EMPTY: yuuryou-adult-hikaku.md
```

`<meta name="description" content="">` のため、Google は本文から snippet を自動生成。
adult content 本文から取得される snippet は SafeSearch スコアを下げる可能性。

`git log` で履歴を追うと: 2026-03-31 `7f6a614 記事ブラッシュアップ` で description が空に上書きされた（最初は適切な description があった）。

### 修正アプローチ
- 4 記事の frontmatter を直接埋める（コスト: 数分）
- 全記事 description 必須化のバリデーション追加（`batch/validate_articles.php` に組み込み）

---

## 5. goumou-osusume の本文が 1,082 words

「剛毛 AV 女優 30 人」を比較する記事として **1,082 words は薄い**。
SERP 1 位の競合は通常 5,000-10,000 words のレベル。
タイトルでは 30 人と謳いつつ、本文は 1 人あたり数行で済ませている可能性。

### 修正アプローチ
- 各女優セクションに 200-300 words の独自解説を追加
- 比較表 / 体毛タイプ分類 / FAQ 拡充

---

## 6. 外部画像 (`hpkenkyu.mixh.jp`) 依存と mixed content

### 観測事実
goumou-osusume HTML の `<img>` 集計:
```
hpkenkyu.mixh.jp:  250 件（旧 WordPress 鯖）
pics.dmm.co.jp:     16 件
src="http://...":   29 件（mixed content / 全て pics.dmm.co.jp HTTP）
```

### 問題
- HTTPS ページに HTTP 画像 = ブラウザが "Not fully secure" 警告
- 旧 WP サイトに依存 → 旧サイトが落ちると新サイトの UX が劣化（Google は再クロールで検知）
- 旧 WP 経由なので「移植/転載コンテンツ」のシグナルになる可能性

### 修正アプローチ
- HTTP → HTTPS に書き換え（pics.dmm.co.jp は HTTPS 対応している）
- 旧 WP 画像 → 自ドメイン or pics.dmm.co.jp に移行

---

## 7. ジャンル title / description のテンプレ重複

`GenreController::show()` の生成ロジック（5/13 改修後）:
```php
$pageTitle = "{$actress['name']}の{$genre['name']}作品 全{$totalWorksAll}本｜無料画像・動画付き{$latestTagSuffix} | " . SITE_NAME;
$metaDescription = "{$actress['name']}の{$genre['name']}作品全{$totalWorksAll}本を発売日順に一覧化。無料で見れる画像・動画つき。{$latestSentence}FANZAで配信中の{$actress['name']}×{$genre['name']}を網羅。";
```

→ 21,375 ページで title 構造が全く同じ（2 変数 + 件数のみ差）。Google の重複コンテンツ判定で「ほぼ同質」とみなされやすい。

### 修正アプローチ
- description に動的シグナル混入: 平均評価, 共演者ジャンル, 出演本数の特徴 など
- 上位ジャンルは静的に手書きの導入文を持たせる

---

## 8. noindex 閾値が `< 3` で甘い

`config/app.php:18` `GENRE_MIN_WORKS = 3`。

### 数感
- 作品数 = 3 のジャンルページは index されている → 355 words の薄い本文
- 5-9 本のページも同様に薄い

### 修正アプローチ
- 閾値を 10 に引き上げる（noindex 範囲拡大）
- 影響: sitemap-genres.xml の URL 数が大幅減 → Google の crawl budget が強いページに集中

---

## 推奨実施順

優先度: コスト対効果 + 仮説検証性で並べる

1. **4 記事の description を埋める**（数分・低リスク・効果は限定的だが確実）
2. **`GENRE_MIN_WORKS` を 3 → 10 へ引き上げ**（数行 + キャッシュクリア・中効果）
3. **5/11 dofollow 化を選択的 revert**（薄いページ向けリンクのみ nofollow に戻す。仮説 #2 の検証になる）
4. **HTTP → HTTPS 画像書き換え**（mass replace、技術リスク低）
5. **goumou-osusume の本文を 1,082 → 3,000+ words に補強**（執筆コスト中、最大流入源の救済）
6. **ジャンル description の動的差別化**（コード変更中、長期効果）
7. **TOP からの内部リンク選別**（弱いジャンル除外、UX 議論あり）

### 仮説検証性が高い順
- 3 (link equity 分散説) → revert すれば 1 週間で結果が見える
- 1 (description 空) → 即時反映、CTR で測定可能
- 2 (薄ページ大量) → 効果まで 2-3 週間

---

## 確認・残タスク

- [ ] DB クエリで「作品数 3-9 のジャンルページ」数を実数把握
- [ ] Search Console カバレッジで「discovered but not indexed」「crawled but not indexed」の推移確認
- [ ] 5/11 dofollow 化の revert を検討（仮説検証用）
