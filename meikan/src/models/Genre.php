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

    /**
     * TOPページの「ジャンルから探す」タイル用。
     * FEATURED_GENRE_SLUGS に並んだ順に取り出し、各ジャンルに紐づく代表作品サムネを付与する。
     */
    public static function findFeatured(int $limit = 12): array
    {
        $slugs = defined('FEATURED_GENRE_SLUGS') ? FEATURED_GENRE_SLUGS : [];
        if (empty($slugs)) return [];

        $cacheKey = 'genre_featured_' . md5(implode(',', $slugs)) . '_' . $limit;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT g.id, g.slug, g.name,
                   COUNT(DISTINCT wg.work_id) AS work_count
            FROM genres g
            LEFT JOIN work_genre wg ON wg.genre_id = g.id
            WHERE g.slug IN ({$placeholders})
            GROUP BY g.id, g.slug, g.name
        ");
        $stmt->execute($slugs);
        $rows = $stmt->fetchAll();

        // slugs の順番に並べ替え
        $bySlug = [];
        foreach ($rows as $row) {
            $bySlug[$row['slug']] = $row;
        }
        $ordered = [];
        foreach ($slugs as $slug) {
            if (isset($bySlug[$slug])) $ordered[] = $bySlug[$slug];
            if (count($ordered) >= $limit) break;
        }

        // 代表作品サムネ (review_count DESC, release_date DESC) を1件ずつ付与
        if (!empty($ordered)) {
            $genreIds = array_column($ordered, 'id');
            $idPlaceholders = implode(',', array_fill(0, count($genreIds), '?'));
            $stmt2 = $db->prepare("
                SELECT wg.genre_id, w.thumbnail_url
                FROM work_genre wg
                INNER JOIN works w ON w.id = wg.work_id
                WHERE wg.genre_id IN ({$idPlaceholders})
                  AND w.thumbnail_url IS NOT NULL AND w.thumbnail_url != ''
                ORDER BY w.review_count DESC, w.release_date DESC
            ");
            $stmt2->execute($genreIds);
            $covers = [];
            foreach ($stmt2->fetchAll() as $row) {
                $gid = (int)$row['genre_id'];
                if (!isset($covers[$gid])) $covers[$gid] = $row['thumbnail_url'];
            }
            foreach ($ordered as &$g) {
                $g['cover_image'] = $covers[(int)$g['id']] ?? null;
            }
            unset($g);
        }

        Cache::set($cacheKey, $ordered);
        return $ordered;
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

    /**
     * 指定女優×各ジャンルの代表作品サムネを一括取得
     * @return array<int, string> [genreId => thumbnail_url]
     */
    public static function getCoverImagesForActress(int $actressId, array $genreIds): array
    {
        if (empty($genreIds)) return [];

        $cacheKey = 'genre_covers_' . $actressId . '_' . md5(implode(',', $genreIds));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $placeholders = implode(',', array_fill(0, count($genreIds), '?'));
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT wg.genre_id, w.thumbnail_url
            FROM work_genre wg
            INNER JOIN works w ON w.id = wg.work_id
            INNER JOIN actress_work aw ON aw.work_id = w.id
            WHERE aw.actress_id = ? AND wg.genre_id IN ({$placeholders})
              AND w.thumbnail_url IS NOT NULL AND w.thumbnail_url != ''
            ORDER BY w.review_count DESC, w.release_date DESC
        ");
        $stmt->execute(array_merge([$actressId], $genreIds));

        $covers = [];
        foreach ($stmt->fetchAll() as $row) {
            $gid = (int)$row['genre_id'];
            if (!isset($covers[$gid])) {
                $covers[$gid] = $row['thumbnail_url'];
            }
        }

        Cache::set($cacheKey, $covers);
        return $covers;
    }
}
