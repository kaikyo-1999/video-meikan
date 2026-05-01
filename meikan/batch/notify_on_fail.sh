#!/bin/bash
# 任意のコマンドをラップして、Slack に成功/失敗通知を送るスクリプト。
# Slack Bot Token + chat.postMessage API 方式。
#
# 使い方:
#   notify_on_fail.sh <job_name> <command> [args...]
#
#   例:
#     notify_on_fail.sh update_prices /usr/bin/php /home/wp2026/av-hakase.com/public_html/batch/update_prices.php
#
# 必要な環境変数（.env から読む）:
#   SLACK_BOT_TOKEN — xoxb- で始まる Bot User OAuth Token
#   SLACK_CHANNEL   — チャンネル ID（C0XXXXXX）or #channel-name
#
# 失敗判定:
#   - コマンドの exit code が非ゼロ
#   - もしくは標準出力 / 標準エラーに "Fatal error" / "Uncaught" / "ERROR:" を含む
#
# 通知:
#   - 失敗時: 🚨 アラート + ログ末尾30行 + 失敗理由 + 所要時間
#   - 成功時: ✅ 「更新完了」メッセ + 所要時間 + ログから抽出した summary 行
#     （"更新: N件 / スキップ: M件" のような集計行を grep で拾う。
#      レコード単位の "名前: 34 件更新" は除外）
#   ※ ファイル名は notify_on_fail のままだが、成功時も投稿する仕様に拡張済み

set -u

JOB_NAME="${1:-unknown}"
shift || true

if [ $# -eq 0 ]; then
    echo "usage: $0 <job_name> <command> [args...]" >&2
    exit 64
fi

# プロジェクトの .env から SLACK_BOT_TOKEN / SLACK_CHANNEL のみ読む。
# .env の DB_PASS 等が特殊文字を含む場合、bash の source ではパースエラーに
# なるため、Slack 関連のキーだけを行単位で抽出して export する。
ENV_FILE="$(cd "$(dirname "$0")/.." && pwd)/.env"
if [ -f "$ENV_FILE" ]; then
    while IFS='=' read -r _key _value; do
        case "$_key" in
            SLACK_BOT_TOKEN|SLACK_CHANNEL)
                # 前後のクォートを除去
                _value="${_value%\"}"; _value="${_value#\"}"
                _value="${_value%\'}"; _value="${_value#\'}"
                export "$_key=$_value"
                ;;
        esac
    done < <(grep -E '^(SLACK_BOT_TOKEN|SLACK_CHANNEL)=' "$ENV_FILE" 2>/dev/null)
fi

LOG_DIR="${HOME}/cron_logs"
mkdir -p "$LOG_DIR"
LOG_FILE="${LOG_DIR}/${JOB_NAME}_$(date +%Y%m%d_%H%M%S).log"

START_TIME=$(date +%s)

# コマンド実行
"$@" > "$LOG_FILE" 2>&1
EXIT_CODE=$?

DURATION=$(( $(date +%s) - START_TIME ))

FAIL=0
REASON=""
if [ "$EXIT_CODE" -ne 0 ]; then
    FAIL=1
    REASON="exit code = ${EXIT_CODE}"
elif grep -qE 'Fatal error|Uncaught|^ERROR:|\[ERROR\]' "$LOG_FILE"; then
    FAIL=1
    REASON="ログにエラー検出"
fi

# 古いログを 30 日で自動削除
find "$LOG_DIR" -name "*.log" -type f -mtime +30 -delete 2>/dev/null

# 所要時間を人間向けにフォーマット
format_duration() {
    local sec="$1"
    if [ "$sec" -lt 60 ]; then
        echo "${sec}秒"
    elif [ "$sec" -lt 3600 ]; then
        echo "$((sec / 60))分$((sec % 60))秒"
    else
        echo "$((sec / 3600))時間$((sec % 3600 / 60))分"
    fi
}

# ログから summary 行（"更新: 12345 件 / ..." のような集計行）を抽出
# - 各レコード単位の "名前: 34 件更新" は除外
extract_highlights() {
    local log_file="$1"
    tail -80 "$log_file" \
        | grep -E "(更新|登録|追加|削除|スキップ|エラー|失敗|処理|新規|計算|完了): [0-9,]+|新規[^:]*: [0-9,]+ ?(人|本|件)|=== 完了 ===" \
        | grep -vE "件更新 *$" \
        | tail -8
}

post_to_slack() {
    local text="$1"

    if [ -z "${SLACK_BOT_TOKEN:-}" ] || [ -z "${SLACK_CHANNEL:-}" ]; then
        echo "[notify_on_fail] SLACK_BOT_TOKEN / SLACK_CHANNEL 未設定のため通知をスキップ" >&2
        return 1
    fi

    # PHP で JSON エスケープして payload を作る（jq に依存しない）
    local payload
    payload=$(SLACK_TEXT="$text" SLACK_CH="$SLACK_CHANNEL" php -r '
        echo json_encode([
            "channel" => getenv("SLACK_CH"),
            "text"    => getenv("SLACK_TEXT"),
        ], JSON_UNESCAPED_UNICODE);
    ')

    curl -s -X POST \
        -H "Authorization: Bearer ${SLACK_BOT_TOKEN}" \
        -H "Content-Type: application/json; charset=utf-8" \
        --data "$payload" \
        https://slack.com/api/chat.postMessage > /dev/null || true
}

HOST=$(hostname -s 2>/dev/null || echo "?")
DURATION_FMT=$(format_duration "$DURATION")

if [ "$FAIL" -eq 1 ]; then
    TAIL=$(tail -30 "$LOG_FILE")
    TEXT=":rotating_light: *${JOB_NAME}* failed on ${HOST} (${REASON} / ${DURATION_FMT})

\`\`\`
${TAIL}
\`\`\`

full log: ${LOG_FILE}"
    post_to_slack "$TEXT"
else
    HIGHLIGHTS=$(extract_highlights "$LOG_FILE")
    if [ -n "$HIGHLIGHTS" ]; then
        TEXT=":white_check_mark: *${JOB_NAME}* 更新完了 on ${HOST} (${DURATION_FMT})

\`\`\`
${HIGHLIGHTS}
\`\`\`"
    else
        TEXT=":white_check_mark: *${JOB_NAME}* 更新完了 on ${HOST} (${DURATION_FMT})"
    fi
    post_to_slack "$TEXT"
fi

exit "$EXIT_CODE"
