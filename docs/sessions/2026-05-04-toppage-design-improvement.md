# TOPページ デザイン改善案 — Phase 2 完了後の次の一手

## 依頼

`docs/sessions/2026-05-04-filmarks-style-toppage-proposal.md` を踏まえ、Phase 0+1+2 が完了した
現在の TOP（HomeController + home.php + style.css）のデザイン改善案を考える。
関連スキル（ui-designer / page-cro / frontend-design / baseline-ui / accessibility-engineer / motion-design）
の観点もミックスする。

---

## 1. 現状の到達点（Phase 0+1+2 で実装済み）

```
① ヒーロー（navy gradient + 3タブ アンカージャンプ + 検索バーUIなし）
② 🎬 FC2 注目ランキング（横スクロール rail / week TOP10）
③ 🔥 今週ホットな作品（fanza_click 急上昇 / rail 10件）
④ 💰 FANZA セール中（rail 10件・元値打消 + 残り日数）
⑤ ⭐ PV急上昇 女優（3列グリッド 6件）
⑥ 🆕 今月デビュー新人（3列グリッド 6件 + コラム導線）
⑦ 📚 ジャンルから探す（2-6列タイル 12件）
⑧ 📝 最新コラム記事（縦リスト 5件）
```

**デザイントークン**（`meikan/public/css/style.css:6-22`）
- `--color-bg: #FFFFFF` / `--color-card: #F5F5F7` / `--color-accent: #e91e8c`
- `--radius: 2px`（極端にカクカク）
- セクション見出し：`background: #e91e8c; color:#fff; font-size:14px; padding:5px 10px`
  → "ピンク帯のラベル" が Filmarks より旧 WordPress ウィジェット感
- カード幅：rail カードはすべて 220px 固定、aspect 220/148

機能的には Phase 1 ロードマップを満たしているが、**視覚言語が "データベースサイト" に寄りすぎ**で、
Filmarks の「映画を主役にする編集メディア感」とギャップがある。

---

## 2. 観察された課題（スキル横断レビュー）

### 2.1 ui-designer 観点（情報設計 / 視覚階層）

- **セクションの "重み" がフラット**：FC2/ホット/セール/PV/新人/ジャンル/コラム すべてが
  同じピンク帯 + 同じ rail/grid 幅で並んでおり、優先度の差が読み取れない。
- **「買う系」と「探す系」が混在**：②③④（外部送客=売上直結）と ⑤⑥⑦（内部回遊）が交互で、
  どこを読めばよいかの導線がない。CRO 上は「買う系」を上、「探す系」を中、「読む系（コラム）」を下、
  という3層構造に並べ替えたい。
- **ヒーロー3タブの実態が anchor jump**：`role="tab"` を付けつつ実装は `<a href="#hot-works">` 等。
  視覚も期待値も「絞り込みタブ」だが挙動はジャンプ。Phase 3 で `/search/` を実装するまでの
  「壊れたタブ」状態は CRO とアクセシビリティ両方でマイナス。

### 2.2 page-cro 観点（コンバージョン）

- **Above the fold が "宣言文 + 装飾タブ" だけ**：ヒーローの下半分で離脱されると CV ゼロ。
  ヒーロー直下に「今この瞬間ホットな1本」をスポットライト表示するのが効く。
- **セールセクションに緊急性が弱い**：残り日数表示はあるが、`残り X 時間` のカウントダウン、
  `〜まで限定` の赤ピル、`残り X 件しか取得できなかった` の希少性が無い。
- **rail が静的**：横スクロール示唆（右端グラデ・矢印）が無いため、PC ユーザーは横にコンテンツが
  あることに気づかず素通りする可能性。Filmarks も右端グラデで「まだ続く」を示唆している。
- **クリック計測の網羅性**：ホット作品 rail には `data-fanza-link-type="hot_rail"` が
  入っているが（`work-rail-card.php:12`）、セール rail / FC2 rail にも同種の属性を入れて
  どの rail が稼ぐかを GA4 で比較したい。

### 2.3 frontend-design / baseline-ui 観点（AI-slop 回避）

- **絵文字頻出見出し** 🎬🔥💰⭐🆕📚📝 が AI-slop シグナルの典型。Filmarks 本家は
  「カッコ書きラベル + 太字日本語」で構成され、絵文字を使っていない。差別化のため
  **絵文字を全廃 → SVG アイコン or タイポグラフィ主導見出し**へ。
