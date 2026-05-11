# 5/7 以降のクロール量急増の原因調査

## 依頼
「5/7 からクロール量がすごい増えてるんだけど、何かやったことあったっけ？」

## 結論

**5/4〜5/5 に複数の大型変更が同時に入っており、Googlebot が 5/7 前後に反応した可能性が極めて高い。** 中でも一次容疑者は **サイトマップ分割（bb4312b, 5/4）** と **TOP リニューアル＋ルート変更（2dc0edb, 5/4）**、二次容疑者は **/sale ページ新設（09fa0fa, 5/5）**。

## 5/4〜5/5 に投入された変更一覧

| 日付 | commit | 内容 | クロール影響 |
|---|---|---|---|
| 5/4 | `bb4312b` | **サイトマップを sitemapindex 形式に分割**（core / articles / actresses / genres の4本立て） | ★★★ Googlebot は新しい sitemap 構造を発見すると全 URL を再評価する。全 `<lastmod>` を当日付で出力しているのも recrawl を誘発 |
| 5/4 | `2dc0edb` | **`/` を FC2 ランキング → HomeController に差替**。`/fc2/` 新設。「魔法の7桁ランキング」ヘッダーリンク変更 | ★★★ サイトで最もクロールされる URL の内容が一変、内部リンク網も組み変わる |
| 5/4 | `e9f88df` | `/cross-link/` 新設＋フッターに導線 | ★ 全ページから新規 URL へのリンク追加 |
| 5/5 | `09fa0fa` | **`/sale/` ページ新設**。ソート6種（`?sort=ending_soon/discount/price_low/price_high/newest`）＋ページネーション（`?page=N`）＋検索（`?q=`） | ★★ 内部リンクから `?sort=` の組合せが大量に発見される（canonical でクエリは剥がしているが、Googlebot は URL バリエーションを一旦クロールする）。さらに TOP リニューアル v2 もこのコミットに同梱 |
| 5/5 | `9bc49c9` | 2026年4月新人 36 名を一括登録＋月別記事 | ★★ 一気に女優ページ 36 個＋ジャンル子ページが新規発見対象になる |

## なぜ「5/7 から」なのか

- Googlebot は sitemap の更新を平均 1〜3 日でピックアップ。5/4 にサイトマップ構造を変えたので、5/7 頃に大規模再クロールが走るのは典型的なタイミング。
- 加えて 5/5 に **新ルート（/sale, /fc2）＋36名分の新女優ページ＋4月新人記事** が同時に投入されている。これらが sitemap-actresses.xml / sitemap-articles.xml / sitemap-core.xml に全部入った状態を Googlebot が 5/7 に発見した、というのが整合する。

## 重要な確認ポイント

### 1. `/sale/?sort=...&page=...` がクロール罠になっていないか

`SaleController` はソート6種 × 全ページ数を有効値として受け付け、canonical (`layout.php:55`) は `currentFullUrl()` でクエリを剥がしているので **インデックス重複は防げている**。ただし Googlebot は canonical を貼っていても **URL バリエーションを一通りクロールしてから判断する** ため、一時的なクロール量増加には寄与している。

→ もしクロール量がこの先も収まらないなら、`robots.txt` で `Disallow: /sale/*?sort=` と `Disallow: /sale/*?q=` を追加するか、sale.php の sort/page リンクに `rel="nofollow"` を付ける選択肢あり。

### 2. サイトマップの `<lastmod>` 全件 today

`SitemapController::index` の sitemapindex で `lastmod = date('Y-m-d')` を毎回 today にしている (`SitemapController.php:12-18`)。これは **毎日全 sitemap が更新されたシグナルになり、再クロールを毎日トリガする**。

→ 子サイトマップごとに実体の最終更新日（articles の最大 updated_at、actresses の最大 updated_at 等）を入れるべき。これはクロールバジェットの観点で対処価値が高い。

### 3. `/fc2/→/` 旧 301 撤去 (`2dc0edb`)

旧 `/fc2/→/` 301 を撤去し `/fc2/` を実体ページにしたので、旧 301 ターゲットだった URL（外部被リンクや GSC 既知 URL）が一気に再評価される。これも crawl spike 寄与。

## 推奨アクション（優先度順）

1. **sitemap-index の `<lastmod>` を実体ベースに修正**（毎日 today 出力をやめる）— 一番効く
2. **GSC の「クロールの統計情報」を確認** — どの URL タイプ（sale / actress / genre / article）が伸びているかを切り分けると原因がさらに明確になる
3. **`/sale/?sort=` のクロール抑制**を検討（GSC 統計次第。`?sort=` が大半なら robots.txt で塞ぐ）
4. **何もしない選択肢もある** — サイトマップ分割直後の crawl spike は健全な挙動（新規 URL 発見＋既存 URL 再評価）で、1〜2 週間で落ち着くのが普通

## 変更ファイル
（調査のみ・コード変更なし）

## 確認・残タスク
- GSC の「設定 → クロールの統計情報」で URL タイプ別の内訳を見たい。スクリーンショットを共有してもらえれば、上記 1〜3 のどれを優先すべきか判断できる。
- 上記 1（lastmod 実体ベース化）を着手してよいか確認。
