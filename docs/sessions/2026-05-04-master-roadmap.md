# マスターロードマップ — TOP リニューアル & 基盤改善

## 目的

Filmarks 風 TOP リニューアルと、それに付随する基盤改善（画像最適化・キャッシュ・計測・UI ライブラリ導入）を
段階的に実施するための全体計画。各 Phase は**独立してリリース可能**な単位で切る。

**関連ドキュメント**:
- `2026-05-04-filmarks-style-toppage-proposal.md` — TOP デザイン提案
- `2026-05-04-tech-stack-reconsideration.md` — 技術スタック検討（Appendix C/D で環境制約と代替案）
- `2026-05-04-top-route-decision.md` — `/` を HomeController に戻し、`/fc2/` に FC2 ランキングを移設した判断

---

## ステータスサマリ (2026-05-04 時点)

| Phase | 状態 | 備考 |
|-------|------|------|
| Phase 0 — 基盤整備 | ✅ 完了 | Web Vitals / img 属性 / FANZA バリアント / OPcache キャッシュ / HTMX+Alpine |
| Phase 1 — TOP 見た目変更 | ✅ 完了 | ルート切替 (`/` → Home, `/fc2/` 新設) / セクション再構成 / 横スクロール |
| Phase 2 — 行動データ反映 | ✅ 完了 (ローカル) | シグナルテーブル / GA4 集計スクリプト / ③④⑤ セクション。**本番デプロイは未** |
| Phase 3 — パーソナル + 検索 | 未着手 | お気に入り合流 / 検索バー活性化 |
| Phase 4 — 編集部キュレーション | 未着手 | weekly_pick / 今週の1本 |

**直近の本番タスク**:
1. 本ブランチを本番にデプロイ
2. `meikan/sql/migration_signals.sql` を本番 DB に適用 (CREATE TABLE IF NOT EXISTS なので冪等)
3. `pip install pymysql` (未導入なら) — `ga4/aggregate_signals.py` で利用
4. cron に `0 3,15 * * * cd /repo && python ga4/aggregate_signals.py >> ga4/aggregate.log 2>&1` を追加
5. デプロイ後 24〜48時間で実データに置換される（それまでは empty section ガードで非表示）
6. GSC で `/fc2/` の URL 検査 → インデックス登録リクエスト
7. `/` の流入クエリを 1〜2週間モニタリング (FC2 系クエリの順位変動を観察)

---

## 全体像

```
Phase 0  基盤整備                  ← TOP変更前にやる            [完了]
Phase 1  TOP 見た目変更             ← FC2セクション追加・横スクロール  [完了]
Phase 2  行動データ反映              ← ホット/セール/PV急上昇        [完了 (ローカル)]
Phase 3  パーソナル + 検索           ← お気に入り合流・検索バー
Phase 4  編集部キュレーション         ← AV博士の今週の1本
```

各 Phase は前 Phase 完了後に着手するが、Phase 0 と Phase 1 の見た目変更は並行可能。

---

## Phase 0 — 基盤整備（合計 1〜2日）

### 0.1 Web Vitals 計測（✅ 完了 2026-05-04）

`templates/layout.php` に web-vitals@4 を追加。GA4 へ LCP/CLS/INP/FCP/TTFB を送信。

### 0.2 img 属性補完（✅ 完了 2026-05-04）

work-card-v2 / fc2-work-card / other-genres-inline の width/height/decoding を補完。

### 0.3 画像最適化 — FANZA バリアント活用版（要対応）

> ⚠️ images.weserv.nl は FANZA ドメインをブロックするため使用不可。代替策として FANZA 既存サイズ活用。

**作業**:
- `helpers.php` に `fanzaImg($url, $size = 'ps')` を追加
  - `pl.jpg` → `ps.jpg` (中) or `pt.jpg` (小) に置換
