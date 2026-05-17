# Google 検索流入の急落原因調査（2026-05-17）

## 依頼
直近の Google 検索流入が大きく下がっている件について、
(1) いつから下がっているか、(2) なぜ下がっているか（GSC → GA4 → 変更内容で深堀り）を調査。

## 結論サマリ

- **急落開始: 2026-05-11 → 2026-05-12（GSC）／ 2026-05-12 → 2026-05-13（GA4）**
- **規模: サイト全体で クリック -70%、表示 -45%**
- **当サイトのコード変更に直接的な犯人は見つからない**（5/11 23:46 デプロイは内部リンク dofollow 化＝改善方向）
- **同時期に業界の信頼できる SEO 観測筋（Search Engine Roundtable）が "5/8" と "5/13-14" の Google 検索順位の大幅変動を報告。Google は公式には未確認**
- **当サイトの落ち方（pos 降格・サイト全体一律・deindex 含む）は 2026-05 の業界観測パターンと整合**
- 二次要因: 主力記事 `/article/goumou-osusume/` 単独でクリック 76→7（pos 2.9→8.7）に下落し、サイト全体減のほぼ全量を占める

> ⚠️ **2026-05 に Google が公式に発表した検索アルゴリズム更新は存在しない**（Google Search Status Dashboard で確認。最終は March 2026 core update / spam update、4/8 完了）。
> 「5/11 に 80% の上位結果が変動／aggregator が直撃された」という話は一部 SEO メディアの分析であり、**Google 公式の announce ではない**。

---

## 1. いつから下がっているか

### サイト全体の日次クリック / 表示（GSC, sc-domain:av-hakase.com）

```
2026-04-15  205c 2026i  ← 4月のピーク帯
...
2026-05-08  178c  923i  ← この日に SEO 観測筋は volatility spike を報告（後述）
2026-05-09  198c 1001i  ← 直前ピーク
2026-05-10  159c  817i
2026-05-11  125c  757i  ← 下落開始
2026-05-12   66c  673i  ← クリック半減
2026-05-13   45c  313i  ← 表示も半減（cliff）／SE Roundtable で 5/13-14 大変動を報告
2026-05-14   40c  313i
```

ピーク帯（5/4-5/10）と直近（5/12-5/14）の日次平均比較:

| 指標 | 5/7-5/10平均 | 5/12-5/14平均 | 変化 |
|---|---|---|---|
| Clicks | 170 | 51 | **-70%** |
| Impressions | 820 | 449 | **-45%** |

### GA4 Google Organic セッション（landing 別合算）

GSC は 3 日遅れなので、GA4 で 5/15-5/16 まで追跡:

```
date        total  google_organic
2026-05-09   498    157  ← ピーク
2026-05-10   511    152
2026-05-11   421    119
2026-05-12   416     81
2026-05-13   305     48
2026-05-14   375     47
2026-05-15   295     28   ← さらに悪化
2026-05-16   440     32
```

- Google organic だけが急減、direct / 他流入はほぼ横ばい → 「サイト全体の不調」ではなく **Google 検索固有の現象**
- Direct や他流入は安定しているのでサーバー / 表示障害ではない

### デバイス / 国別

- 日本 (jpn): 168 → 50 clicks（ほぼ全量がこれ）
- Mobile: 144 → 39c / Desktop: 24 → 11c
- → 特定デバイスではなく日本 Google 検索全体で起きている

---

## 2. なぜ下がっているか

### 2-1. ページタイプ別の被害分布（サイト全体に均等）

| タイプ | 5/7-5/10 平均 | 5/12-5/14 平均 | クリック変化 |
|---|---|---|---|
| 記事 (`/article/...`) | 111c / 414i | 32c / 239i | **-71%** |
| ジャンル (`/x/y/`) | 58c / 495i | 18c / 204i | **-68%** |
| 女優 (`/x/`) | 1c / 16i | 0c / 4i | -56% |
| トップ | 1c / 7i | 1c / 2i | -33% |

→ **特定ページ種別ではなくサイト全体で均一にダメージ**

### 2-2. 単独で最大寄与しているページ

