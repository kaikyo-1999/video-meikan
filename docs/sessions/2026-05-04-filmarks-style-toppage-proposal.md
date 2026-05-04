# Filmarks 風トップページ案 — av-hakase.com

## 依頼

Filmarks (https://filmarks.com/) のような「作品 / 女優 / ジャンル」のショートカット導線を持ち、
アフィリンククリックやお気に入りデータを元にしたホットコンテンツのレコメンドや、
セール作品紹介を行うトップページを作りたい。案を作って欲しい。

---

## 1. Filmarks のトップ構造を分解（参考にすべき要素）

Filmarks の TOP は「初見ユーザー」「ヘビーユーザー」「セール狙い」を1ページで満たす設計になっている。
構造を分解すると以下の 6 ブロックに整理できる。

| # | ブロック | 役割 | av-hakase での置き換え案 |
|---|---------|------|------------------------|
| A | 巨大ヒーロー検索 + 3カテゴリタブ（映画/ドラマ/アニメ） | 「探す入口」を最上段で明示 | **作品 / 女優 / ジャンル** の3タブ + 検索 |
| B | 注目の話題作（横スクロール） | "今ホットな作品" を視覚で訴求 | **今週ホットな作品**（fanza_click 急上昇） |
| C | 上映中・配信中（セール/新着） | 売上に直結する CTA | **FANZA セール中の作品**（API のセール属性） |
| D | みんなの評価ランキング | 行動データ由来の信頼感 | **お気に入り急上昇女優 / 作品** |
| E | 特集・コラム | エディトリアルで深堀り | **最新コラム記事**（既存 articles） |
| F | パーソナル（マイ評価/Watchlist） | リピート訪問の理由 | **あなたのお気に入り**（localStorage 反映） |

Filmarks の強みは「**行動データ起点のレコメンド** × **エディトリアルな特集**」のハイブリッドで
"今このサイトに来る理由" を毎日更新している点。これを av-hakase でも再現する。

---

## 2. 現状の TOP との差分（gap analysis）

現 `HomeController` の構造（`meikan/src/controllers/HomeController.php:6-78`）:

- `/` → 新人女優 (n-1月) + ジャンル別おすすめ (痴女・巨乳) + 最新記事5件
- `/meikan/` → 女優一覧 60件ずつページネーション

**足りないもの**:

1. **作品単体への導線がトップに無い**（女優ページからしか作品に辿り着けない）
2. **「今売れている / 話題」の動的シグナルが無い**（毎日同じ画面）
3. **セール訴求が無い**（FANZA セール期は機会損失）
4. **お気に入り(localStorage)を活用したパーソナライズが無い**
5. **3カテゴリ（作品/女優/ジャンル）を横断的に探す導線が無い**

既に揃っているデータ資産:

- ✅ `fanza_click` イベント（GA4） — `data-fanza-cid` 単位のクリック数
- ✅ `favorite_added` / `favorite_removed` 系イベント（`favorites.js:115`）
- ✅ `actresses` / `works` / `genres` テーブル + junction
- ✅ FANZA API（セール属性 `campaign` / `price` 取得可能）
- ✅ `cache/` ファイルキャッシュ機構

→ **データは全部揃っているので、集計バッチ + UI 構築で完結する。**

---

## 3. 新トップページ IA（情報設計）

### 3.1 セクション構成（上から順）

```
┌──────────────────────────────────────────────┐
│ ① ヒーロー: 検索バー + 3タブ                  │
│   [作品] [女優] [ジャンル]  🔍               │
│   AV女優 1,200人・作品 35,000本を網羅         │
├──────────────────────────────────────────────┤
│ ② 🎬 FC2 注目ランキング（横スクロール 10件）   │
│   ← Fc2Work::getRanking('week') を流用        │
│   [サムネ] [タイトル] [👍 投票数] [→詳細]      │
├──────────────────────────────────────────────┤
│ ③ 🔥 今週ホットな作品 (横スクロール 10件)      │
│   ← fanza_click 直近7日 急上昇順              │
│   [サムネ] [タイトル] [女優名] [→詳細]         │
├──────────────────────────────────────────────┤
│ ④ 💰 FANZA セール中（横スクロール 10件）       │
│   ← FANZA API の campaign / 割引率            │
│   [サムネ] [元値→セール値] [残り日数]           │
├──────────────────────────────────────────────┤
│ ⑤ ⭐ PV急上昇 女優 TOP6                         │
│   ← GA4 sessions 直近7日 vs 前週 の伸び率     │
│   ※ Phase 3 でお気に入り急上昇に切替可        │
├──────────────────────────────────────────────┤
│ ⑥ 🆕 今月デビュー新人 (既存)                   │
├──────────────────────────────────────────────┤
│ ⑦ 📚 ジャンルから探す（タイル12個）            │
│   巨乳 / 痴女 / 素人 / 熟女 / 美少女 / ...    │
├──────────────────────────────────────────────┤
│ ⑧ 📝 最新コラム記事 (既存5件)                  │
├──────────────────────────────────────────────┤
│ ⑨ 💖 あなたのお気に入り (localStorage 有時のみ) │
│   ← JS で動的描画。0件なら非表示               │
└──────────────────────────────────────────────┘
```

**②に FC2 を入れた狙い**:
- 既存の `Fc2RankingController` / `Fc2Work::getRanking()` がユーザー投票データを既に蓄積している（GA4 等の外部依存なし）
- FANZA 一辺倒のラインナップに**別ジャンル感（個人系）を最上段で出す**ことで離脱抑制
- `/fc2/` への内部リンクが TOP から張れる（現状は導線が薄い）

**⑤を「お気に入り急上昇」から「PV急上昇」に変更した理由**:
- お気に入りデータは現状件数が少なく、TOP に出すには母数不足（ランキングがブレる/空になる）
- GA4 の `sessions` は既に毎日蓄積されており、`ga4/daily_report.py` で女優ページ単位の集計実績あり
- 「行動データ起点で女優を推す」枠の意義は維持しつつ、データソースだけ差し替える
- お気に入りが貯まったら Phase 3 でロジックを置換 or ハイブリッド化（fav × pv の合成スコア）

### 3.2 モバイル優先設計

- ヒーロータブはモバイルでも横並び3つ（Filmarks と同じ）
- ②③④は**横スクロール（snap-x）**で1画面に1.2枚見える幅 → スワイプ誘発
- ⑥のジャンルタイルは **2列 × 6行** で fold 内に収める

### 3.3 デザインメタファ

Filmarks の良さは「映画ポスターを大胆に並べる」こと。
av-hakase でも **作品ジャケット（FANZA 画像）を主役**にする。
現在の TOP は「女優サムネ中心」だが、新 TOP は **作品サムネ : 女優サムネ = 6 : 4** に。

---

## 4. データレイヤ設計

新規に必要なテーブル / 集計ジョブ:

### 4.1 `work_signals` テーブル（新規）

```sql
CREATE TABLE work_signals (
  cid VARCHAR(64) PRIMARY KEY,
  click_7d INT NOT NULL DEFAULT 0,        -- 直近7日 fanza_click
  click_30d INT NOT NULL DEFAULT 0,
  fav_7d INT NOT NULL DEFAULT 0,           -- 直近7日 favorite_added (work)
  velocity_score DECIMAL(8,4),             -- 急上昇スコア (7d / 30d 比)
  is_on_sale TINYINT(1) DEFAULT 0,
  sale_price INT NULL,
  list_price INT NULL,
  sale_ends_at DATETIME NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_velocity (velocity_score DESC),
  INDEX idx_sale (is_on_sale, sale_ends_at)
);
```

### 4.2 `actress_signals` テーブル（新規）

```sql
CREATE TABLE actress_signals (
  actress_id INT PRIMARY KEY,
  sessions_7d INT DEFAULT 0,               -- ⑤PV急上昇のメイン指標
  sessions_prev_7d INT DEFAULT 0,          -- 前週比較用
  pv_velocity_score DECIMAL(8,4),          -- (sessions_7d - prev) / prev
  fav_7d INT DEFAULT 0,                    -- Phase 3 で利用
  fav_30d INT DEFAULT 0,
  click_7d INT DEFAULT 0,                  -- 紐付き作品の合算クリック
  updated_at DATETIME NOT NULL,
  INDEX idx_pv_velocity (pv_velocity_score DESC)
);
```

⑤の出し方:
- `pv_velocity_score DESC` で並び替え
- 母数が小さすぎるノイズを除くため `sessions_7d >= 50` 等のしきい値を設ける
- 該当が6件未満なら sessions_7d 単純降順にフォールバック

### 4.3 集計バッチ（新規）

| ファイル | 内容 | 実行頻度 |
|---------|------|---------|
| `batch/aggregate_signals.php` | GA4 API → `work_signals` / `actress_signals` を更新 | 1日2回 (cron) |
| `batch/fetch_fanza_sale.php` | FANZA API でセール対象作品を取得 → `work_signals.is_on_sale` 更新 | 1日1回 |
| `batch/clear_top_cache.php` | TOP キャッシュ単独クリア | 上記後に実行 |

GA4 集計は既に `ga4/daily_report.py` で `customEvent:event_name` を扱う基盤があるため、
PHP からは **集計済みCSVを置く方式** か **PythonをcronでDB書き込みまでやる方式** の2択。
→ Pythonで書ききった方が薄い（推奨）。

### 4.4 既存テーブルとの結合

`work_signals.cid` ↔ `works.cid` で結合し、女優・タイトル情報は既存モデルから取得。
既存の `Work::all()` キャッシュと同じ形にして、表示側は最小改修で済むようにする。

---

## 5. Controller / Template 改修

### 5.1 HomeController（既存を拡張）

```php
public function index(array $params): void
{
    // 既存: 新人 / ジャンル別おすすめ / 最新記事
    // 追加:
    $fc2Ranking   = Fc2Work::getRanking('week', 10, 0);  // ②
    $hotWorks     = Work::findHotByVelocity(10);          // ③
    $saleWorks    = Work::findOnSale(10);                 // ④
    $hotActresses = Actress::findHotByPv(6);              // ⑤
    $genreTiles   = Genre::findFeatured(12);              // ⑦

    render('home', [...既存..., 'fc2Ranking' => $fc2Ranking, ...]);
}
```

キャッシュキー: `home_v2` (TTL 1800秒 = 30分)。
急上昇は遅延少なめが望ましいので既存 3600秒より短めに。

### 5.2 ヒーロー検索の3タブ

検索バーは既存の actress 検索 (もしあれば) を流用。
タブ切替で `?type=work|actress|genre` を付与し、結果ページのフィルタに流す。

新規ルート:
- `/search/?q=xxx&type=work` → `SearchController@index`
- 既に検索が無ければ Phase 2 に回し、TOP では **タブ = ジャンプリンク**（#hot-works 等）として始める

### 5.3 templates/home.php

セクション順を上記 IA に従って再構成。
横スクロールは CSS Scroll Snap で実装（JS不要）:

```css
.hot-rail { display: flex; overflow-x: auto; scroll-snap-type: x mandatory; gap: 12px; }
.hot-rail__card { flex: 0 0 220px; scroll-snap-align: start; }
```

### 5.4 パーソナルセクション（⑧）

`favorites.js` を流用し、TOPページに `#my-favorites` プレースホルダを置く。
0件なら `display: none` で sectoin ごと非表示にし、SEO 影響なし。

---

## 6. UX 仕掛け（Filmarks から学ぶ）

| 仕掛け | Filmarks | av-hakase での実装 |
|--------|---------|-------------------|
| **横スクロールで多くを見せる** | 上映中作品をrail表示 | ②③④を全部rail |
| **スコア / 評価の可視化** | ★4.2 (1234件) | ❤️128人がお気に入り、🔥クリック急上昇 |
| **残り時間のFOMO** | 上映終了まで | セール終了まで残りX日 |
| **「今夜の1本」みたいなキュレーション** | 編集部ピックアップ | AV博士の今週のピックアップ（手動 + 記事連動） |
| **タグクラウド型のジャンル探索** | カテゴリチップ | ⑥のジャンルタイル |

---

## 7. SEO への影響と対策

トップページは現在「ジャンル別おすすめ女優」記事に内部リンクを送っている。
新 TOP もこの内部リンクは **必ず維持**（重要度高い記事への被リンク数を減らさない）。

- ②③④の**作品/女優カードからのリンクで内部リンクが増える**ため、孤立ページが減る ✅
- ヒーロー検索バーは `<form>` でクロール可能にする（JS無しでも動作）
- パーソナライズ部分(⑧) は noindex 不要（要素ごと非表示なので影響なし）

---

## 8. フェーズ分割（実装順）

### Phase 1（最小で動く版・1〜2日）

- [ ] テンプレートのセクション順だけ作り変え（既存データのまま）
- [ ] ② FC2セクションは `Fc2Work::getRanking('week')` を流用するだけで動く
- [ ] ⑦ジャンルタイル新規 (Genre::featured を `app.php` 定数で固定12件)
- [ ] ヒーロー検索は **アンカージャンプ** で代用
- [ ] 横スクロール (snap-x) のCSSだけ追加

→ 「Filmarks 風の見た目」+ ② FC2 ランキングだけは Phase 1 で動く（既存データで完結）。
③④⑤ は Phase 2 まではプレースホルダ or 既存「ジャンル別おすすめ」を残しておく。

### Phase 2（行動データ反映・3〜5日）

- [ ] `work_signals` / `actress_signals` テーブル作成
- [ ] `ga4/aggregate_signals.py` 新規
  - fanza_click 集計 → `work_signals.click_7d / click_30d / velocity_score`
  - sessions 集計 → `actress_signals.sessions_7d / sessions_prev_7d / pv_velocity_score`
- [ ] cron 設定（1日2回）
- [ ] HomeController に hotWorks (③) / saleWorks (④) / hotActresses by PV (⑤) を追加
- [ ] FANZA セール取得バッチ

### Phase 3（パーソナル + 検索・5〜7日）

- [ ] `/search/` 実装（作品名・女優名横断）
- [ ] ⑨ お気に入りセクションを home.php に統合
- [ ] 「あなたが見た作品から」レコメンド（localStorage の閲覧履歴ベース）
- [ ] お気に入りデータが貯まったら ⑤ を PV単独 → PV × fav 合成スコアに切替
  （`actress_signals.fav_7d` を `pv_velocity_score` と重み付け合成）

### Phase 4（編集部ピックアップ）

- [ ] `weekly_pick` テーブル + 管理画面 or JSON 設定
- [ ] AV博士コメント付きで「今週の1本」枠を追加

---

## 9. 効果計測

導入前後で見るべき指標（GA4 で計測可能）:

| 指標 | 改善仮説 |
|------|---------|
| TOP の `fanza_click` 数 | 作品が前面に出るので大幅増を期待 |
| TOP からの作品ページ遷移率 | 0% に近い → 30%+ |
| `favorite_added` イベント数 | パーソナル機能で増 |
| TOP 直帰率 | 横スクロール導入で低下 |
| TOP セッション継続時間 | 増 |

A/B テストは Vercel の Edge Config / Flags でやれるが、PHP サイトなので
`?v=new` クエリと `Cache-Control: no-cache` での簡易A/Bが現実解。

---

## 10. リスクと注意点

- **データが薄い時期は寂しく見える** → Phase 1 で見た目だけ先に出す/フォールバックとして既存の「ジャンル別おすすめ」を併存させる
- **キャッシュ更新タイミング**: 急上昇枠は 30分キャッシュを推奨。長すぎると "今ホット" が嘘になる
- **モバイル横スクロールの初見ヒント**: 1.2枚見せる幅にして「次がある」ことを視覚で示す
- **セール終了済みを表示しない**: `sale_ends_at < NOW()` を必ずフィルタ
- **CID プレフィックス除去禁止**（CLAUDE.md 既出ルール）

---

## 確認・残タスク

進めて良ければ次は:

1. **どのフェーズから着手するか** — 提案: まず Phase 1 の見た目だけ作って体感確認
2. **ヒーロー検索を入れるか** — 既存の検索機能の有無を再確認（無ければ Phase 1 はアンカージャンプで開始）
3. **セール作品の出し方** — FANZA API のどのフィールド（campaign / price 比較）を「セール」と判定するかの定義
4. **既存「ジャンル別おすすめ女優」セクション** を残すか撤去するかの判断
5. **② FC2 の表示期間** — `week / month / all` のどれを TOP に出すか（推奨: `week` で鮮度を出す）

---

## 更新履歴

- 2026-05-04 初版作成
- 2026-05-04 ② に FC2 ランキングを追加 / ③④ を1つずつ下にずらす / ⑤ お気に入り急上昇 → PV急上昇 に変更（データ量不足のため。お気に入り版は Phase 3 で復活）