- 用途別に使い分け:
  - actress-card (TOP の女優グリッド): `ps`
  - work-card-v2 (作品カード): `pl` 維持（クリック誘発に必要）
  - 横スクロール rail のサムネ: `ps`
  - 関連女優サイドバー: `pt`

**期待効果**: 転送量 5〜10倍削減、LCP 短縮

**所要時間**: 0.5日

### 0.4 キャッシュ強化 — OPcache 配列方式（要対応）

> ⚠️ Shinserver には APCu/Redis が無い。OPcache のみ利用可。

**作業**:
- `Cache.php` に OPcache バックエンド追加（`var_export` + `include` 方式）
- 既存ファイルキャッシュは fallback として残す
- 重いクエリ（`Actress::all()`, `Genre::featured()` 等）から段階的に切替

**期待効果**: クエリ応答速度 5〜10倍

**所要時間**: 0.5日

### 0.5 HTMX + Alpine.js の導入準備（任意・Phase 1 と一緒で可）

`layout.php` に CDN 読み込みを追加するだけ。実利用は Phase 1 以降。

```html
<script defer src="https://unpkg.com/htmx.org@2"></script>
<script defer src="https://unpkg.com/alpinejs@3"></script>
<style>[x-cloak]{display:none!important}</style>
```

---

## Phase 1 — TOP 見た目変更（合計 1〜2日）

### 1.1 セクション構成の入替

`templates/home.php` を再構成。新 IA:

```
① ヒーロー (検索バー + 3タブ ※検索は Phase 2、現時点はアンカージャンプ)
② 🎬 FC2 注目ランキング (week)
③ 🆕 今月デビュー新人 (既存)
④ ジャンル別おすすめ女優 (既存・暫定維持)
⑤ 📚 ジャンルから探す (12タイル)
⑥ 📝 最新コラム記事 (既存)
```

**注**: ⑥ホット作品 / セール / PV急上昇 は Phase 2 で挿入。Phase 1 では既存「ジャンル別おすすめ」を残してプレースホルダ代替。

### 1.2 FC2 セクション追加

`Fc2Work::getRanking('week', 10, 0)` を `HomeController` で呼び、partial で表示。

### 1.3 ジャンルタイル新規

`config/app.php` に `FEATURED_GENRES` 定数で 12 ジャンルを固定。`Genre::findFeatured()` で取得。

### 1.4 横スクロール (CSS Scroll Snap)

```css
.hot-rail { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; gap: 12px; -webkit-overflow-scrolling: touch; }
.hot-rail__card { flex: 0 0 220px; scroll-snap-align: start; }
```

ライブラリ不要。

### 1.5 デプロイ・効果計測

Web Vitals 数値の Before/After を比較。

---

### Phase 1.6 デザイン微修正 (2026-05-04 — モバイルレビュー後)

ユーザーレビュー (`Image #3` モバイルキャプチャ) を踏まえて以下を反映:

- **検索バー撤去** — disabled 状態が「壊れて見える」ため Phase 3 まで非表示
- **FC2 カード「0票」非表示** — 票数 0 のときだけ vote count を隠す
- **モバイル横スクロールバー非表示** — `scrollbar-width: none` + 768px 以上で表示復帰
- **セクション見出しの secondary ラベル化** — 「(週間)」を `.top-section__title-sub` (白半透明バッジ) に
- **「もっと見る」ボタンを Filmarks 風全幅グレーボタン化** — `padding: 14px / background: var(--color-card)` で各セクション末尾に配置

未対応 (subjective なので保留):
- F: ヒーローの「337人」を大きく / アクセント色強調
- G: ヒーロー subtitle 短縮
- H: セクション見出しのピンクベタ背景撤廃 → icon + bold text

### Phase 1 着手記録 (2026-05-04)

**ルート構造変更 (A案採用)**:
- `meikan/index.php`:
  - `/fc2/` → `/` の 301 リダイレクトを撤去
  - `$router->add('', 'HomeController@index')` (旧: Fc2RankingController)
  - `$router->add('fc2/', 'Fc2RankingController@index')` を新設
