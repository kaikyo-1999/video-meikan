<?php

class Actress
{
    public static function all(): array
    {
        $cacheKey = 'actresses_all';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->query('
            SELECT a.*, COUNT(aw.work_id) AS work_count
            FROM actresses a
            LEFT JOIN actress_work aw ON a.id = aw.actress_id
            WHERE a.thumbnail_url IS NOT NULL
              AND a.thumbnail_url != ""
              AND a.thumbnail_url NOT LIKE "%/digital/video/%"
              AND a.thumbnail_url NOT LIKE "%now_printing%"
            GROUP BY a.id
            ORDER BY work_count DESC, a.name ASC
        ');
        $result = $stmt->fetchAll();

        Cache::set($cacheKey, $result);
        return $result;
    }

    public static function findBySlug(string $slug): ?array
    {
        $cacheKey = 'actress_' . $slug;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT a.*, COUNT(aw.work_id) AS work_count
            FROM actresses a
            LEFT JOIN actress_work aw ON a.id = aw.actress_id
            WHERE a.slug = ?
            GROUP BY a.id
        ');
        $stmt->execute([$slug]);
        $result = $stmt->fetch() ?: null;

        if ($result) Cache::set($cacheKey, $result);
        return $result;
    }

    public static function getGenres(int $actressId): array
    {
        $cacheKey = 'actress_genres_' . $actressId;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT g.*, COUNT(DISTINCT w.id) AS work_count
            FROM genres g
            INNER JOIN work_genre wg ON g.id = wg.genre_id
            INNER JOIN works w ON wg.work_id = w.id
            INNER JOIN actress_work aw ON w.id = aw.work_id
            WHERE aw.actress_id = ?
            GROUP BY g.id
            HAVING work_count >= 3
            ORDER BY work_count DESC
        ');
        $stmt->execute([$actressId]);
        $result = $stmt->fetchAll();

        Cache::set($cacheKey, $result);
        return $result;
    }

    public static function count(): int
    {
        $db = Database::getInstance();
        return (int)$db->query('SELECT COUNT(*) FROM actresses')->fetchColumn();
    }

    public static function getSimilarActresses(int $actressId): array
    {
        $cacheKey = 'similar_actresses_' . $actressId;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT a.id, a.name, a.slug, a.thumbnail_url, sa.score
            FROM similar_actresses sa
            INNER JOIN actresses a ON sa.similar_actress_id = a.id
            WHERE sa.actress_id = ?
            ORDER BY sa.score DESC
            LIMIT 5
        ');
        $stmt->execute([$actressId]);
        $result = $stmt->fetchAll();

        Cache::set($cacheKey, $result);
        return $result;
    }

    /**
     * 関連女優を取得（タグ+デビュー時期ベース、作品少ない女優向け）
     * similar_actressesが空の場合のフォールバック
     */
    public static function getRelatedActresses(int $actressId): array
    {
        $cacheKey = 'related_actresses_' . $actressId;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();

        // テーブルが存在しない場合は空配列
        try {
            $stmt = $db->prepare('
                SELECT a.id, a.name, a.slug, a.thumbnail_url, ra.score
                FROM related_actresses ra
                INNER JOIN actresses a ON ra.related_actress_id = a.id
                WHERE ra.actress_id = ?
                ORDER BY ra.score DESC
                LIMIT 5
            ');
            $stmt->execute([$actressId]);
            $result = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $result = [];
        }

        Cache::set($cacheKey, $result);
        return $result;
    }

    public static function allForSitemap(): array
    {
        $db = Database::getInstance();
        return $db->query('SELECT slug, updated_at FROM actresses ORDER BY id')->fetchAll();
    }

    /**
     * 指定月にデビューした女優一覧を取得
     * @param string $yearMonth YYYY-MM形式
     */
    public static function findByDebutMonth(string $yearMonth): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT a.*, COUNT(aw.work_id) AS work_count
            FROM actresses a
            LEFT JOIN actress_work aw ON a.id = aw.actress_id
            WHERE DATE_FORMAT(a.debut_date, "%Y-%m") = ?
            GROUP BY a.id
            ORDER BY a.debut_date ASC, a.name ASC
        ');
        $stmt->execute([$yearMonth]);
        return $stmt->fetchAll();
    }

    /**
     * 直近Nヶ月以内にデビューした女優一覧を取得
     */
    public static function findRecentDebuts(int $months = 6): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT a.*, COUNT(aw.work_id) AS work_count
            FROM actresses a
            LEFT JOIN actress_work aw ON a.id = aw.actress_id
            WHERE a.debut_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY a.id
            ORDER BY a.debut_date DESC, a.name ASC
        ');
        $stmt->execute([$months]);
        return $stmt->fetchAll();
    }

    /**
     * 直近Nヶ月以内にデビューし、指定ジャンルの作品を持つ女優一覧
     */
    public static function findRecentDebutsByGenre(string $genreSlug, int $months = 6): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT a.*, COUNT(DISTINCT aw.work_id) AS work_count,
                   COUNT(DISTINCT wg.work_id) AS genre_work_count
            FROM actresses a
            INNER JOIN actress_work aw ON a.id = aw.actress_id
            INNER JOIN works w ON aw.work_id = w.id
            LEFT JOIN work_genre wg ON w.id = wg.work_id
            LEFT JOIN genres g ON wg.genre_id = g.id AND g.slug = ?
            WHERE a.debut_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
            GROUP BY a.id
            HAVING genre_work_count > 0
            ORDER BY genre_work_count DESC, a.debut_date DESC
        ');
        $stmt->execute([$genreSlug, $months]);
        return $stmt->fetchAll();
    }
}
