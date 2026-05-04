# ジャンルページ「単体0件で空表示」バグ修正

## 依頼
GSCで `/{actress}/{genre}/` のジャンルページが「全X本」と書かれているのに作品が0件表示の状態で公開されているケースを修正。

## 原因
`GenreController::show` のロジック:
- `countByActressAndGenre()` = 全作品数（404判定・タイトル「全X本」用）
- `countSingleByActressAndGenre()` = 単体作品のみ（デフォルト表示用）

→ 全作品 ≥ GENRE_MIN_WORKS(3) で 200を返すが、すべて企画/オムニバス/コンビ作品の場合、単体作品=0で**実表示は空**。さらに `noindex` 条件は `< 3` のみなのでGoogleにインデックスされ得る。

GSC実例: `/fujiura-megu/kateikyoushi/`, `/meguri/kateikyoushi/`, `/riku/panchira/` (本番で全3本/カード0)。

## 実施内容（Option B: 全作品フォールバック）
単体作品=0のときは全作品表示に切り替え、タイトル「全X本」と整合させる。

## 変更ファイル
- `meikan/src/controllers/GenreController.php:36-41` — `$totalSingle === 0` なら `$singleOnly=false` で全作品表示にフォールバック。`$singleOnly` をビューに渡す
- `meikan/src/controllers/GenreController.php:107` — `'singleOnly' => $singleOnly` をrenderに追加
- `meikan/templates/genre.php:73` — チェックボックスの `checked` を `$singleOnly` に連動
- `meikan/public/js/genre.js:28` — `currentSingle` の初期値をチェックボックスの状態から読み取り（ハードコード `true` を廃止）

## 検証
ローカル（`localhost:8765`）で単体0件×全作品≥3のサンプルを確認:

| URL | タイトル | 表示カード |
|---|---|---|
| `/aihou-suzu/m-otoko/` | 全4本 | 4枚 ✅ |
| `/aihou-suzu/gansha/` | 全6本 | 6枚 ✅ |
| `/aihou-suzu/chikubi/` | 全3本 | 3枚 ✅ |

チェックボックスは `checked` 外れ。JS側 currentSingle=false で同期。

## sitemap・noindex影響
- sitemap-genres.xml の閾値判定は `countByActressAndGenre()`（全作品）のままなので、フォールバックページも sitemap に含まれて整合する
- 全作品<3の場合は従来通り noindex（変更なし）

## 確認・残タスク
- 本番デプロイ → GSCで該当URLの再クロール後、検出ページ数が正しく反映されるか確認
- 単体ありで本来 noindex が効いていたページに影響しないことの軽い回帰確認
