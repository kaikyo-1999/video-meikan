<?php

class ApiController
{
    public function works(array $params): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $actressId = (int)($_GET['actress_id'] ?? 0);
        $genreId = (int)($_GET['genre_id'] ?? 0);

        if (!$actressId || !$genreId) {
            http_response_code(400);
            echo json_encode(['error' => 'actress_id and genre_id are required']);
            return;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $sort = $_GET['sort'] ?? '';
        $query = trim($_GET['q'] ?? '');
        $singleOnly = ($_GET['single'] ?? '0') === '1';
        $vrFilter = $_GET['vr'] ?? '';

        // バリデーション
        if (!in_array($sort, ['', 'rank', 'review', '-date'], true)) {
            $sort = '';
        }
        if (!in_array($vrFilter, ['', '2d', 'vr'], true)) {
            $vrFilter = '';
        }

        $total = Work::searchCount($actressId, $genreId, $query, $singleOnly, $vrFilter);
        $totalPages = max(1, (int)ceil($total / ITEMS_PER_PAGE));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * ITEMS_PER_PAGE;

        $works = Work::search($actressId, $genreId, $sort, $query, $singleOnly, $vrFilter, ITEMS_PER_PAGE, $offset);

        // work-card-horizontal パーシャルでHTML生成
        ob_start();
        foreach ($works as $work) {
            require TEMPLATE_DIR . '/partials/work-card-horizontal.php';
        }
        $html = ob_get_clean();

        echo json_encode([
            'html' => $html,
            'total' => $total,
            'total_pages' => $totalPages,
            'page' => $page,
        ], JSON_UNESCAPED_UNICODE);
    }
}