- `meikan/templates/partials/header.php`: 「魔法の7桁ランキング」リンクを `/` → `/fc2/`
- `meikan/src/controllers/SitemapController.php`: core sitemap に `/fc2/` を追加 (priority 0.9)

**新規ファイル**:
- `meikan/templates/partials/fc2-rail-card.php` — TOP の FC2 横スクロール用コンパクトカード
- `meikan/templates/partials/genre-tile.php` — ジャンルタイル

**モデル / コントローラ**:
- `Genre::findFeatured(int $limit)` — FEATURED_GENRE_SLUGS から DB 存在分のみを順序保持で取得 + 代表サムネ付与
- `HomeController::index()` — fc2Ranking / featuredGenres / actressCount を template に渡す
- `config/app.php` — `FEATURED_GENRE_SLUGS` 定数 (12件) 追加

**テンプレート全面再構成**:
- `meikan/templates/home.php` を Filmarks 風 IA で書き直し
  - ① ヒーロー (タイトル + 3タブ + 検索バー[disabled / Phase 2 で活性化])
  - ② FC2 注目ランキング (週間 TOP10、横スクロール rail)
  - ③ 新人女優グリッド
  - ④ ジャンル別おすすめ女優 (既存 — Phase 2 で行動データ枠と差替)
  - ⑤ ジャンルから探す (12タイル)
  - ⑥ 最新コラム記事

**CSS 追加** (style.css に 312 行追加, 4142 → 4454):
- `.top-section__head` — タイトル + もっと見るリンクの flex 配置
- `.hero / .hero__title / .hero__subtitle / .hero__tabs / .hero__tab / .hero__search`
- `.hot-rail` — CSS Scroll Snap (`scroll-snap-type: x mandatory`)
- `.fc2-rail-card` — 220px 幅、ランクバッジ、サムネ aspect-ratio 16/9
- `.genre-tile-grid / .genre-tile / .genre-tile__overlay` — レスポンシブ 2→3→4→6 列

**動作確認 (localhost:8000)**:
- ✅ `/` 200 OK、新 TOP が描画される
- ✅ `/fc2/` 200 OK、既存 FC2 ランキングが正しく表示
- ✅ ヘッダー「魔法の7桁ランキング」が `/fc2/` を指す
- ✅ PHP エラー 0 件 (dev-server.log 空)
- ✅ フルスクリーンショット保存 (`/tmp/top_after.png`, `/tmp/fc2_after.png`)

**残対応 (Phase 1 完了後の運用考慮)**:
- 本番デプロイ後、GSC で `/fc2/` を URL 検査 → インデックス登録リクエスト
- `/` のクエリ流入を 1〜2週間モニタリング (FC2 系クエリの順位変動を観察)
- 本番ホットコンテンツ計測のため Phase 2 に移行

---

## Phase 2 着手記録 (2026-05-04 完了)

### 新規ファイル
- `meikan/sql/migration_signals.sql` — work_signals / actress_signals テーブル定義
- `meikan/templates/partials/work-rail-card.php` — ホット作品 rail カード (rank badge + サムネ + タイトル + 主演女優)
- `meikan/templates/partials/sale-rail-card.php` — セール rail カード (% OFF バッジ + 元値→セール値 + 残り日数)
- `ga4/aggregate_signals.py` — GA4 集計バッチ (本番 cron 用)
  - fanza_click → work_signals (click_7d / 30d / prev_7d / velocity_score)
  - sessions → actress_signals (sessions_7d / prev_7d / pv_velocity_score)
  - 完了後にローカル cache を全削除

