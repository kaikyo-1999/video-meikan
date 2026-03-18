<?php
/**
 * PHP Built-in Server Router (開発用)
 * Usage: php -S localhost:8000 dev-server.php
 */

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// 静的ファイルはそのまま配信
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $mimeTypes = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml'];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    readfile($file);
    return;
}

// src/, config/, batch/ 等へのアクセスを遮断
if (preg_match('#/(src|config|batch|logs|cache|sql)/#', $path)) {
    http_response_code(403);
    echo '403 Forbidden';
    return;
}
if (str_contains($path, '.env')) {
    http_response_code(403);
    echo '403 Forbidden';
    return;
}

require __DIR__ . '/index.php';
