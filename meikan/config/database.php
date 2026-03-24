<?php

// .env.local があればそちらを優先（ローカル開発用）
$envFile = ROOT_DIR . '/.env.local';
if (!file_exists($envFile)) {
    $envFile = ROOT_DIR . '/.env';
}
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            putenv(trim($line));
        }
    }
}

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'video_meikan');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_SOCKET', getenv('DB_SOCKET') ?: '');
define('DB_CHARSET', 'utf8mb4');