### モデル / コントローラ
- `Work::findHotByVelocity($limit, $minClicks)` — velocity_score 降順、ノイズ除去 (click_7d しきい値)、フォールバック (click_7d 単純降順)、主演女優を `lead_actress` で付与
- `Work::findOnSale($limit)` — `sale_end_at > NOW() AND list_price > price`、割引率降順
- `Actress::findHotByPv($limit, $minSessions)` — pv_velocity_score 降順、サムネ必須、フォールバック
- `HomeController::index()` — 旧 GENRE_SECTIONS (ジャンル別おすすめ女優) を撤去、③④⑤ を新規取得
- 新人女優の月選択を**「先月 → DB 最新月」フォールバック**に変更（ローカル/本番でデータ揃いに差があっても動く）

### テンプレート再構成
- `meikan/templates/home.php` — 最終 IA に到達:
  ```
  ① ヒーロー
  ② FC2 注目ランキング (週間)
  ③ 今週ホットな作品          ← Phase 2 新規
  ④ FANZA セール中             ← Phase 2 新規
  ⑤ PV急上昇 女優              ← Phase 2 新規
  ⑥ デビュー新人女優
  ⑦ ジャンルから探す (12タイル)
  ⑧ 最新コラム記事
  ```
- 旧「痴女・巨乳ジャンルおすすめ女優」セクションは撤去 (Phase 2 で削除予定通り)

### CSS 追加
- `style.css` +166 行 (`.work-rail-card / .sale-rail-card`、ランクバッジ、% OFF バッジ、price strikethrough、残り日数表示)

### ローカル検証
- ローカル DB に migration_signals.sql 適用 (work_signals / actress_signals 作成)
- ダミーシードデータ投入:
  - work_signals: 30 行 (review_count 上位の works から)
  - actress_signals: 20 行 (サムネあり女優からランダム)
  - works.sale_end_at: 10 行に未来日 + price 値引きを設定
- ブラウザで確認:
  - ✅ 全セクション (①〜⑧) が描画される
  - ✅ FC2 / ホット / セール の3つの横スクロール rail が動作
  - ✅ % OFF バッジ + 元値strikethrough + 残り日数が正しく表示
  - ✅ 新人女優は 2026-03 (DB 最新月) に自動フォールバック
  - ✅ Web Vitals 計測継続中

### 本番展開メモ
1. `meikan/sql/migration_signals.sql` を本番 DB に適用 (CREATE TABLE IF NOT EXISTS なので冪等)
2. `pip install pymysql` (もし未導入なら) — `ga4/aggregate_signals.py` で利用
3. cron に追加（ローカル時刻 03:00 / 15:00 推奨）:
   ```
   0 3,15 * * * cd /path/to/repo && python ga4/aggregate_signals.py >> ga4/aggregate.log 2>&1
   ```
4. `fetch_fanza.php` は既に `sale_end_at / campaign_title` を更新する実装あり → 既存 cron で OK
5. デプロイ後 24〜48時間で実データに置換される（それまでは empty section ガードで非表示）

---

## Phase 2 — 行動データ反映（合計 3〜5日）

### 2.1 シグナルテーブル新規

```sql
CREATE TABLE work_signals (...);
CREATE TABLE actress_signals (
  ...,
  sessions_7d INT,
  sessions_prev_7d INT,
  pv_velocity_score DECIMAL(8,4),
  ...
);
```

### 2.2 GA4 集計バッチ

`ga4/aggregate_signals.py` 新規:
- `fanza_click` 集計 → `work_signals.click_7d / click_30d / velocity_score`
- `sessions` 集計 → `actress_signals.sessions_7d / sessions_prev_7d / pv_velocity_score`

cron: 1日2回 (03:00 / 15:00)

### 2.3 FANZA セール取得バッチ

`batch/fetch_fanza_sale.php` 新規。FANZA API の `campaign` フィールドから割引対象を抽出して `work_signals.is_on_sale / sale_price / sale_ends_at` 更新。

cron: 1日1回 (04:00)

### 2.4 TOP に挿入

