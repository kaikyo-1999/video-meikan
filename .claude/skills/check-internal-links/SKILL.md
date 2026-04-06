---
name: check-internal-links
description: "記事内の内部リンクとDB参照の整合性をチェックする。404リンク、存在しない@actress[slug]、記事リンク切れを検出。'内部リンクチェック', 'リンク切れ確認', 'check links', '404チェック', 'internal link check', 'リンク検証'で発動。"
metadata:
  version: 1.0.0
---

# 内部リンク404検知スキル

記事Markdown内のリンクと@actressタグが有効かを検証し、404になるリンクを検出する。

---

## チェック対象

| パターン | チェック内容 |
|---------|------------|
| `@actress[slug]` | DBのactressesテーブルにslugが存在するか |
| `[text](/article/slug/)` | `content/articles/` にslug.mdファイルが存在するか |
| `[text](/actress-slug/)` | DBにactress slugが存在するか（slug_redirectsも考慮） |
| `[text](/actress-slug/genre-slug/)` | 女優slug + ジャンルslugの両方がDBに存在するか |

---

## 実行フロー

### Step 1: ローカルDBでチェック

```bash
php meikan/batch/validate_articles.php
```

validate_articles.phpが内部リンクチェックを含んでいるため、これで以下を検出できる：
- @actress[slug]のDB不存在
- /article/slug/ のファイル不存在
- /actress-slug/ のDB不存在
- /actress-slug/genre-slug/ のDB不存在

### Step 2: 本番DBとの差分チェック（オプション）

ローカルDBと本番DBでslugが異なるケースを検出する。

```bash
# 記事内の全@actress slugを抽出
grep -roE '@actress\[[a-z0-9-]+\]' meikan/content/articles/*.md | \
  sed 's/.*@actress\[//;s/\]//' | sort -u > /tmp/article_slugs.txt

# 本番DBのslugリストを取得
ssh -i ~/.ssh/shinserver_rsa -p 10022 wp2026@sv6810.wpx.ne.jp "php -r \"
define('ROOT_DIR', '/home/wp2026/av-hakase.com/public_html');
require_once ROOT_DIR . '/config/database.php';
\\\$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
\\\$stmt = \\\$pdo->query('SELECT slug FROM actresses');
while(\\\$r = \\\$stmt->fetch(PDO::FETCH_NUM)) echo \\\$r[0] . PHP_EOL;
\"" | sort > /tmp/prod_slugs.txt

# 本番DBにないslugを検出
echo "=== 本番DBに存在しない@actress slug ==="
comm -23 /tmp/article_slugs.txt /tmp/prod_slugs.txt
```

### Step 3: 結果レポート

- エラー数・警告数を集計
- エラーがある場合は修正方針を提示:
  - @actress slug不存在 → 女優登録 or slug修正 or リダイレクト追加
  - 記事リンク不存在 → 記事作成 or リンク修正
  - 本番DB不一致 → 本番バッチ実行 or slug統一

---

## 使い分け

| 状況 | 推奨コマンド |
|------|------------|
| 記事作成・編集後 | Step 1のみ（ローカルチェック） |
| デプロイ前 | Step 1 + Step 2（本番差分チェック） |
| 定期チェック | Step 1 + Step 2 |
