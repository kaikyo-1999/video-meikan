<?php

/**
 * HTMLエスケープ
 */
function h(?string $str): string
{
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * URL生成
 */
function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

/**
 * テンプレート描画
 */
function render(string $template, array $data = []): void
{
    extract($data);

    ob_start();
    require TEMPLATE_DIR . '/' . $template . '.php';
    $content = ob_get_clean();

    // layout.phpでラップ（sitemap等XML系は除外）
    if (isset($noLayout) && $noLayout) {
        echo $content;
    } else {
        require TEMPLATE_DIR . '/layout.php';
    }
}

/**
 * アセットURL生成
 */
function asset(string $path): string
{
    $filePath = ROOT_DIR . '/public/' . ltrim($path, '/');
    $ver = file_exists($filePath) ? filemtime($filePath) : '';
    return BASE_URL . 'public/' . ltrim($path, '/') . '?v=' . $ver;
}

/**
 * JSON-LD出力
 */
function jsonLd(array $data): string
{
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}

/**
 * ページネーション計算
 */
function paginate(int $total, int $perPage, int $currentPage): array
{
    $totalPages = max(1, (int)ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
    ];
}

/**
 * 現在のページ番号を取得
 */
function currentPage(): int
{
    return max(1, (int)($_GET['page'] ?? 1));
}

/**
 * サイトのフルURL生成
 */
function fullUrl(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url($path);
}

/**
 * 現在のリクエストURLのフルURL（canonical/og:url用）
 * REQUEST_URIからクエリ文字列を除いたパスを使う
 */
function currentFullUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    return $scheme . '://' . $host . $path;
}
