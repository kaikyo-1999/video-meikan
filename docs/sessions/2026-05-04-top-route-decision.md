# TOPルートの扱い — 重大な判断ポイント

## 発見

`meikan/index.php:72` を確認した結果、**現在の `/` は `Fc2RankingController@index`** にルーティングされている。
CLAUDE.md の route 表（`/` → HomeController）は**古い情報**。

```php
// 現状
$router->add('', 'Fc2RankingController@index');     // ← / は FC2 ランキング
$router->add('meikan/', 'TopController@index');     // 女優一覧
// HomeController は require はされているが route に未登録 (orphan)
```

git log でも確認: `e80358e トップページをFC2魔法の7桁ランキングに変更・/fc2/を/にリダイレクト` (2026年4月)
→ ユーザーが意図的に切り替えた経緯あり。

加えて `/fc2/` → `/` への **301 リダイレクト**も `meikan/index.php:16-20` で設定されている。

---

## 何が問題か

Filmarks 風 TOP リニューアル提案 (`2026-05-04-filmarks-style-toppage-proposal.md`) は
**`/` = HomeController という前提**で書かれていたが、実際は違う。

そのまま進めると:
- HomeController を改修しても **誰も見ない** （ルートが無い）
- FC2 ランキングが `/` の主役のまま

---

## 選択肢

### A案: FC2 を `/fc2/` に戻し、`/` を新 TOP に
```
/        → HomeController@index (Filmarks 風 新 TOP)
/fc2/    → Fc2RankingController@index  (移動)
```
- ✅ 提案書の意図に最も忠実
- ✅ Filmarks 風セクション構成をフルに発揮
- ✅ FC2 ランキングは1セクションとして TOP に登場 (横スクロール rail)
- ⚠️ 「fc2 7桁」系クエリの被リンク・順位が `/` から `/fc2/` に移る
  → 一時的な順位下落の可能性
  → 301 リダイレクトを `/` → `/fc2/` で正しく張れば中長期では回復
- ⚠️ 内部リンクで `/` を「魔法の7桁ランキング」として張っている箇所を更新する必要
  - `templates/header.php:10` `header__nav-link>魔法の7桁ランキング` → `/fc2/` に
  - その他 footer / sitemap / 記事内リンクなど

### B案: `/` の上に Filmarks セクションを足す（FC2 ランキングは下に残す）
```
/        → 新 HomeController (ヒーロー + 新人 + ジャンル + FC2 + 記事)
/fc2/    → / リダイレクト維持
```
- ✅ URL 変更ゼロ → SEO リスク最小
- ⚠️ FC2 ランキングがページ下部に押される
  - 「fc2 7桁」狙いキーワードのコンテンツが fold 外に → 順位下落の可能性
- ⚠️ ページが縦長になり LCP 悪化のリスク

### C案: ハイブリッド (FC2 を主役のまま、上に Filmarks サマリーを薄く追加)
```
/        → 改修 Fc2RankingController
           上部: ヒーロー + ミニ新人 rail + ジャンルタイル
           主役: FC2 ランキング (現状維持)
           下部: ジャンル別おすすめ女優 + 最新記事
```
- ✅ FC2 ランキングの SEO 保護
- ✅ URL 変更ゼロ
- ⚠️ 「Filmarks 風」体験は薄まる (FC2 が中心)
- ⚠️ ページの主題が混在し UX が散漫になる懸念

---

## 推奨

**A案を推奨**。理由:

1. 元の提案書 (`filmarks-style-toppage-proposal.md`) で合意した IA は「FC2 は1セクション」
2. FC2 ランキングはコンテンツとして十分独立しており、`/fc2/` という明示的な URL の方が分かりやすい
3. SEO リスクは 301 リダイレクトで適切に管理可能
4. 中長期では「サイト全体の TOP として何が必要か」が優先

ただし:
- GSC で `/` の現状クエリ・流入を確認してから決めた方が安全
- 「fc2 7桁」系クエリの順位が`/` で取れているなら、一時的な順位下落を覚悟する必要あり

---

## 既に実装済みのもの (どの案でも無駄にならない)

- ✅ `Genre::findFeatured()` 追加
- ✅ `FEATURED_GENRE_SLUGS` config 定義
- ✅ `partials/fc2-rail-card.php` 新規作成
- ✅ `partials/genre-tile.php` 新規作成
- ✅ `HomeController` に fc2Ranking / featuredGenres を渡す変更

---

## 確認事項

1. **A / B / C どれで進めるか**（推奨: A）
2. A案を選ぶ場合: GSC で `/` の現状流入を確認するか即時実行か
3. A案を選ぶ場合: `/fc2/` への 301 移行のタイミング（即日 / 段階的）
