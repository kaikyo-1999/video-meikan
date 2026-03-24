#!/bin/bash
# 本番DBからローカルDBにデータを同期するスクリプト
# Usage: bash scripts/sync-db.sh

set -e

SSH_HOST="sv6810.wpx.ne.jp"
SSH_USER="wp2026"
SSH_PORT="10022"
SSH_KEY="$HOME/.ssh/shinserver_rsa"

MYSQL_SOCKET="/tmp/mysql_meikan.sock"
DUMP_FILE="/tmp/video_meikan_dump.sql"
LOCAL_DB="video_meikan"
LOCAL_USER="root"
MYSQL_CMD="mysql -u $LOCAL_USER -S $MYSQL_SOCKET"

echo "=== 本番DB → ローカルDB 同期 ==="
echo ""

# 1. MySQLが起動しているか確認
if ! mysqladmin ping -u "$LOCAL_USER" -S "$MYSQL_SOCKET" 2>/dev/null | grep -q "alive"; then
    echo "✗ ローカルMySQLが起動していません"
    echo "  先に実行: bash scripts/setup-local.sh"
    exit 1
fi

# 2. 本番サーバーからDB情報を取得してダンプ
echo "[1/3] 本番DBからダンプを取得..."
echo "  → SSH接続: $SSH_USER@$SSH_HOST:$SSH_PORT"

# 本番の.envからDB情報を読み取ってmysqldumpを実行
ssh -i "$SSH_KEY" -p "$SSH_PORT" "$SSH_USER@$SSH_HOST" bash -s << 'REMOTE_SCRIPT' > "$DUMP_FILE"
# 本番の.envからDB接続情報を読む
ENV_FILE="$HOME/av-hakase.com/public_html/meikan/.env"
if [ ! -f "$ENV_FILE" ]; then
    ENV_FILE="$HOME/repo/meikan/.env"
fi

if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: .env file not found on server" >&2
    exit 1
fi

DB_HOST=$(grep '^DB_HOST=' "$ENV_FILE" | cut -d= -f2)
DB_NAME=$(grep '^DB_NAME=' "$ENV_FILE" | cut -d= -f2)
DB_USER=$(grep '^DB_USER=' "$ENV_FILE" | cut -d= -f2)
DB_PASS=$(grep '^DB_PASS=' "$ENV_FILE" | cut -d= -f2)

# mysqldump実行（ストラクチャ + データ）
mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
    --single-transaction \
    --quick \
    --set-gtid-purged=OFF \
    2>/dev/null
REMOTE_SCRIPT

# ダンプの検証
if [ ! -s "$DUMP_FILE" ]; then
    echo "✗ ダンプファイルが空です。SSH接続やDB設定を確認してください。"
    rm -f "$DUMP_FILE"
    exit 1
fi

DUMP_SIZE=$(du -h "$DUMP_FILE" | cut -f1)
echo "  ✓ ダンプ取得完了 ($DUMP_SIZE)"

# 3. ローカルDBに反映
echo ""
echo "[2/3] ローカルDBにインポート..."
$MYSQL_CMD -e "DROP DATABASE IF EXISTS $LOCAL_DB; CREATE DATABASE $LOCAL_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$MYSQL_CMD "$LOCAL_DB" < "$DUMP_FILE"
echo "  ✓ インポート完了"

# 4. クリーンアップ
echo ""
echo "[3/3] クリーンアップ..."
rm -f "$DUMP_FILE"
echo "  ✓ 一時ファイル削除"

# レコード数を表示
echo ""
echo "=== 同期完了 ==="
echo ""
$MYSQL_CMD "$LOCAL_DB" -e "
SELECT '女優' AS テーブル, COUNT(*) AS 件数 FROM actresses
UNION ALL
SELECT 'ジャンル', COUNT(*) FROM genres
UNION ALL
SELECT '作品', COUNT(*) FROM works;
"
