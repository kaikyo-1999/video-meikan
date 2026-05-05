# TOP デザイン改善 Day 1（A1〜A9）実装ログ

## 依頼

`docs/sessions/2026-05-04-toppage-design-improvement.md` の Day 1 (A1〜A9) を実装。

## 実施内容

| ID | 内容 | 結果 |
|----|------|------|
| A1 | `.top-section__title` をピンク帯ラベル → 左4pxアクセントバー + 18px太字に刷新 | ✅ |
| A2 | `.rail-wrap` 導入。`::after` で右端32px（モバイル24px）の白フェードを overlay。FC2/Hot/Sale 3 rail に適用 | ✅ |
| A3 | `@media (hover:none)` 限定で work/fc2/sale rail / actress-card / genre-tile の `:active { transform: scale(0.98); transition: 80ms }` | ✅ |
| A4 | `@keyframes sale-pulse` 定義し `.sale-rail-card__badge` に `1.6s ease-out 0.4s 1`。`prefers-reduced-motion` で無効化 | ✅ |
| A5 | home.php 全セクション見出し・hero タブから絵文字を全廃（grep 0 件確認） | ✅ |
| A6 | hero `role="tab"` `role="tablist"` を撤去し `<nav aria-label="主要セクションへのショートカット">` に降格。Alpine.js の挙動は維持 | ✅ |
| A7 | fc2-rail-card.php に `data-fc2-link-type="fc2_rail"` 追加。sale-rail-card は既存の `data-fanza-link-type="sale_rail"` を維持 | ✅ |
| A8 | `.hero__subtitle` のコントラスト rgba(255,255,255,0.85) → 0.92 | ✅ |
| A9 | TOP 末尾に「AV博士について」リード文 + 統計（登録女優 / 作品数 / コラム記事）を配置。`Work::countAll()` を新規追加 | ✅ |

## 変更ファイル

- `meikan/public/css/style.css`
  - `.top-section__title` 刷新（旧ピンク帯撤去）
  - `.top-section__title-sub` を白背景バッジ → 中性グレーの脇役テキストに変更
  - `.hero__subtitle` コントラスト引き上げ
  - `.rail-wrap` + `::after` フェード新設
  - `:active` タップフィードバック媒体クエリ
  - `@keyframes sale-pulse` + バッジへの 1 周期適用
  - `.site-intro__*` 一式
- `meikan/templates/home.php`
  - hero の ARIA / 絵文字整理
  - 全セクション見出しから絵文字撤去 + `aria-labelledby` で関連付け
  - rail を `<div class="rail-wrap" role="region" aria-label="...">` でラップ（FC2 / Hot / Sale）
  - サブラベル（週間 TOP10 / 急上昇 TOP10 / 期間限定価格 / 直近7日 / 今月の話題 / 人気12カテゴリ / 編集部ピックアップ / サイトの楽しみ方）追加
  - 末尾に「AV博士について」セクション + 統計 dl 追加
- `meikan/src/controllers/HomeController.php`
  - `workCount` / `articleCount` をテンプレートへ渡すよう拡張
- `meikan/src/models/Work.php`
  - `countAll()` 静的メソッドを追加（cache TTL 1日）
- `meikan/templates/partials/fc2-rail-card.php`
  - `data-fc2-link-type="fc2_rail"` を追加（GA4 計測の一貫性）

## 検証

- PHP lint：4 ファイル全て syntax error なし
- `agent-browser` で localhost:8000 を mobile (390x844) / desktop (1280x900) スクショ
- DOM 検証：
  - `.top-section__title` 8 個（hero 後の全セクション）
  - `.rail-wrap` 3 個（FC2 / Hot / Sale）
  - `.site-intro__stat` 3 個（女優 / 作品 / 記事）
  - 全タイトル文字列に絵文字なし（grep 0 件）
- 統計値（実測）：登録女優 357 人 / 作品数 32,355 本 / コラム記事 41 本

## 確認・残タスク

- ユーザーがローカル（http://localhost:8000/）で実機確認
- 問題なければコミット → デプロイ
- 次フェーズ: B1〜B4（コンテンツタイプ別アクセントパレット / Spotlight ハイブリッド / Today's Pick ヒーロー）
- 検索バーの Google サイトサーチ仮実装（C3）は未着手 — ユーザー判断待ち

## スクリーンショット

- mobile: `/tmp/top-after-A1-A9.png`
- desktop: `/tmp/top-desktop.png`
