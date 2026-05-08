# /simplify 実装全体レビュー — 2026-05-07

## 依頼
`/simplify 現在の実装全体` — meikan/src + templates + batch + ga4/gsc を対象に、**Reuse / Quality / Efficiency** の3軸で並列レビューし、安全度の高い指摘から修正する。

## 実施内容

### Phase 1-2: 並列レビュー
3エージェントを並列実行（subagent_type=general-purpose）。スコープは `meikan/src`, `meikan/templates`, `meikan/batch`, `meikan/public/css/style.css`, `ga4/`, `gsc/`。

### Phase 3: 修正適用方針

指摘は多数（Reuse 14件 / Quality 17件 / Efficiency 18件）あったため、**risk × impact** で4階層に triage:

- **A. 即適用**（小・機械的・低リスク）
- **B. 適用候補**（中規模・要レビュー）
- **C. 要相談**（DB スキーマ変更・大規模リファクタ・SEO/UX影響）
- **D. 見送り**（コスト > 価値）

このセッションでは A のみを適用し、B/C はバックログとして本ドキュメントに記録する。

---

## A. 即適用したもの

### A1. CLAUDE.md ルール違反: CID 数字プレフィックス除去を削除
- `meikan/src/controllers/ArticleController.php:724-732` — `preg_replace('/^\d+/', '', $cid)` で「1ebod00944 → ebod00944」のようなフォールバック画像URLを生成していた
- CLAUDE.md「FANZA CID ルール」で**明確に禁止**されている処理
- `1mgold00045` のように先頭の `1` がCIDの一部であるケースで 404 を生む
- 該当ロジック (`$strippedId`, `$altThumb`, `$onerror` の3変数 + `<img onerror>` 出力) を削除

### A2. `Cache::remember()` ヘルパー追加
- `meikan/src/Cache.php` — get/set ペアの繰り返し定形コードを 1 行に集約するヘルパーを追加
- 既存の `get/set/clear` API は無変更（互換維持）
- 30+ サイト（Work.php / Actress.php / Genre.php）の段階的置き換えに利用可能

### A3. `Actress::VALID_THUMB_PREDICATE` 定数化
- `meikan/src/models/Actress.php` — 同一 SQL 述語 (`thumbnail_url IS NOT NULL AND ... NOT LIKE "%/digital/video/%" AND ... NOT LIKE "%now_printing%"`) が **6箇所** にコピペ展開されていた
- クラス定数 + テーブルエイリアス可変版 (`validThumbPredicate(string $alias = 'a'): string`) として抽出し、6箇所で使用
- HomeController.php:21-25 にも PHP 側で同等のフィルタがあるため、`Actress::hasValidThumbnail(array $row)` を追加して置換

### A4. home.php:1 の歴史的 PR コメント削除
- `<?php /* Filmarks 風 TOP — Phase 2 (2026-05-04) — Hero 撤去済み */ ?>` を削除
- CLAUDE.md「コメントは WHY を残す。タスクを参照するコメントは削除」に基づく

### A5. `formatRelativeTime` 未来タイムスタンプ対応
- `meikan/src/helpers.php:143-154` — 未来時刻が渡されると「-3分前」のような表示になる潜在バグ
- `$diff < 0` の早期リターンを追加

---

## B. 適用候補（次回以降にバッチで実施推奨）

