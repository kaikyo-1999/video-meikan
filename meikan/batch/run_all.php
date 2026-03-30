<?php
/**
 * バッチ一括実行スクリプト
 * 正しい順序でバッチを実行する（順序を変更しないこと）
 *
 * 実行順序:
 *   1. register_actresses.php  — 新規女優をDBに登録
 *   2. fetch_actress_profiles.php — 女優のプロフィール画像を取得
 *   3. fetch_fanza.php — 作品データを取得・紐付け
 *   4. assign_title_genres.php — タイトルベースのジャンル紐付け
 *   5. calculate_debut_dates.php — デビュー日を算出
 *   6. calculate_similar_actresses.php — 似ている女優を計算
 *   7. calculate_related_actresses.php — 関連女優を計算（タグ+デビュー時期ベース）
 *   8. clear_cache.php — キャッシュをクリア
 *
 * ⚠ 順序を守らないと、作品画像が女優サムネイルに設定される問題が再発します
 *
 * Usage: php batch/run_all.php [JSONファイルパス（新規女優登録用、省略可）]
 */

require_once __DIR__ . '/config.php';

batchLog('=== バッチ一括実行 開始 ===');

$batches = [
    'register_actresses.php',
    'fetch_actress_profiles.php',
    'fetch_fanza.php',
    'assign_title_genres.php',
    'calculate_debut_dates.php',
    'calculate_similar_actresses.php',
    'calculate_related_actresses.php',
    'clear_cache.php',
];

$jsonArg = $argv[1] ?? null;

foreach ($batches as $batch) {
    $path = __DIR__ . '/' . $batch;

    if (!file_exists($path)) {
        batchLog("SKIP: {$batch} が見つかりません");
        continue;
    }

    batchLog("--- {$batch} 実行開始 ---");

    $cmd = 'php ' . escapeshellarg($path);
    if ($batch === 'register_actresses.php' && $jsonArg) {
        $cmd .= ' ' . escapeshellarg($jsonArg);
    }

    $output = [];
    $returnCode = 0;
    exec($cmd . ' 2>&1', $output, $returnCode);

    foreach ($output as $line) {
        echo $line . PHP_EOL;
    }

    if ($returnCode !== 0) {
        batchLog("ERROR: {$batch} が異常終了しました (exit code: {$returnCode})");
        batchLog('=== バッチ一括実行 中断 ===');
        exit($returnCode);
    }

    batchLog("--- {$batch} 完了 ---");
}

batchLog('=== バッチ一括実行 完了 ===');
