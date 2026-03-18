<?php

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}
require_once ROOT_DIR . '/config/app.php';
require_once ROOT_DIR . '/config/database.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/Cache.php';

// バッチ用ログ
function batchLog(string $message): void
{
    $logFile = LOG_DIR . '/batch_' . date('Y-m-d') . '.log';
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}