- **`--radius: 2px` × ピンク帯ヘッダー**の組み合わせが「2010 年代 WordPress テーマ」感。
  見出しの背景色を撤去し、`font-size:18px / weight:800 / 左 4px のアクセントバー` に。
- **カードがすべて 220×148 + radius 4px** で揃っているため、視覚的リズムが無く
  「データベース一覧」感が出る。`hot-works` と `sale-works` だけでも幅違い・aspect 違いの
  バリアントを持たせたい（例：①位だけ 320px の "spotlight" カード）。
- **アクセントカラー単色**：すべてピンク `#e91e8c` で塗っているため、「ホット」「セール」「新人」が
  視覚的に区別できない。コンテンツタイプ別カラーパレットを用意（hot=red、sale=amber、new=blue、
  pv=violet、fc2=teal）し、見出しのアクセントバー / バッジに展開する。

### 2.4 accessibility-engineer 観点

- **`<a role="tab">` は不正な ARIA**（`home.php:9-17`）：tab には `role="tab"` + `aria-selected` +
  対応する `role="tabpanel"` が必要。実態が anchor jump ならば `role` は外し、
  単に「セクション内ジャンプ」に降格するか、Phase 3 で本物のタブに昇格する。
- **絵文字単独のラベル**は読み上げで「炎、絵文字」等が読まれて雑音化。
  各見出しに テキスト「人気作品」など意味的キーワードを必ず併記する（現状は「🔥 今週ホットな作品」で
  テキストはあるので OK だが、絵文字を SVG 化するなら `aria-hidden="true"` を付ける）。
- **rail の横スクロール領域に `role="region"` + `aria-label`** を付けてランドマーク化。
- **コントラスト**：`hero__subtitle` の `rgba(255,255,255,0.85)` on `#14213d` は WCAG AA を
  ぎりぎりクリアしているが `0.85→0.92` に上げて余裕を持たせる。

### 2.5 motion-design 観点

- 全カード hover で `translateY(-2px)` + 0.12s — 統一されているのは良いが、
  **タップフィードバックが無い**。モバイルでは hover が効かないため、`:active` 時の
  `scale(0.98)` を 80ms で入れると押した感が出る。
- **rail の慣性スクロール終端ヒント**：右端 24px に linear-gradient で
  ホワイトフェードを置き「まだ続く」のシグナル。
- **セールバッジに微 pulse**：`@keyframes sale-pulse` で 2s 周期の opacity 0.8↔1.0 を入れると
  セール感が出る（過剰演出にならないよう 1 サイクルだけのアテンション or initial paint で 1 回だけ）。

### 2.6 SEO / コンテンツ観点

- **本文テキストがほぼゼロ**：H1（site name）+ subtitle 1行 + セクション見出しのみ。
  Google にとって TOP は「サイトの主題」を読み取る重要ページ。⑦ジャンルタイル下に
  「AV博士について」150〜250字 の リード文 + 月次更新統計（"今月新規取り込み {N} 作品 / {N} 名追加"）
  のショウケースを置きたい。
- **見出し階層**：現状すべて `<h2>`。本来 hero が `<h1>`、セクションは `<h2>`、サブは `<h3>` で
  整理すべき（home.php は概ね合っているが要確認）。

---

## 3. 改善案 — 3 レベルで提示

### A. Quick wins（0.5〜1 日 / DB 変更なし）

| # | 改善 | ファイル | 期待効果 |
|---|------|---------|---------|
| A1 | セクション見出しのピンク帯を撤去し、左 4px アクセントバー + 18px 太字に | `style.css:242-250` | 視覚 AI-slop 解消、ブランド昇格 |
| A2 | rail 右端 24px にグラデ（`mask-image` か `::after`）で「続き有り」を視覚化 | `style.css:.hot-rail` | 横スクロール認知率 ↑ |
| A3 | カード `:active { transform: scale(0.98) }` 80ms をモバイル限定で追加 | rail/tile 共通 | タップ感、誤タップ気づき ↑ |
| A4 | セールバッジに `@keyframes sale-pulse`（初回 1 周期のみ） | `style.css:.sale-rail-card__badge` | セール訴求 ↑ |
| A5 | 見出しの絵文字を `<svg aria-hidden>` 化 or 削除（「🔥」を `<span class="badge--hot">HOT</span>` に） | `home.php` 全見出し | AI-slop 解消、a11y ↑ |
| A6 | ヒーロー3タブの `role="tab"` を削除し、見た目だけのラベル化（実装と表示の整合） | `home.php:8-18` | a11y ↑、誤解防止 |
| A7 | sale-rail / fc2-rail にも `data-fanza-link-type="sale_rail"` `="fc2_rail"` を追加 | 各 rail-card partial | A/B 計測の粒度 ↑ |
| A8 | ヒーロー subtitle のコントラスト `0.85→0.92` | `style.css:4171` | a11y ↑ |
| A9 | TOP 末尾（⑧の下）に 150〜250字の "AV博士について" リード文 + 統計ハイライト | `home.php` 末尾 | SEO TF-IDF / トピック性 ↑ |

