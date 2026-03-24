# ローカル開発環境

## 前提

- macOS（Apple Silicon）
- Homebrew で MySQL インストール済み（`brew install mysql`）
- PHP 8.x インストール済み

## セットアップ

### 初回セットアップ

```bash
bash scripts/setup-local.sh
```

このスクリプトが以下を行う:
1. MySQL 起動確認（ソケット: `/tmp/mysql_meikan.sock`）
2. `video_meikan` データベース作成 + スキーマ適用
3. `.env.local` 作成（ローカルDB接続設定）
4. `cache/`, `logs/` ディレクトリ作成

### 本番データの同期

```bash
bash scripts/sync-db.sh
```

本番サーバーにSSH接続し、`mysqldump` でデータを取得してローカルDBに反映する。

## 開発サーバー起動

```bash
cd meikan
php -S localhost:8000 dev-server.php
```

ブラウザで http://localhost:8000/ を開いて確認。

## 環境設定ファイルの仕組み

### .env と .env.local の優先順位

`config/database.php` は以下の順で環境ファイルを読み込む:

1. `.env.local`（存在すれば最優先）
2. `.env`（`.env.local` がなければこちら）

| ファイル | 用途 | git管理 |
|---------|------|---------|
| `.env` | 本番サーバー用（サーバー上で直接作成） | 除外 |
| `.env.local` | ローカル開発用 | 除外 |
| `.env.example` | 設定例（新規セットアップ時の参考） | 管理対象 |

### ローカル用 .env.local の内容

```
DB_HOST=localhost
DB_NAME=video_meikan
DB_USER=root
DB_PASS=
DB_SOCKET=/tmp/mysql_meikan.sock

FANZA_API_ID=（本番と同じAPIキー）
FANZA_AFFILIATE_ID=（本番と同じアフィリエイトID）
```

`DB_SOCKET` を指定することで、Homebrew の MySQL にソケット経由で接続する。

## MySQL について

### 起動・停止

```bash
# 起動（Homebrewサービス）
brew services start mysql

# 停止
brew services stop mysql
```

Homebrew サービスが動かない場合は直接起動:

```bash
/opt/homebrew/opt/mysql/bin/mysqld_safe \
    --datadir=/opt/homebrew/var/mysql \
    --socket=/tmp/mysql_meikan.sock &
```

### DB接続

```bash
mysql -u root -S /tmp/mysql_meikan.sock video_meikan
```

## 本番との違い

| 項目 | ローカル | 本番 |
|------|---------|------|
| Webサーバー | PHP ビルトインサーバー | Apache |
| URL書き換え | `dev-server.php` で処理 | `.htaccess` で処理 |
| DB接続 | ソケット（rootパスワードなし） | ホスト指定（パスワードあり） |
| HTTPS | なし（http://） | あり（https://） |
| キャッシュ | 手動クリア | 手動クリア |

## 開発の流れ

1. ローカルでコード修正
2. ブラウザで http://localhost:8000/ を確認
3. 問題なければ `git commit` → `git push`
4. 本番にデプロイ（`docs/deploy.md` 参照）
