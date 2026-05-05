<?php

class Work
{
    /**
     * TOP「今週ホットな作品」用。work_signals.velocity_score 降順で取得。
     * ノイズ除去のため click_7d >= しきい値 のものに限定。
     * 該当が少ない場合は click_7d 単純降順にフォールバック。
     * 各作品に主演女優1名を付与。
     */
    public static function findHotByVelocity(int $limit = 10, int $minClicks = 3): array
    {
        $cacheKey = "hot_works_v_{$limit}_{$minClicks}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        // 1段目: GA4 集計済シグナルがあれば velocity 順
        $stmt = $db->prepare('
            SELECT w.id, w.title, w.thumbnail_url, w.affiliate_url, w.source_id,
                   s.click_7d, s.velocity_score
            FROM works w
            INNER JOIN work_signals s ON s.work_id = w.id
            WHERE s.click_7d >= ?
            ORDER BY s.velocity_score DESC, s.click_7d DESC
            LIMIT ?
        ');
        $stmt->execute([$minClicks, $limit]);
        $rows = $stmt->fetchAll();

        // 2段目: シグナルはあるが click 閾値未満 → click_7d 単純降順
        if (count($rows) < $limit) {
            $stmt2 = $db->prepare('
                SELECT w.id, w.title, w.thumbnail_url, w.affiliate_url, w.source_id,
                       s.click_7d, s.velocity_score
                FROM works w
                INNER JOIN work_signals s ON s.work_id = w.id
                ORDER BY s.click_7d DESC, w.release_date DESC
                LIMIT ?
            ');
            $stmt2->execute([$limit]);
            $rows = $stmt2->fetchAll();
        }

        // 3段目: work_signals 自体が空 (新規環境 / GA4 未集計) → 直近 90 日 + レビュー多い順
        if (empty($rows)) {
            $stmt3 = $db->prepare('
                SELECT id, title, thumbnail_url, affiliate_url, source_id,
                       0 AS click_7d, 0 AS velocity_score
                FROM works
                WHERE thumbnail_url IS NOT NULL AND thumbnail_url != ""
                  AND release_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                ORDER BY review_count DESC, release_date DESC
                LIMIT ?
            ');
            $stmt3->execute([$limit]);
            $rows = $stmt3->fetchAll();
        }

        $rows = self::attachLeadActress($rows);
        $rows = self::attachSecondarySample($rows);
        Cache::set($cacheKey, $rows, 1800); // 30分キャッシュ
        return $rows;
    }

    /**
     * TOP「FANZA セール中」用。works テーブル既存カラムから直接抽出。
     * 条件: sale_end_at が未来日 AND list_price > price (実際に値引きされている)
     * ソート: 割引率(%)降順 → 終了が近い順 (FOMO 訴求)
     */
    public static function findOnSale(int $limit = 10): array
    {
        $cacheKey = "sale_works_{$limit}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT id, title, thumbnail_url, affiliate_url, source_id,
                   price, list_price, sale_end_at, campaign_title,
                   ROUND((1 - price / list_price) * 100) AS discount_pct
            FROM works
            WHERE sale_end_at IS NOT NULL
              AND sale_end_at > NOW()
              AND price IS NOT NULL AND list_price IS NOT NULL
              AND list_price > price
              AND thumbnail_url IS NOT NULL AND thumbnail_url != ""
            ORDER BY discount_pct DESC, sale_end_at ASC
            LIMIT ?
        ');
        $stmt->execute([$limit]);
        $rows = $stmt->fetchAll();
        $rows = self::attachLeadActress($rows);
        $rows = self::attachSecondarySample($rows);

        Cache::set($cacheKey, $rows, 1800);
        return $rows;
    }

    /**
     * /sale ページ用: セール中の作品を検索 + ソート + ページネーション。
     * sort: '' (おすすめ=割引率高+終了近い) / 'ending_soon' / 'discount' / 'price_low' / 'price_high' / 'newest'
     */
    public static function searchSale(string $sort = '', string $query = '', int $limit = ITEMS_PER_PAGE, int $offset = 0): array
    {
        $db = Database::getInstance();

        $orderBy = match ($sort) {
            'ending_soon' => 'sale_end_at ASC',
            'discount'    => 'discount_pct DESC, sale_end_at ASC',
            'price_low'   => 'price ASC, discount_pct DESC',
            'price_high'  => 'price DESC, discount_pct DESC',
            'newest'      => 'release_date DESC',
            default       => 'discount_pct DESC, sale_end_at ASC',
        };

        $where = "sale_end_at IS NOT NULL
              AND sale_end_at > NOW()
              AND price IS NOT NULL AND list_price IS NOT NULL
              AND list_price > price
              AND thumbnail_url IS NOT NULL AND thumbnail_url != ''";
        $params = [];
        if ($query !== '') {
            $where .= ' AND title LIKE ?';
            $params[] = '%' . $query . '%';
        }
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare("
            SELECT id, title, thumbnail_url, affiliate_url, source_id,
                   price, list_price, sale_end_at, campaign_title, release_date, label,
                   ROUND((1 - price / list_price) * 100) AS discount_pct
            FROM works
            WHERE {$where}
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $rows = self::attachLeadActress($rows);
        $rows = self::attachSecondarySample($rows);
        return $rows;
    }

    public static function countSale(string $query = ''): int
    {
        $db = Database::getInstance();
        $where = "sale_end_at IS NOT NULL
              AND sale_end_at > NOW()
              AND price IS NOT NULL AND list_price IS NOT NULL
              AND list_price > price
              AND thumbnail_url IS NOT NULL AND thumbnail_url != ''";
        $params = [];
        if ($query !== '') {
            $where .= ' AND title LIKE ?';
            $params[] = '%' . $query . '%';
        }
        $stmt = $db->prepare("SELECT COUNT(*) FROM works WHERE {$where}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * 各 row に secondary_image (sort_order=1 のサンプル画像URL) を付与。
     * TOP の「ホット作品」「セール作品」サムネをパッケージ画像 → 2枚目のサンプルへ差し替えるための前処理。
     * 該当が無ければ secondary_image=null。partial 側で fallback する。
     */
    private static function attachSecondarySample(array $rows): array
    {
        if (empty($rows)) return $rows;
        $ids = array_column($rows, 'id');
        $samples = self::getSampleImagesBulk($ids);
        foreach ($rows as &$row) {
            $list = $samples[(int)$row['id']] ?? [];
            $row['secondary_image'] = $list[1] ?? null; // 2枚目（インデックス1）
        }
        unset($row);
        return $rows;
    }

    /**
     * work_signals テーブルの最新 updated_at を返す。
     * TOP「今週ホットな作品」セクションの「いつ更新したか」表示用。
     */
    public static function lastSignalUpdate(): ?string
    {
        $cacheKey = 'work_signals_last_update';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached !== '' ? $cached : null;

        $db = Database::getInstance();
        $result = $db->query('SELECT MAX(updated_at) FROM work_signals')->fetchColumn();
        Cache::set($cacheKey, $result ?: '', 600); // 10分キャッシュ
        return $result ?: null;
    }

    /**
     * 各作品に「主演女優1名」を付与する (id, name, slug)。
     * actress_work から最初の1人を選ぶ簡易版。
     */
    private static function attachLeadActress(array $rows): array
    {
        if (empty($rows)) return $rows;
        $ids = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT aw.work_id, a.id, a.name, a.slug
            FROM actress_work aw
            INNER JOIN actresses a ON a.id = aw.actress_id
            WHERE aw.work_id IN ({$placeholders})
            ORDER BY aw.work_id, a.id
        ");
        $stmt->execute($ids);
        $byWork = [];
        foreach ($stmt->fetchAll() as $r) {
            $wid = (int)$r['work_id'];
            if (!isset($byWork[$wid])) {
                $byWork[$wid] = ['id' => (int)$r['id'], 'name' => $r['name'], 'slug' => $r['slug']];
            }
        }
        foreach ($rows as &$row) {
            $row['lead_actress'] = $byWork[(int)$row['id']] ?? null;
        }
        unset($row);
        return $rows;
    }

    public static function findByActress(int $actressId): array
    {
        $cacheKey = 'works_actress_' . $actressId;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT w.*
            FROM works w
            INNER JOIN actress_work aw ON w.id = aw.work_id
            WHERE aw.actress_id = ?
            ORDER BY w.release_date DESC, w.id DESC
        ');
        $stmt->execute([$actressId]);
        $result = $stmt->fetchAll();

        Cache::set($cacheKey, $result);
        return $result;
    }

    public static function countAll(): int
    {
        $cacheKey = 'works_count_all';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return (int)$cached;

        $db = Database::getInstance();
        $result = (int)$db->query('SELECT COUNT(*) FROM works')->fetchColumn();
        Cache::set($cacheKey, $result, 86400);
        return $result;
    }

    public static function countByActress(int $actressId): int
    {
        $cacheKey = "work_count_actress_{$actressId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT COUNT(*)
            FROM actress_work
            WHERE actress_id = ?
        ');
        $stmt->execute([$actressId]);
        $result = (int)$stmt->fetchColumn();

        Cache::set($cacheKey, $result);
        return $result;
    }

    public static function findByActressPaginated(
        int $actressId,
        int $limit = ITEMS_PER_PAGE,
        int $offset = 0,
        bool $singleOnly = true
    ): array {
        $db = Database::getInstance();
        $where = 'aw.actress_id = ?';
        if ($singleOnly) {
            $where .= ' AND (SELECT COUNT(*) FROM actress_work aw2 WHERE aw2.work_id = w.id) = 1';
        }
        $stmt = $db->prepare("
            SELECT w.*
            FROM works w
            INNER JOIN actress_work aw ON w.id = aw.work_id
            WHERE {$where}
            ORDER BY CASE WHEN w.price IS NOT NULL AND w.list_price IS NOT NULL AND w.price < w.list_price THEN 0 ELSE 1 END, w.review_count DESC, w.release_date DESC, w.id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$actressId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    public static function findByActressAndGenre(
        int $actressId,
        int $genreId,
        int $limit = ITEMS_PER_PAGE,
        int $offset = 0,
        bool $singleOnly = true
    ): array {
        $db = Database::getInstance();
        $where = 'aw.actress_id = ? AND wg.genre_id = ?';
        if ($singleOnly) {
            $where .= ' AND (SELECT COUNT(*) FROM actress_work aw2 WHERE aw2.work_id = w.id) = 1';
        }
        $stmt = $db->prepare("
            SELECT w.*
            FROM works w
            INNER JOIN actress_work aw ON w.id = aw.work_id
            INNER JOIN work_genre wg ON w.id = wg.work_id
            WHERE {$where}
            ORDER BY CASE WHEN w.price IS NOT NULL AND w.list_price IS NOT NULL AND w.price < w.list_price THEN 0 ELSE 1 END, w.review_count DESC, w.release_date DESC, w.id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$actressId, $genreId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    public static function countSingleByActress(int $actressId): int
    {
        $cacheKey = "single_work_count_{$actressId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT COUNT(DISTINCT w.id)
            FROM works w
            INNER JOIN actress_work aw ON w.id = aw.work_id
            WHERE aw.actress_id = ?
              AND (SELECT COUNT(*) FROM actress_work aw2 WHERE aw2.work_id = w.id) = 1
        ');
        $stmt->execute([$actressId]);
        $result = (int)$stmt->fetchColumn();

        Cache::set($cacheKey, $result);
        return $result;
    }

    public static function countSingleByActressAndGenre(int $actressId, int $genreId): int
    {
        $cacheKey = "single_work_count_{$actressId}_{$genreId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT COUNT(DISTINCT w.id)
            FROM works w
            INNER JOIN actress_work aw ON w.id = aw.work_id
            INNER JOIN work_genre wg ON w.id = wg.work_id
            WHERE aw.actress_id = ? AND wg.genre_id = ?
              AND (SELECT COUNT(*) FROM actress_work aw2 WHERE aw2.work_id = w.id) = 1
        ');
        $stmt->execute([$actressId, $genreId]);
        $result = (int)$stmt->fetchColumn();

        Cache::set($cacheKey, $result);
        return $result;
    }

    public static function countByActressAndGenre(int $actressId, int $genreId): int
    {
        $cacheKey = "work_count_{$actressId}_{$genreId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT COUNT(DISTINCT w.id)
            FROM works w
            INNER JOIN actress_work aw ON w.id = aw.work_id
            INNER JOIN work_genre wg ON w.id = wg.work_id
            WHERE aw.actress_id = ? AND wg.genre_id = ?
        ');
        $stmt->execute([$actressId, $genreId]);
        $result = (int)$stmt->fetchColumn();

        Cache::set($cacheKey, $result);
        return $result;
    }

    public static function latestReleaseDateByActress(int $actressId): ?string
    {
        $cacheKey = "latest_release_actress_{$actressId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached === '' ? null : $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT MAX(w.release_date)
            FROM works w
            INNER JOIN actress_work aw ON w.id = aw.work_id
            WHERE aw.actress_id = ? AND w.release_date IS NOT NULL
        ');
        $stmt->execute([$actressId]);
        $result = $stmt->fetchColumn();
        $value = $result ?: null;

        Cache::set($cacheKey, $value ?? '');
        return $value;
    }

    public static function latestReleaseDateByActressAndGenre(int $actressId, int $genreId): ?string
    {
        $cacheKey = "latest_release_actress_genre_{$actressId}_{$genreId}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached === '' ? null : $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT MAX(w.release_date)
            FROM works w
            INNER JOIN actress_work aw ON w.id = aw.work_id
            INNER JOIN work_genre wg ON w.id = wg.work_id
            WHERE aw.actress_id = ? AND wg.genre_id = ? AND w.release_date IS NOT NULL
        ');
        $stmt->execute([$actressId, $genreId]);
        $result = $stmt->fetchColumn();
        $value = $result ?: null;

        Cache::set($cacheKey, $value ?? '');
        return $value;
    }

    public static function recentByActressAndGenre(int $actressId, int $genreId, int $limit = 10): array
    {
        $cacheKey = "works_recent_{$actressId}_{$genreId}_{$limit}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $result = self::findByActressAndGenre($actressId, $genreId, $limit, 0);

        Cache::set($cacheKey, $result);
        return $result;
    }

    /**
     * 女優ページ用: ソート・フィルター対応の作品取得（ジャンル指定なし）
     */
    public static function searchByActress(
        int $actressId,
        string $sort = '',
        string $query = '',
        bool $singleOnly = false,
        string $vrFilter = '',
        int $limit = ITEMS_PER_PAGE,
        int $offset = 0
    ): array {
        $db = Database::getInstance();

        $where = 'aw.actress_id = ?';
        $params = [$actressId];

        if ($query !== '') {
            $where .= ' AND w.title LIKE ?';
            $params[] = '%' . $query . '%';
        }

        if ($singleOnly) {
            $where .= ' AND (SELECT COUNT(*) FROM actress_work aw2 WHERE aw2.work_id = w.id) = 1';
        }

        if ($vrFilter === '2d') {
            $where .= ' AND w.title NOT LIKE ?';
            $params[] = '%【VR】%';
        } elseif ($vrFilter === 'vr') {
            $where .= ' AND w.title LIKE ?';
            $params[] = '%【VR】%';
        }

        $orderBy = match ($sort) {
            'rank'    => 'CASE WHEN w.price IS NOT NULL AND w.list_price IS NOT NULL AND w.price < w.list_price THEN 0 ELSE 1 END, w.review_count DESC, w.release_date DESC, w.id DESC',
            'review'  => 'w.review_average DESC, w.release_date DESC, w.id DESC',
            '-date'   => 'w.release_date ASC, w.id ASC',
            default   => 'w.release_date DESC, w.id DESC',
        };

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare("
            SELECT w.*
            FROM works w
            INNER JOIN actress_work aw ON w.id = aw.work_id
            WHERE {$where}
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 女優ページ用: フィルター対応の件数取得
     */
    public static function searchCountByActress(
        int $actressId,
        string $query = '',
        bool $singleOnly = false,
        string $vrFilter = ''
    ): int {
        $db = Database::getInstance();

        $where = 'aw.actress_id = ?';
        $params = [$actressId];

        if ($query !== '') {
            $where .= ' AND w.title LIKE ?';
            $params[] = '%' . $query . '%';
        }

        if ($singleOnly) {
            $where .= ' AND (SELECT COUNT(*) FROM actress_work aw2 WHERE aw2.work_id = w.id) = 1';
        }

        if ($vrFilter === '2d') {
            $where .= ' AND w.title NOT LIKE ?';
            $params[] = '%【VR】%';
        } elseif ($vrFilter === 'vr') {
            $where .= ' AND w.title LIKE ?';
            $params[] = '%【VR】%';
        }

        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT w.id)
            FROM works w
            INNER JOIN actress_work aw ON w.id = aw.work_id
            WHERE {$where}
        ");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * 複数作品のサンプル画像を一括取得（N+1防止）
     * @return array work_id => [image_url, ...]
     */
    public static function getSampleImagesBulk(array $workIds): array
    {
        if (empty($workIds)) return [];

        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($workIds), '?'));
        $stmt = $db->prepare("
            SELECT work_id, image_url
            FROM work_sample_images
            WHERE work_id IN ({$placeholders})
            ORDER BY work_id, sort_order
        ");
        $stmt->execute($workIds);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['work_id']][] = $row['image_url'];
        }
        return $result;
    }

    /**
     * 検索・ソート・フィルター対応の作品取得
     */
    public static function search(
        int $actressId,
        int $genreId,
        string $sort = '',
        string $query = '',
        bool $singleOnly = false,
        string $vrFilter = '',
        int $limit = ITEMS_PER_PAGE,
        int $offset = 0
    ): array {
        $db = Database::getInstance();

        $where = 'aw.actress_id = ? AND wg.genre_id = ?';
        $params = [$actressId, $genreId];

        if ($query !== '') {
            $where .= ' AND w.title LIKE ?';
            $params[] = '%' . $query . '%';
        }

        if ($singleOnly) {
            $where .= ' AND (SELECT COUNT(*) FROM actress_work aw2 WHERE aw2.work_id = w.id) = 1';
        }

        if ($vrFilter === '2d') {
            $where .= ' AND w.title NOT LIKE ?';
            $params[] = '%【VR】%';
        } elseif ($vrFilter === 'vr') {
            $where .= ' AND w.title LIKE ?';
            $params[] = '%【VR】%';
        }

        $orderBy = match ($sort) {
            'rank'    => 'CASE WHEN w.price IS NOT NULL AND w.list_price IS NOT NULL AND w.price < w.list_price THEN 0 ELSE 1 END, w.review_count DESC, w.release_date DESC, w.id DESC',
            'review'  => 'w.review_average DESC, w.release_date DESC, w.id DESC',
            '-date'   => 'w.release_date ASC, w.id ASC',
            default   => 'w.release_date DESC, w.id DESC',
        };

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare("
            SELECT w.*
            FROM works w
            INNER JOIN actress_work aw ON w.id = aw.work_id
            INNER JOIN work_genre wg ON w.id = wg.work_id
            WHERE {$where}
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * 検索・フィルター対応の件数取得
     */
    public static function searchCount(
        int $actressId,
        int $genreId,
        string $query = '',
        bool $singleOnly = false,
        string $vrFilter = ''
    ): int {
        $db = Database::getInstance();

        $where = 'aw.actress_id = ? AND wg.genre_id = ?';
        $params = [$actressId, $genreId];

        if ($query !== '') {
            $where .= ' AND w.title LIKE ?';
            $params[] = '%' . $query . '%';
        }

        if ($singleOnly) {
            $where .= ' AND (SELECT COUNT(*) FROM actress_work aw2 WHERE aw2.work_id = w.id) = 1';
        }

        if ($vrFilter === '2d') {
            $where .= ' AND w.title NOT LIKE ?';
            $params[] = '%【VR】%';
        } elseif ($vrFilter === 'vr') {
            $where .= ' AND w.title LIKE ?';
            $params[] = '%【VR】%';
        }

        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT w.id)
            FROM works w
            INNER JOIN actress_work aw ON w.id = aw.work_id
            INNER JOIN work_genre wg ON w.id = wg.work_id
            WHERE {$where}
        ");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
