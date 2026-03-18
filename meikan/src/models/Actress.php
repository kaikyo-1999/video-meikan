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

    public static function allForSitemap(): array
    {
        $db = Database::getInstance();
        return $db->query('SELECT slug, updated_at FROM actresses ORDER BY id')->fetchAll();
    }
}