```
② 🎬 FC2 注目ランキング (Phase 1 で導入済)
③ 🔥 今週ホットな作品 (新規)
④ 💰 FANZA セール中 (新規)
⑤ ⭐ PV急上昇 女優 TOP6 (新規)
⑥ 🆕 今月デビュー新人
⑦ 📚 ジャンルから探す
⑧ 📝 最新コラム記事
```

→ ここで「ジャンル別おすすめ女優」(暫定セクション) は撤去。

### 2.5 ヒーロー検索の HTMX 実装

```html
<input hx-get="/api/search-suggest" hx-trigger="keyup changed delay:300ms" hx-target="#suggest">
<div id="suggest"></div>
```

`/api/search-suggest` は最大 5 件のサジェストを HTML partial で返す。

---

## Phase 3 — パーソナル + 検索（合計 5〜7日）

### 3.1 検索ページ実装

- ルート追加: `/search/?q=xxx&type=work|actress|genre`
- `SearchController@index` 新規
- MySQL FULLTEXT インデックス追加（works.title, actresses.name）

### 3.2 お気に入りセクション (⑨) を home.php に統合

localStorage の favorites を Alpine.js で読み出し → 0件なら非表示。

```html
<section x-data="favoritesSection()" x-show="hasFavorites" x-cloak>
  ...
</section>
```

### 3.3 favorites.js を Alpine.js 化リファクタ

480行 → 100行台へ圧縮。クリック二重発火に注意して段階的に。

### 3.4 PV急上昇 → PV × fav 合成スコアに切替

`actress_signals.fav_7d` がデータ充実してきたら、⑤ のロジックを合成スコアに変更。

### 3.5 「あなたが見た作品から」レコメンド

localStorage に閲覧履歴を保存 → 同ジャンル他作品をレコメンド。

---

## Phase 4 — 編集部キュレーション（合計 2〜3日）

### 4.1 weekly_pick テーブル

```sql
CREATE TABLE weekly_pick (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('work','actress','article'),
  target_id VARCHAR(64),
  comment TEXT,            -- AV博士コメント
  picked_for_week DATE,    -- 週の月曜日
  created_at DATETIME
);
```

### 4.2 ピック登録 UI（管理者用）

簡易 PHP ページ or 既存 batch スクリプトで JSON ロード方式。

### 4.3 TOP 表示

```
ヒーロー直下に「今週のAV博士ピック」枠を1つ追加
```

---

## Phase 5+ — 余力で実施（参考）

- 自前 PHP 画像プロキシ (`/img.php`) で WebP 化（Phase 0.3 の発展）
- MySQL → SQLite キャッシュ（読み込み頻度の高い集計）
- A/B テスト基盤（GA4 + クエリパラメータベースの簡易実装）
- AMP / RSS 対応

---

## ガントチャート（暦想定）

```
              5月  6月  7月
Phase 0       ██
Phase 1       ░██
Phase 2          ████
Phase 3              █████
Phase 4                   ███
Phase 5+                      ...
```

`█` = 実作業、`░` = 並行作業可

---

## リリース戦略

各 Phase 完了時に:

1. ローカルで `php -S localhost:8000 -t meikan meikan/dev-server.php` で動作確認
2. Web Vitals (LCP/CLS/INP) が劣化していないか確認
3. `git commit` → `rsync` で Shinserver にデプロイ
4. デプロイ後 GA4 / GSC を 3 日監視 → 主要指標に異常なければ次 Phase へ

---

## 確認・残タスク

1. **Phase 0.3 の方針** — FANZA バリアント活用版（`pl→ps/pt`）で進めて良いか
2. **Phase 0.4 の方針** — OPcache 配列キャッシュを導入して良いか
3. **HTMX + Alpine.js を Phase 0.5 で先行投入するか**（推奨: Yes）
4. **Phase 1 着手のタイミング** — Phase 0 完了後すぐ / 数日空ける
5. **将来的に WebP 必須なら Phase 5+ で自前プロキシ実装** — 優先度の判断