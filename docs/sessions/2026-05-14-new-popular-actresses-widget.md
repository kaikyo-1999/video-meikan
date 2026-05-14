# 女優・ジャンルページに「新人女優」「人気女優」ウィジェット追加

## 依頼
ジャンル / 女優ページの最下部に TOP で使っている新人女優・人気女優のウィジェットを入れたい。対話で仕様すり合わせ済み。

## 確定仕様

### 1. 対象セクション
- **新人女優** (`debutActresses`): TOP と同じロジック（先月デビュー→DB最新月へフォールバック、有効サムネのみ、最大6名、ページ固有フィルタなし）
- **人気女優** (`hotActresses`): ページ固有フィルタ
  - 女優ページ: 該当女優の Top1 ジャンル（作品数最多のジャンル）で人気な他女優（PVベース、最大6名、該当女優除外）
  - ジャンルページ: 該当ジャンルで人気な他女優（PVベース、最大6名、該当女優除外）

### 2. 配置ロジック
- **作品数 < `GENRE_MIN_WORKS`(10本)** のページ:
  - 作品リストの**下部**に「新人女優」「人気女優」セクションを並べる（帯状）
- **作品数 ≥ 10本** のページ:
  - 作品リスト **`index=5`** の挿入位置を URL ハッシュ固定ローテーションに拡張
  - 女優ページ候補: `similar` / `related` / `new` / `popular`（4種）
  - ジャンルページ候補: `other-genres` / `similar` / `related` / `new` / `popular`（5種）
  - サイドバー（ジャンルページの「好きな人にオススメ」）は**触らない**

### 3. ローテーション方式
- ハッシュソース:
  - 女優ページ: `$actress['slug']`
  - ジャンルページ: `$actress['slug'] . '/' . $genre['slug']`
- `crc32($source) % count($availableCandidates)` で1つに固定
- データが空の候補はスキップして、ハッシュ起点で次の候補にフォールバック
- 訪問ごとには変化させない（SEO一貫性 / Googleのスナップショット安定）

### 4. カードデザイン
- 既存 `partials/similar-actresses-inline.php` のレイアウトを流用
- セクション見出しを `見出しテンプレ` で切り替え:
  - similar: `{女優名}と似ている女優`（既存）
  - related: `{女優名}の関連女優`
  - new: `今月デビューの新人女優` または `{YYYY}年{M}月デビューの新人`
  - popular: `今人気の{ジャンル名}女優`（女優ページ: Top1ジャンル名 / ジャンルページ: 該当ジャンル名）

### 5. 件数
- 全セクション共通で最大6名（既存similarと同じ）

## 実装計画

### ファイル変更
1. `meikan/src/models/Actress.php`
   - `findHotByPvFilteredByTopGenre(int $actressId, int $limit, int $minSessions)` 追加
   - `findHotByPvAndGenre(int $genreId, int $excludeActressId, int $limit, int $minSessions)` 追加
   - `findRecentDebut(int $limit)` 追加（HomeController から切り出し、6名+フォールバック）

2. `meikan/src/controllers/HomeController.php`
   - 既存の debut 取得を `Actress::findRecentDebut()` に置き換え（共通化）

3. `meikan/src/controllers/ActressController.php`
   - `debutActresses` を取得して渡す
   - Top1 ジャンル特定 → `hotActresses` 取得して渡す
   - `topGenreName`（人気女優セクションタイトル用）を渡す

4. `meikan/src/controllers/GenreController.php`
   - `debutActresses` を取得して渡す
   - `hotActresses` を取得（該当ジャンルで人気な他女優）
   - `relatedActresses` も取得（ローテ候補に含めるため。現状 `similarActresses` のみ取得している場合は要追加）

5. `meikan/templates/partials/`（新規）
   - `debut-actresses-inline.php` — 新人女優のインライン挿入カード
   - `popular-actresses-inline.php` — 人気女優のインライン挿入カード
   - `related-actresses-inline.php` — 関連女優のインライン（既存`similar`と差別化見出し）
   - `recommend-bottom-section.php` — 作品<10本ページ用、下部に新人+人気を並べる

6. `meikan/templates/partials/work-list-insertions.php`
   - `$globalIndex === 5` のロジックを CRC32 ベースのローテーションに置換
   - データ有無チェック → fallback 探索

7. `meikan/templates/actress.php` / `meikan/templates/genre.php`
   - 作品リストの直下に `if ($totalWorks < GENRE_MIN_WORKS)` で `recommend-bottom-section.php` を require

## 変更ファイル

### モデル
- `meikan/src/models/Actress.php` — `findHotByPvAndGenre()` / `findHotByPvFilteredByTopGenre()` / `findRecentDebut()` を追加

### コントローラー
- `meikan/src/controllers/HomeController.php` — debut 取得を `Actress::findRecentDebut()` に置換（共通化）
- `meikan/src/controllers/ActressController.php` — debutActresses / hotActresses / topGenreName を render() に追加。hot が空のときは全体PV順にフォールバック
- `meikan/src/controllers/GenreController.php` — relatedActresses / debutActresses / hotActresses / isFewWorks を render() に追加。hot が空のときは全体PV順にフォールバック

### テンプレート
- `meikan/templates/partials/actresses-inline.php`（新規）— 汎用 女優カード インライン挿入（$inlineTitle / $inlineItems / 任意CTA）
- `meikan/templates/partials/recommend-bottom-section.php`（新規）— 作品<10本ページ用、最下部に新人＋人気を並べる
- `meikan/templates/partials/work-list-insertions.php` — `index=5` に URLハッシュ(CRC32) 固定ローテーション。actress: similar/new/popular の3スロット（related は actress.php のエイリアスで実質 similar に統合）。genre: other-genres/similar/related/new/popular の最大5スロット。データ空はスキップ
- `meikan/templates/partials/similar-actresses-inline.php`（削除）— `actresses-inline.php` に統合
- `meikan/templates/actress.php` / `meikan/templates/genre.php` — `if ($isFewWorks)` で `recommend-bottom-section.php` を作品リスト直下に require

### スタイル
- `meikan/public/css/style.css` — `.similar-inline__title` を flex 化、`.similar-inline__cta` 追加、`.similar-inline + .similar-inline` 間隔調整

## ローカル検証結果

| 対象 | 結果 |
|------|------|
| `php -l` 全変更ファイル | No syntax errors |
| `/hatano-yui/` (3842本) | `index=5` で「2026年4月デビューの新人女優」セクション表示。既存「波多野結衣が好きな人にオススメ」は最下部にそのまま維持 |
| `/hatano-yui/kyonyu/` (1157本) | `index=5` で「波多野結衣が好きな人にオススメ」(similar) を表示 |
| `/honmaasami/` (少作品) | `index=5` は発火せず（5作品未満）。代わりに作品リスト直下に「2026年4月デビューの新人女優」「今注目の人気女優」の2セクションが表示 |
| ハッシュ分散検証 | hatano-yui mod3=1, mihinanami mod3=0, okajimaakuru mod3=2 で分散 |

## 確認・残タスク

- PSI（mobile/desktop）はメタタグ/HTML追加のみで Lighthouse スコアへの実質影響は小。新規セクションがLCP要素になることはないが、デプロイ後に主要ページを念のため計測する
- 本番DBで `actress_signals` テーブルの sessions_7d データ充足度に依存。データ薄ければ全体PV順フォールバックが効くため、人気女優は最低限表示される