| # | 領域 | 概要 | ファイル |
|---|------|------|---------|
| B1 | Reuse | バッチの `actressNameMatches` 関数 5 コピーを `batch/config.php` に集約 | fetch_fanza.php, fetch_fanza_targeted.php, fetch_actress_profiles.php, fix_actress_work.php, validate_actress_works.php |
| B2 | Reuse | FANZA URL builder (`fanzaAffiliateUrl/fanzaPackageImage/fanzaDetailUrl`) を `helpers.php` に集約 | ArticleController, fetch_fanza系, partials |
| B3 | Reuse | FANZA item-parse 共通ブロック (~80行) を `parseFanzaItem()` に抽出 | fetch_fanza.php / fetch_fanza_targeted.php / update_prices.php |
| B4 | Reuse | `fetch_fanza.php` と `fetch_fanza_targeted.php` の本体重複（byte-level） — `--all` フラグで targeted 側に統合し fetch_fanza.php を削除 | batch/ |
| B5 | Reuse | Python: GA4/GSC サービスアカウント boilerplate (`KEY_FILE`, `service_account.Credentials.from_service_account_file`) を共通モジュール化 | ga4/daily_report.py, ga4/aggregate_signals.py, gsc/fetch.py |
| B6 | Reuse | Python: `ACTRESS_RE` / `GENRE_RE` / `classify()` の3コピーを `gsc/url_classify.py` に集約 | ga4/daily_report.py, gsc/daily_report.py, gsc/ctr_compare_report.py |
| B7 | Reuse | Rail-card 系 CSS の共通化（`.rail-card` ベース + 修飾子）— 約80行削減 | public/css/style.css 4275-4617 |
| B8 | Reuse | Rail-card 系 partial 3つを `partials/rail-card.php` に統合 | partials/work-rail-card.php, sale-rail-card.php, fc2-rail-card.php |
| B9 | Quality | Sale WHERE clause が 3 サイトで重複 — `Work::saleWhereSql()` に抽出 | models/Work.php:84-89, 117-121, 149-153 |
| B10 | Quality | 信号フォールバック 3-tier 構造（`findHotByPv` / `findHotByVelocity`）を `signalFallback()` ヘルパーに | models/Actress.php / Work.php |
| B11 | Quality | `Fc2RankingController` の `render('fc2_submit', ...)` 5 コピーを `renderSubmit($error, $success)` に集約 | controllers/Fc2RankingController.php:109-170 |
| B12 | Quality | `ApiController` のクエリパラメータ allow-list が controller / model に重複 — `WorkSort` / `VrFilter` クラス定数化 | controllers/ApiController.php, SaleController.php, models/Work.php |
| B13 | Quality | `genre.php`, `actress.php`, `sale.php` 内のビジネスロジックを controller に移譲 | templates/* |
| B14 | Efficiency | `ArticleController::renderWorkEmbed` 内の `$lastWorkUrl` 静的可変経由で `:::samples` フラッシャと暗黙結合 | controllers/ArticleController.php |
| B15 | Quality | `:::say` バブル HTML が ArticleController と actress.php で重複 — `partials/hakase-bubble.php` に抽出 | controllers/ArticleController.php:393, templates/actress.php:62 |

---

## C. 要相談（影響範囲が大きい・SEO/データ整合性に関わる）

| # | 領域 | 概要 | リスク |
|---|------|------|--------|
| C1 | Efficiency | `Work.php` の `actress_count = 1` 相関サブクエリを precompute column 化 | バッチに集計列更新追加。データ整合性検証必要 |
| C2 | Efficiency | `Genre::findFeatured` の cover query — LIMIT 無し全行スキャン → window function 化 | TOP ホットパス。MySQL バージョン要確認 |
| C3 | Efficiency | `Actress::findByDebutMonth` の `DATE_FORMAT(...)= ?` をレンジ条件 (`BETWEEN`) に書き換え（idx_debut_date を活かす） | 月境界のタイムゾーン挙動確認必要 |
| C4 | Efficiency | works テーブルへインデックス追加 (`idx_sale_end`, `idx_review_count`, `(sale_end_at, list_price, price)` 複合) | スキーマ migration。CLAUDE.md「本番DBスキーマ同期」フロー必須 |
| C5 | Efficiency | `Cache::clear()` の `glob()` を 2-hex sharding に変更 | キャッシュディレクトリ構造変更。デプロイ時に旧キャッシュ flush 必要 |
| C6 | Efficiency | `aggregate_signals.py:193` の `clear_top_cache` がサイト全体キャッシュを破棄 → tag-based invalidation 化 | Cache 層の API 拡張 |
| C7 | Quality | VR フィルタ `title LIKE '%【VR】%'` （CLAUDE.md 違反: タイトル判定不正確）→ `is_vr` カラム or work_genre join | スキーマ変更 + バッチ書き込み更新 |
| C8 | Quality | ArticleController 883 行（うち markdownToHtml ~556 行）を `MarkdownParser` / `BlockRenderers` / `ArticleEmbeds` に分離 | 大規模リファクタ。記事レンダリング全件回帰テスト必要 |
| C9 | Quality | controller の SQL 直書きを model に移譲（`ArticleController:711-720`, `Fc2RankingController:91-94`） | 中規模だが回帰リスク低い、B 候補にも入る |

---

## D. 見送り

| # | 概要 | 理由 |
|---|------|------|
| D1 | `formatRelativeTime` 未来タイムスタンプ仕様化 | A5 で対応済 |
| D2 | `helpers.php:122-125` `latestReleaseTag`/`latestReleaseMonth` 統合 | 既に内部で呼び出しチェイン済（指摘事項通り） |
| D3 | `ApiController::works` 4 分岐の統合 | 既存ロジックが明示的で読みやすく、抽象化の利得が薄い |
| D4 | `gsc/fetch.py` rowLimit ページネーション化 | 現状 1000 行で運用上問題なし。必要時に追加 |

---

## 変更ファイル
- `meikan/src/controllers/ArticleController.php:724-732,738,751` — A1 (CID プレフィックス除去ロジック削除)
- `meikan/src/Cache.php` — A2 (`Cache::remember()` ヘルパー追加)
- `meikan/src/models/Actress.php` — A3 (定数化 + 6 SQL サイト置換 + `hasValidThumbnail()` 追加)
- `meikan/src/controllers/HomeController.php:21-25` — A3 適用
- `meikan/templates/home.php:1` — A4 コメント削除
- `meikan/src/helpers.php:143-154` — A5 future timestamp 早期リターン

## 確認・残タスク

- B/C の各項目はそれぞれ別タスクとして切り出し可能。優先順位を決めて 2026-05-08 以降にバッチで実施するのが妥当。
- 特に **C7 (VR フィルタ CLAUDE.md 違反)** は SEO 的にも気になるので早めに着手したい。
- 修正後にローカルで `php -S localhost:8000 -t meikan meikan/dev-server.php` で TOP/article/actress 各ページを目視確認推奨。
