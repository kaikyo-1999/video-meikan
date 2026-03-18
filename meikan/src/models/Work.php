<?php

class Work
{
    public static function findByActressAndGenre(
        int $actressId,
        int $genreId,
        int $limit = ITEMS_PER_PAGE,
        int $offset = 0
    ): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT w.*
            FROM works w
            INNER JOIN actress_work aw ON w.id = aw.work_id
            INNER JOIN work_genre wg ON w.id = wg.work_id
            WHERE aw.actress_id = ? AND wg.genre_id = ?
            ORDER BY w.release_date DESC, w.id DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->execute([$actressId, $genreId, $limit, $offset]);
        return $stmt->fetchAll();
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
}