`/article/goumou-osusume/`（剛毛 AV 女優おすすめ）が単独で -69 クリック /日 を占め、
サイト全体減 -119 クリック /日 の **約 58%** を説明する。

クエリ別の動き:

| Query | clicks (4日) | clicks (3日) | pos before | pos after |
|---|---|---|---|---|
| 剛毛 av女優 | 131 | 7 | 2.4 | 6.7 |
| 剛毛av女優 | 89 | 3 | 2.3 | 6.8 |
| av女優 剛毛 | 59 | 7 | 2.3 | 6.5 |

→ 平均 #2 から #6-7 に押し下げられた典型的な **ランキング降格** パターン。
インデックス削除や表示障害ではない（impressions も 350→63 と減るが消えてはいない）。

### 2-3. 同日にデプロイした自サイトの変更

直近のコミット履歴を時系列で確認:

| 日時 | コミット | 内容 | SEO への影響予測 |
|---|---|---|---|
| 5/8 09:39 | bdc1419 | TOP に preload + fetchpriority 追加 | （即日 revert） |
| 5/8 09:41 | 78bcee5 | 上記を revert | 中立 |
| 5/9 18:11 | 0804549 | 未使用 unpkg (web-vitals/htmx/alpine.js) 削除 | プラス（軽量化） |
| **5/11 23:46** | **f9a6e91** | **記事内部リンクの nofollow 撤去** | **プラス（link equity 流通）** |
| 5/13 12:38 | 6023481 | 女優・ジャンル title に「無料画像・動画付き」追記 | 中立〜プラス |

- 5/11 のコミットは ArticleController.php のみで、**内部リンクを dofollow 化した** 変更（本来は SEO 改善寄りの方向）。落ちる方の変更ではない
- 5/13 の title 変更は **記事ページには影響しない**（女優・ジャンルテンプレのみ）。一方で goumou-osusume は記事なので関係なし

→ **自サイトの変更が今回の急落を直接引き起こした証拠は無い**

### 2-4. 外的要因：信頼できる SEO 観測筋の報告

> ⚠️ 以下は **Google 公式発表ではない**。Google Search Status Dashboard 上、2026-05 に確認済みアルゴリズム更新は **無い**（最終は March 2026 core update / spam update、4/8 完了）。
> 一方で、業界で長年信頼されている観測ソース（Search Engine Roundtable: Barry Schwartz）と複数の順位トラッキングツール（Semrush / Sistrix / Mozcast / Accuranker / Algoroo 等）が、**当サイトの下落と同時期に大幅な順位変動を観測** している。

| 日付 | 観測内容 | ソース |
|---|---|---|
| 2026-05-08 | 複数の rank tracker で大幅変動。Google 未確認更新の可能性 | seroundtable.com「Google Search Ranking Volatility Heating Up May 8th」 |
| 2026-05-13–14 | 再び大幅変動。"deindexing activity"（Googlebot 到達可能だったページがインデックスから消える現象）も同時に報告 | seroundtable.com「Google Search Ranking Volatility Heating Up May 13th & 14th」 |
| 2026-04-23 / 4-27 / 4-28 | 同様の未確認 volatility が断続的に発生 | seroundtable.com |

当サイトの実データとの整合:
- 5/8 → 5/9 は逆に **クリック増加**（178c → 198c）しているが、これは pos 2-3 にいた goumou-osusume が一時的に上昇したため。同記事の position は 5/8 にも 3.2 → 5/10 に 2.2 まで改善（同じ波の中で正にも負にも触れた可能性）
- 5/11 以降のサイト全体・サイト均一な降格、impressions 半減（=Google 表示自体が減っている）、特定ページの順位降格は SE Roundtable 観測パターンと一致

### 2-5. Google 公式が発表している関連アルゴリズム更新（時期は要追加調査）

Google は SafeSearch / explicit content 関連で以下のドキュメント更新を行っている（Search Engine Land / Search Engine Roundtable が報じる Google 公式ドキュメント更新）:

