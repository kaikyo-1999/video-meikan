#!/bin/bash
# ローカル開発環境セットアップスクリプト
# Usage: bash scripts/setup-local.sh

set -e

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
MEIKAN_DIR="$PROJECT_DIR/meikan"

MYSQL_SOCKET="/tmp/mysql_meikan.sock"
MYSQL_CMD="mysql -u root -S $MYSQL_SOCKET"

echo "=== video-meikan ローカル開発環境セットアップ ==="
echo ""

# 1. MySQL起動確認・起動
echo "[1/4] MySQL起動確認..."
if mysqladmin ping -u root -S "$MYSQL_SOCKET" 2>/dev/null | grep -q "alive"; then
    echo "  ✓ MySQL は起動済みです（socket: $MYSQL_SOCKET）"
else
    echo "  → MySQL を起動します..."
    # まず通常のbrewサービスで試す
    brew services start mysql 2>/dev/null || true
    sleep 2

    # それでもダメならmysqld_safeで直接起動
    if ! mysqladmin ping -u root -S "$MYSQL_SOCKET" 2>/dev/null | grep -q "alive"; then
        echo "  → mysqld_safe で直接起動..."
        /opt/homebrew/opt/mysql/bin/mysqld_safe \
            --datadir=/opt/homebrew/var/mysql \
            --socket="$MYSQL_SOCKET" &
        for i in {1..15}; do
            if mysqladmin ping -u root -S "$MYSQL_SOCKET" 2>/dev/null | grep -q "alive"; then
                break
            fi
            sleep 1
        done
    fi

    if mysqladmin ping -u root -S "$MYSQL_SOCKET" 2>/dev/null | grep -q "alive"; then
        echo "  ✓ MySQL 起動完了"
    else
        echo "  ✗ MySQL の起動に失敗しました"
        echo "    手動で確認: ps aux | grep mysqld"
        exit 1
    fi
fi

# 2. データベース作成 + スキーマ適用
echo ""
echo "[2/4] データベースセットアップ..."
if $MYSQL_CMD -e "USE video_meikan" 2>/dev/null; then
    ACTRESS_COUNT=$($MYSQL_CMD -N -e "SELECT COUNT(*) FROM video_meikan.actresses" 2>/dev/null || echo "0")
    echo "  ✓ データベース video_meikan は存在します（女優: ${ACTRESS_COUNT}件）"
    read -p "  スキーマを再適用しますか？（データは消えます） [y/N]: " answer
    if [[ "$answer" =~ ^[Yy]$ ]]; then
        $MYSQL_CMD -e "DROP DATABASE video_meikan"
        $MYSQL_CMD < "$MEIKAN_DIR/sql/schema.sql"
        echo "  ✓ スキーマ再適用完了"
    fi
else
    $MYSQL_CMD < "$MEIKAN_DIR/sql/schema.sql"
    echo "  ✓ データベース作成 + スキーマ適用完了"
fi

# 3. .env.local ファイル作成
echo ""
echo "[3/4] .env.local ファイル確認..."
if [ -f "$MEIKAN_DIR/.env.local" ]; then
    echo "  ✓ .env.local は存在します（既存のものを使用）"
else
    cat > "$MEIKAN_DIR/.env.local" << EOF
DB_HOST=localhost
DB_NAME=video_meikan
DB_USER=root
DB_PASS=
DB_SOCKET=$MYSQL_SOCKET

FANZA_API_ID=your_api_id_here
FANZA_AFFILIATE_ID=your_affiliate_id_here
EOF
    echo "  ✓ .env.local を作成しました（APIキーは手動で設定してください）"
fi

# 4. 必要なディレクトリ作成
echo ""
echo "[4/4] ディレクトリ確認..."
mkdir -p "$MEIKAN_DIR/cache" "$MEIKAN_DIR/logs"
echo "  ✓ cache/, logs/ ディレクトリ確認完了"

# 完了
echo ""
echo "=== セットアップ完了 ==="
echo ""
echo "開発サーバー起動:"
echo "  cd meikan && php -S localhost:8000 dev-server.php"
echo ""
echo "本番DBからデータ同期:"
echo "  bash scripts/sync-db.sh"
echo ""
echo "ブラウザで確認:"
echo "  http://localhost:8000/"
