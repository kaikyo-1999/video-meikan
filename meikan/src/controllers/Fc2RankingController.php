<?php

class Fc2RankingController
{
    private const PER_PAGE = 20;
    private const VALID_PERIODS = ['all', 'month', 'week'];

    public function index(array $params): void
    {
        $period = $_GET['period'] ?? 'all';
        if (!in_array($period, self::VALID_PERIODS, true)) {
            $period = 'all';
        }

        $page       = currentPage();
        $total      = Fc2Work::countApproved();
        $pagination = paginate($total, self::PER_PAGE, $page);
        $works      = Fc2Work::getRanking($period, self::PER_PAGE, $pagination['offset']);

        $ip       = $this->getClientIp();
        $workIds  = array_column($works, 'id');
        $votedIds = Fc2Work::getVotedIds($workIds, $ip);
        $votedSet = array_flip($votedIds);

        $periodLabels = ['all' => '全期間', 'month' => '月間', 'week' => '週間'];

        render('fc2_ranking', [
            'fc2Page'         => true,
            'pageTitle'       => 'FC2おすすめの7桁｜' . date('Y') . '年' . date('n') . '月最新ランキング | ' . SITE_NAME,
            'metaDescription' => date('Y') . '年' . date('n') . '月最新のFC2 7桁の数字おすすめランキング。ユーザー投票で選ばれた人気のFC2作品（7桁CID）を紹介。fc2 おすすめの7桁を探している方はこのページで確認できます。',
            'breadcrumbs'     => [
                ['label' => 'TOP', 'url' => ''],
                ['label' => 'FC2ランキング', 'url' => ''],
            ],
            'works'           => $works,
            'pagination'      => $pagination,
            'period'          => $period,
            'periodLabels'    => $periodLabels,
            'votedSet'        => $votedSet,
            'rankOffset'      => $pagination['offset'],
        ]);
    }

    public function submit(array $params): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->store();
            return;
        }

        render('fc2_submit', [
            'pageTitle'       => 'FC2作品を投稿 | ' . SITE_NAME,
            'metaDescription' => '面白いFC2作品のCIDを投稿してランキングに追加しよう。',
            'breadcrumbs'     => [
                ['label' => 'TOP', 'url' => ''],
                ['label' => 'FC2ランキング', 'url' => 'fc2/'],
                ['label' => '作品を投稿', 'url' => ''],
            ],
            'noindex' => true,
        ]);
    }

    public function vote(array $params): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            return;
        }

        $workId = isset($_POST['work_id']) ? (int)$_POST['work_id'] : 0;
        if ($workId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid work_id']);
            return;
        }

        $work = Fc2Work::findById($workId);
        if (!$work) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
            return;
        }

        $ip      = $this->getClientIp();
        $success = Fc2Work::vote($workId, $ip);

        // 最新の投票数を返す
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM fc2_votes WHERE fc2_work_id = ?');
        $stmt->execute([$workId]);
        $voteCount = (int)$stmt->fetchColumn();

        echo json_encode([
            'success'    => $success,
            'already'    => !$success,
            'vote_count' => $voteCount,
        ]);
    }

    // ---------- private ----------

    private function store(): void
    {
        $cid = trim($_POST['cid'] ?? '');
        if (!preg_match('/^\d{7}$/', $cid)) {
            render('fc2_submit', [
                'pageTitle'   => 'FC2作品を投稿 | ' . SITE_NAME,
                'breadcrumbs' => [
                    ['label' => 'TOP', 'url' => ''],
                    ['label' => 'FC2ランキング', 'url' => 'fc2/'],
                    ['label' => '作品を投稿', 'url' => ''],
                ],
                'error'   => 'CIDは7桁の数字で入力してください。',
                'noindex' => true,
            ]);
            return;
        }

        if (Fc2Work::findByCid($cid)) {
            render('fc2_submit', [
                'pageTitle'   => 'FC2作品を投稿 | ' . SITE_NAME,
                'breadcrumbs' => [
                    ['label' => 'TOP', 'url' => ''],
                    ['label' => 'FC2ランキング', 'url' => 'fc2/'],
                    ['label' => '作品を投稿', 'url' => ''],
                ],
                'error'   => 'この作品はすでに登録されています。',
                'noindex' => true,
            ]);
            return;
        }

        $meta = $this->fetchFc2Meta($cid);
        if (!$meta) {
            render('fc2_submit', [
                'pageTitle'   => 'FC2作品を投稿 | ' . SITE_NAME,
                'breadcrumbs' => [
                    ['label' => 'TOP', 'url' => ''],
                    ['label' => 'FC2ランキング', 'url' => 'fc2/'],
                    ['label' => '作品を投稿', 'url' => ''],
                ],
                'error'   => 'FC2ページからの情報取得に失敗しました。CIDを確認してください。',
                'noindex' => true,
            ]);
            return;
        }

        Fc2Work::create([
            'cid'           => $cid,
            'title'         => $meta['title'],
            'thumbnail_url' => $meta['thumbnail_url'],
            'price'         => $meta['price'] ?? 0,
            'duration'      => $meta['duration'] ?? null,
            'is_approved'   => 1,
            'submitted_ip'  => $this->getClientIp(),
        ]);

        render('fc2_submit', [
            'pageTitle'   => 'FC2作品を投稿 | ' . SITE_NAME,
            'breadcrumbs' => [
                ['label' => 'TOP', 'url' => ''],
                ['label' => 'FC2ランキング', 'url' => 'fc2/'],
                ['label' => '作品を投稿', 'url' => ''],
            ],
            'success' => '投稿を受け付けました。ランキングに追加されました。',
            'noindex' => true,
        ]);
    }

    /**
     * FC2コンテンツページからタイトル・サムネイル等を取得
     */
    private function fetchFc2Meta(string $cid): ?array
    {
        $url = 'https://adult.contents.fc2.com/article/' . $cid . '/';
        $ctx = stream_context_create(['http' => [
            'timeout'          => 10,
            'follow_location'  => 1,
            'max_redirects'    => 3,
            'user_agent'       => 'Mozilla/5.0 (compatible; AV-hakase-bot/1.0)',
        ]]);

        $html = @file_get_contents($url, false, $ctx);
        if ($html === false) return null;

        // 商品なしページの検出
        if (strpos($html, 'お探しの商品が見つかりませんでした') !== false) return null;

        // タイトル
        if (!preg_match('/<title[^>]*>([^<]+)</i', $html, $m)) return null;
        $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        // サイト名部分を除去
        $title = preg_replace('/\s*[-|｜]\s*FC2.*/u', '', $title);
        if (empty($title)) return null;

        // サムネイル（og:image）
        $thumbnail = null;
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/', $html, $tm)) {
            $thumbnail = $tm[1];
        }

        // 価格（円表記から数値抽出）
        $price = 0;
        if (preg_match('/¥\s*([\d,]+)/', $html, $pm)) {
            $price = (int)str_replace(',', '', $pm[1]);
        }

        // 再生時間（分）
        $duration = null;
        if (preg_match('/(\d+)\s*分/', $html, $dm)) {
            $duration = (int)$dm[1];
        }

        $thumbnail_url = $thumbnail;
        return compact('title', 'thumbnail_url', 'price', 'duration');
    }

    private function getClientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }
}