- **explicit videos の crawl 拒否サイトのランキング降格**: Googlebot が動画ファイルにアクセスできない explicit content サイトのランキングを **Video モードで大幅降格** すると明記
- age gate やアクセス制限がある場合、Googlebot リクエストを検証して age gate なしで配信することを推奨
- explicit pages を別ドメイン / サブドメインにグループ化することを推奨

当サイトとの関連性:
- av-hakase.com は明確に explicit content サイト
- /robots.txt は `/src/ /config/ /batch/ /logs/ /cache/ /sql/` を Disallow しているのみで、コンテンツ本文は Googlebot に開放されている
- 動画ファイルは hosting していない（FANZA 商品リンクのみ）
- → 「動画 crawl 拒否ペナルティ」には該当しないはず。ただし explicit content 全般の扱いが厳しくなっている文脈は無視できない

### 2-6. 仮説の整理

| 仮説 | 整合性 | 評価 |
|---|---|---|
| **A. 2026-05-08 / 5-13–14 の未確認 Google 更新（SE Roundtable 報告）** | サイト全体均一に -70%、5/11-5/12 で cliff、業界観測の volatility 時期と一致 | **本命**（ただし Google 公式未確認） |
| B. 5/11 内部リンク nofollow 撤去デプロイで Google 再評価 | 改善方向の変更なので落ちる説明にならない | 低 |
| C. Google explicit content アルゴリズム継続的なチューニング | adult site であることから完全に排除はできないが、特定の trigger 条件（動画 crawl 拒否等）に該当しない | 中（A と併発の可能性） |
| D. 手動ペナルティ | GSC で警告が来ていない前提なら除外（要目視確認） | 低 |
| E. インデックス障害 | impressions がゼロでなく半減、canonical / sitemap / robots.txt 正常 | 否 |

---

## 確認・残タスク

### すぐ確認すべきこと
- [ ] Search Console の「手動による対策」「セキュリティの問題」を目視確認（警告が無いかの最終確認）
- [ ] Search Console の「ページ エクスペリエンス」「Core Web Vitals」レポートで 5/11 前後に異常がないか確認
- [ ] Search Console の「カバレッジ」で 5/8 以降に deindexed ページが増えていないか確認（SE Roundtable が 5/13-14 のタイミングで deindexing 増加を報告しているため）
- [ ] 5/15-5/17 の GSC データが揃ったタイミングで再評価（さらに悪化 / 横ばい / 回復のどれか）

### 中期で打つべき手（仮説 A 前提）
- 一次情報の追加（FANZA API データに独自の解説・比較・体験を上乗せ）
- 「おすすめ N 人」型記事の独自性強化（goumou-osusume の本文を取材ベース / 独自評価軸で再構成）
- 薄いジャンル / 女優ページは noindex 範囲をさらに広げる（既に「作品数 < 3」は noindex 済）
- E-E-A-T を強化する署名 / 著者情報 / 一次ソース引用の整備

### データソース
- **GSC**: 公式 API（`gsc/fetch.py`）、`sc-domain:av-hakase.com`
- **GA4**: Data API（property 529336238）
- **Google 公式アルゴリズム情報**: [Google Search Status Dashboard](https://status.search.google.com/products/rGHU1u87FJnkP6W2GwMi/history) — 2026-05 の確認済み更新なし
- **業界観測（要 Google 公式裏付けなしで参照）**:
  - [SE Roundtable: Google Search Ranking Volatility Heating Up May 8th](https://www.seroundtable.com/google-search-ranking-volatility-heated-41293.html)
  - [SE Roundtable: Google Search Ranking Volatility Heating Up May 13th & 14th](https://www.seroundtable.com/google-search-ranking-volatility-heated-41324.html)
  - [SE Roundtable: April & May 2026 Webmaster Report](https://www.seroundtable.com/april-may-2026-google-webmaster-report-41251.html)
- **Google 公式 explicit content 関連ドキュメント**:
  - [Google Updates Ranking Algorithm For Explicit Content & Videos; Updates SafeSearch Documentation (SE Roundtable 経由)](https://www.seroundtable.com/google-ranking-algorithm-explicit-content-videos-safesearch-39536.html)