### B. ビジュアルリフレッシュ（2〜3 日 / デザインシステム改修）

#### B1. アクセントパレット拡張

```css
/* 既存 */
--color-accent: #e91e8c;        /* メインピンク（CTA, ロゴ） */

/* 新規（コンテンツタイプ別） */
--accent-hot: #ef4444;          /* ホット作品（赤） */
--accent-sale: #f59e0b;         /* セール（琥珀） */
--accent-new: #3b82f6;          /* 新人（青） */
--accent-pv: #8b5cf6;           /* PV急上昇（紫） */
--accent-fc2: #14b8a6;          /* FC2（ティール） */
--accent-genre: #6b7280;        /* ジャンル（中性グレー） */
```

各セクションのアクセントバーとカードバッジに展開。タイトル文字色は `--color-text` のまま、
カラーは"アクセントバー / 数字 / バッジ"のみに使うことで品位を保つ。

#### B2. 見出しコンポーネント刷新

```html
<!-- BEFORE -->
<h2 class="top-section__title">🔥 今週ホットな作品</h2>

<!-- AFTER -->
<h2 class="section-head section-head--hot">
  <span class="section-head__bar" aria-hidden="true"></span>
  <span class="section-head__label">人気作品</span>
  <span class="section-head__sub">今週のホット急上昇 TOP10</span>
</h2>
```

```css
.section-head { display:flex; align-items:baseline; gap:10px; padding:0; background:transparent; color:var(--color-text); font-size:18px; font-weight:800; }
.section-head__bar { display:inline-block; width:4px; height:18px; background:var(--accent-hot); border-radius:2px; }
.section-head--hot .section-head__bar { background: var(--accent-hot); }
.section-head--sale .section-head__bar { background: var(--accent-sale); }
.section-head__sub { font-size:12px; font-weight:500; color:var(--color-text-sub); }
```

#### B3. Spotlight + Rail のハイブリッド構造（③④に適用）

①位を 320×214 の大カード（タイトル全表示・女優名・「+クリック数」）、
②③位を中カード 220×148、④以降を小カード 160×108 にして"視覚リズム"を作る。

```html
<ol class="hot-rail hot-rail--mixed">
  <li class="hot-rail__item hot-rail__item--xl">…①位</li>
  <li class="hot-rail__item hot-rail__item--m">…②位</li>
  <li class="hot-rail__item hot-rail__item--m">…③位</li>
  <li class="hot-rail__item hot-rail__item--s">…④位</li>
  …
</ol>
```

`flex: 0 0 320px` / `220px` / `160px` を CSS のみで切り替え。新しい `xl/m/s` バリアントを
`work-rail-card` に追加（背景画像の `object-fit: cover` でレイアウト破綻なし）。

#### B4. ヒーロー再構築

「装飾タブ + 何も無い」状態から、**「今日の1本」スポットライト**入りに。

```
┌─────────────────────────────────────────┐
│  [小ロゴ] AV博士                          │
│  人気AV女優 1,200人の作品をジャンルで探す │
│                                          │
│  ┌────────┐ 今日のピックアップ           │
│  │  jacket │ 〇〇〇〇 (女優名)            │
│  │  画像   │ 「タイトル」                 │
│  └────────┘ [詳細を見る →]               │
│                                          │
│  作品 / 女優 / ジャンルから探す          │
│  [作品検索 ▾] [女優検索 ▾] [ジャンル一覧] │
└─────────────────────────────────────────┘
```

「今日のピックアップ」は ③ホット作品の①位を流用（追加クエリゼロ）。
タブを"絞り込み" 期待にせず、**3つの探索カテゴリへの導線（リンク3本）** に降格させる。
これで Phase 3 の本物検索が来るまで「壊れたタブ」を晒さずに済む。

### C. UX / CRO 改善（3〜5 日 / 構造変更含む）

