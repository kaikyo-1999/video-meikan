<?php

class Work
{
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
