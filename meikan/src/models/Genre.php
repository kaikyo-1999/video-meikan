<?php

class Genre
{
    public static function findBySlug(string $slug): ?array
    {
        $cacheKey = 'genre_' . $slug;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM genres WHERE slug = ?');
        $stmt->execute([$slug]);
        $result = $stmt->fetch() ?: null;

        if ($result) Cache::set($cacheKey, $result);
        return $result;
    }

    public static function allForActress(int $actressId): array
    {
        return Actress::getGenres($actressId);
    }

    public static function allSlugsForActress(int $actressId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT g.slug
            FROM genres g
            INNER JOIN work_genre wg ON g.id = wg.genre_id
            INNER JOIN works w ON wg.work_id = w.id
            INNER JOIN actress_work aw ON w.id = aw.work_id
            WHERE aw.actress_id = ?
            GROUP BY g.id
            HAVING COUNT(DISTINCT w.id) >= 3
        ');
        $stmt->execute([$actressId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