#### C1. セクション順の再編（Buy → Browse → Read の3層）

```
① Hero（todays pick + 探索リンク3本）
─── BUY 層 ─────────────────────────
② FC2 注目（既存維持）
③ 今週ホット（既存・spotlight 化）
④ FANZA セール（既存・カウントダウン強化）
─── BROWSE 層 ──────────────────────
⑤ PV急上昇 女優（既存）
⑥ 今月デビュー新人（既存）
⑦ ジャンルから探す（既存）
─── READ 層 ───────────────────────
⑧ 最新コラム記事（既存）
⑨ AV博士について + 統計ハイライト（新規）
```

3 層の境目に `<hr class="layer-divider">` 風の薄い区切り（h:1px, color:rgba(0,0,0,0.06)）を置き、
ユーザーに「ここから探す系」「ここから読む系」と心理的なギアチェンジを与える。

#### C2. セールセクション強化

- バッジ：`SALE` 単独 → `SALE -40%` のように割引率併記
- カードに `残り 3日 12時間` の二段階表示（JS で1分毎に更新するか、毎時 SSR で十分）
- セール終了済み（`sale_ends_at < NOW()`）を必ず除外
- カード末尾に小さく `今すぐFANZAで見る →` の擬似 CTA テキスト追加（クリッカブル領域はカード全体）

#### C3. ヒーロー検索の段階的活性化

Phase 3 で `/search/?q=&type=` を実装するまでの中継として：

- 検索 input は表示するが、`form action="https://www.google.com/search"` + `hidden q="site:av-hakase.com"` に
  ハック。Google サイトサーチで実用的に動く（多くの個人サイトが採用するパターン）。
- Phase 3 で実装したら action を `/search/` に切替。HTML はそのまま。

#### C4. パーソナライズ Lite（localStorage 活用）

`favorites.js` を拡張して "閲覧履歴" も保存（女優ページ訪問時に slug を最大 12 件保存）。
TOP に `<section id="recent-visits">` プレースホルダ + JS 描画。0件なら `display:none`。

```js
const recents = JSON.parse(localStorage.getItem('av_hakase_recents') || '[]');
if (recents.length) {
  // /api/actresses?slugs=... で軽く取得 or 最初の訪問時にカード HTML をキャッシュ
  renderRecentSection(recents);
}
```

これだけで「あなたが見た女優」枠が完成し、再訪率の体感が上がる。

#### C5. クロール最適化

- `⑦ジャンルから探す` の 12 件は手動キュレ（`config/featured_genres.php`）の上、
  `⑨ もっとジャンルを見る` リンクで全ジャンル一覧へ流す（孤立ページ削減）。
- `⑥ 今月デビュー新人` 内の女優カードに `/{slug}/` リンクが既にある（`actress-card.php:1`）が、
  下部の「もっと見る」を `/article/shinjin-av-{YYYY-MM}/` に確実に張り、月次デビュー記事への
  内部リンクを TOP から保証する（既に `home.php:88-89` で実装済み・OK）。

---

## 4. デザイントークンの再定義案（B フェーズで導入）

```css
:root {
  /* 既存維持 */
  --color-bg: #FFFFFF;
  --color-card: #F5F5F7;
  --color-text: #1A1A1E;
  --color-text-sub: #6B6B70;
  --color-border: #e5e5e7;
  --color-accent: #e91e8c;
  --color-accent-hover: #c4177a;
  --color-danger: #d72121;

  /* 新規 — コンテンツタイプ別アクセント */
  --accent-hot: #ef4444;
  --accent-sale: #f59e0b;
  --accent-new: #3b82f6;
  --accent-pv: #8b5cf6;
  --accent-fc2: #14b8a6;

  /* 新規 — タイポ階層 */
  --fs-section: 18px;       /* 現 14px から */
  --fs-section-sub: 12px;
  --fs-card-title: 13px;
  --fs-card-meta: 11px;

  /* 既存 radius を増やす（カクカク → 角を僅かに丸める） */
  --radius: 4px;            /* 現 2px から */
  --radius-card: 6px;       /* カードのみ少し丸く */
  --radius-pill: 999px;
}
```

`--radius: 2px → 4px` だけで全体の"角ばった感"が和らぐ。`--radius-card: 6px` を rail カード/
genre tile に当てると Filmarks の "ポスター感" に近づく。

---

## 5. セクションごとの "1 個だけ変える" 優先案

データ・実装コストが軽く、視覚インパクトが大きい順に並べる：

