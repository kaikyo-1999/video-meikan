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
 * FANZA画像URL の末尾サイズ指定子を変換
 * 例: .../118abf00177pl.jpg → .../118abf00177ps.jpg
 *
 * 対応サイズ: pl(大) / ps(中) / pt(小)
 * パターンに一致しないURL（女優プロフィール画像など）はそのまま返す。
 */
function fanzaImg(?string $url, string $size = 'ps'): string
{
    if (empty($url)) return '';
    if (!in_array($size, ['pl', 'ps', 'pt'], true)) $size = 'ps';
    return preg_replace('#([a-z0-9_]+)pl\.jpg(\?.*)?$#i', '${1}' . $size . '.jpg${2}', $url) ?? $url;
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

/**
 * 発売日が直近3ヶ月以内なら "[YYYY年M月最新作収録]" 形式で返す。範囲外/null は空文字。
 */
function latestReleaseTag(?string $releaseDate): string
{
    $month = latestReleaseMonth($releaseDate);
    return $month === '' ? '' : "[{$month}最新作収録]";
}

/**
 * 発売日が直近3ヶ月以内なら "YYYY年M月" 形式で返す。範囲外/null は空文字。
 */
function latestReleaseMonth(?string $releaseDate): string
{
    if (!$releaseDate) return '';
    $threshold = (new DateTime())->modify('-3 months')->format('Y-m-d');
    if ($releaseDate < $threshold) return '';
    $dt = new DateTime($releaseDate);
    return sprintf('%s年%d月', $dt->format('Y'), (int)$dt->format('m'));
}

/**
 * 任意の日時文字列を「X分前 / X時間前 / X日前 / YYYY/M/D」に整形する。
 * シグナル系セクションの「いつ更新したか」表示用。
 */
function formatRelativeTime(?string $dt): string
{
    if (!$dt) return '';
    $ts = strtotime($dt);
    if (!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60) return 'たった今'; // 未来時刻 ($diff < 0) もここでカバー
    if ($diff < 3600) return floor($diff / 60) . '分前';
    if ($diff < 86400) return floor($diff / 3600) . '時間前';
    if ($diff < 7 * 86400) return floor($diff / 86400) . '日前';
    return date('Y/n/j', $ts);
}