1. **セクション見出しのピンク帯撤去**（A1）→ 1ファイル 30行で完了。即「ブランド感」が上がる
2. **絵文字 → SVG / 完全撤去**（A5）→ AI-slop 即解消
3. **rail 右端グラデ**（A2）→ 横スクロール認知率
4. **ヒーロー Today's Pick 化**（B4）→ 上方視認率と CTR
5. **ホット作品の Spotlight 化**（B3）→ 視覚リズム + ①位 CTR の集中
6. **セクション順 BUY/BROWSE/READ 3層化**（C1）→ 直帰率改善
7. **ジャンル別アクセントカラー**（B1）→ 区別性
8. **パーソナライズ Lite**（C4）→ 再訪率
9. **本物検索 or Google サイトサーチ**（C3）→ 検索 UI の機能化

---

## 6. アクセシビリティ修正リスト（A フェーズに含めて確実に）

- [ ] `home.php:8-17` の `role="tab"` を削除（または完全な tablist 実装に昇格）
- [ ] 各セクションを `<section aria-labelledby="…">` で囲み、ID を見出しに付与
- [ ] rail を `<div role="region" aria-label="人気作品の横スクロール">` でラップ
- [ ] 装飾絵文字を SVG 化する場合は `aria-hidden="true"` 必須
- [ ] `hero__subtitle` のコントラスト引き上げ
- [ ] フォーカスリング：rail カード `:focus-visible` で 2px outline accent 色を明示

---

## 7. 計測（A/B 案）

PHP サイトなので Edge Config 不可。簡易 A/B は次の2案：

- **案 1（推奨）**：URL クエリ `?v=b` でテンプレートを切替（HomeController で参照）。
  GA4 のページパスに反映される。50/50 を JS で `?v=` 付きリンクへ書き換えるだけ。
- **案 2**：時間帯 A/B（偶数日 = 旧、奇数日 = 新）。実装は超軽量だが解釈が難しい。

KPI（既存 `ga4/daily_report.py` 拡張で集計可能）：
- TOP からの `fanza_click` 数 / TOP セッション
- TOP 直帰率
- セクション別クリック分布（`data-fanza-link-type` 別）

---

## 8. 関連スキルの活用ポイント

| スキル | 使うフェーズ | 何をやらせるか |
|--------|------------|---------------|
| `ui-designer` | B フェーズ前 | 見出しコンポーネント・カードバリアント・レイアウトの IA 整理 |
| `frontend-design` | B〜C | 「Today's Pick」ヒーローと Spotlight カードの実装ガイド |
| `baseline-ui` | レビュー時 | 絵文字・ピンク帯・generic radius を AI-slop として検出させる |
| `accessibility-engineer` | A フェーズ | tab / region / 見出し階層の自動レビュー |
| `motion-design` | A〜B | rail グラデ / sale-pulse / tap feedback の細部チューニング |
| `page-cro` | C フェーズ | セクション順序 と 検索/CTA の効果検証設計 |
| `web-design-guidelines` | リリース前 | 全体監査（コントラスト / フォーカス / セマンティクス） |
| `webapp-testing` | リリース前 | 実機 Playwright で hero/rail/grid のスクショ取得・回帰確認 |

---

## 9. 推奨進め方（提案）

```
Day 1   A1〜A9 まとめて投入（見出し刷新・絵文字撤去・rail グラデ・a11y修正・SEO本文）
        → ここで体感が大きく変わる。/?v=a で旧、デフォで新を出して GA4 で2週間観察
Day 2-3 B1〜B4 のデザイントークン再定義 + 見出しコンポーネント + ヒーロー Today's Pick
Day 4-5 C1〜C4（順序入替・セールカウントダウン・パーソナライズ Lite）
Day 6+  Phase 3 検索実装（既存提案 docs に従う）
```

---

## 確認・残タスク

- どこから着手するか（提案：まず Day 1 の A1〜A9 を一気に。リスク低・効果高）
- B1 のアクセントパレット導入時、ピンク `#e91e8c` をブランドメインとして残すか
  （CTA・ロゴだけに残し、見出しからは外すのを推奨）
- ヒーロー検索（C3）を Google サイトサーチ中継で動かしてよいか（ユーザー判断要）
- 絵文字 → SVG 化の素材：Lucide icons（MIT）を SVG インラインで埋め込みが軽量で推奨

---

## 更新履歴

- 2026-05-04 初版作成（Phase 0+1+2 完了直後の改善案）
