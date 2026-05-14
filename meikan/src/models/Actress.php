<?php

class Actress
{
    /**
     * サムネイル妥当性 SQL 述語。
     * 作品画像 (`/digital/video/`) が誤って女優サムネに混入したケース、および
     * `now_printing` プレースホルダを除外する。
     */
    public static function validThumbPredicate(string $alias = 'a'): string
    {
        $col = $alias === '' ? 'thumbnail_url' : $alias . '.thumbnail_url';
        return "{$col} IS NOT NULL AND {$col} != \"\" "
            . "AND {$col} NOT LIKE \"%/digital/video/%\" "
            . "AND {$col} NOT LIKE \"%now_printing%\"";
    }

    /**
     * 上記述語の PHP 側等価。配列 1 件に対するフィルタ用。
     */
    public static function hasValidThumbnail(array $row): bool
    {
        $url = $row['thumbnail_url'] ?? '';
        if ($url === '' || $url === null) return false;
        if (strpos($url, '/digital/video/') !== false) return false;
        if (strpos($url, 'now_printing') !== false) return false;
        return true;
    }

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
            WHERE ' . self::validThumbPredicate('a') . '
            GROUP BY a.id
            ORDER BY work_count DESC, a.name ASC
        ');
        $result = $stmt->fetchAll();

        Cache::set($cacheKey, $result, 86400 * 30);
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

    public static function findById(int $actressId): ?array
    {
        $cacheKey = 'actress_id_' . $actressId;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM actresses WHERE id = ?');
        $stmt->execute([$actressId]);
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

    /**
     * actress_signals テーブルの最新 updated_at を返す。
     * TOP「PV急上昇女優」セクションの「いつ更新したか」表示用。
     */
    public static function lastSignalUpdate(): ?string
    {
        $cacheKey = 'actress_signals_last_update';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached !== '' ? $cached : null;

        $db = Database::getInstance();
        $result = $db->query('SELECT MAX(updated_at) FROM actress_signals')->fetchColumn();
        Cache::set($cacheKey, $result ?: '', 600); // 10分キャッシュ
        return $result ?: null;
    }

    public static function count(): int
    {
        $cacheKey = 'actresses_count';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return (int)$cached;

        $db = Database::getInstance();
        $result = (int)$db->query('
            SELECT COUNT(*)
            FROM actresses
            WHERE ' . self::validThumbPredicate('') . '
        ')->fetchColumn();

        Cache::set($cacheKey, $result, 86400 * 30);
        return $result;
    }

    public static function getSimilarActresses(int $actressId): array
    {
        $cacheKey = 'similar_actresses_10_' . $actressId;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT a.id, a.name, a.slug, a.thumbnail_url, a.birthday, a.bust, a.waist, a.hip, sa.score
            FROM similar_actresses sa
            INNER JOIN actresses a ON sa.similar_actress_id = a.id
            WHERE sa.actress_id = ?
            ORDER BY sa.score DESC
            LIMIT 10
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
        $cacheKey = 'related_actresses_10_' . $actressId;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();

        // テーブルが存在しない場合は空配列
        try {
            $stmt = $db->prepare('
                SELECT a.id, a.name, a.slug, a.thumbnail_url, a.birthday, a.bust, a.waist, a.hip, ra.score
                FROM related_actresses ra
                INNER JOIN actresses a ON ra.related_actress_id = a.id
                WHERE ra.actress_id = ?
                ORDER BY ra.score DESC
                LIMIT 10
            ');
            $stmt->execute([$actressId]);
            $result = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $result = [];
        }

        Cache::set($cacheKey, $result);
        return $result;
    }

    /**
     * 逆引き関連女優を取得
     * 他の女優の similar_actresses / related_actresses に自分が含まれていれば、その女優を返す
     */
    public static function getReverseLookupActresses(int $actressId): array
    {
        $cacheKey = 'reverse_lookup_actresses_10_' . $actressId;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();

        try {
            // similar_actresses と related_actresses の両方を逆引きし、スコア降順で上位10件
            $stmt = $db->prepare('
                SELECT a.id, a.name, a.slug, a.thumbnail_url, a.birthday, a.bust, a.waist, a.hip, t.score
                FROM (
                    SELECT actress_id AS ref_id, score FROM similar_actresses WHERE similar_actress_id = ?
                    UNION ALL
                    SELECT actress_id AS ref_id, score FROM related_actresses WHERE related_actress_id = ?
                ) t
                INNER JOIN actresses a ON t.ref_id = a.id
                ORDER BY t.score DESC
                LIMIT 10
            ');
            $stmt->execute([$actressId, $actressId]);
            $result = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $result = [];
        }

        Cache::set($cacheKey, $result);
        return $result;
    }

    /**
     * 指定ジャンルの作品が多い女優を取得（サムネイルあり）
     */
    public static function findTopByGenre(string $genreSlug, int $limit = 6): array
    {
        $cacheKey = 'actresses_top_genre_' . $genreSlug . '_' . $limit;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT a.*, COUNT(DISTINCT aw.work_id) AS work_count,
                   COUNT(DISTINCT CASE WHEN g.slug = ? THEN wg.work_id END) AS genre_work_count
            FROM actresses a
            INNER JOIN actress_work aw ON a.id = aw.actress_id
            INNER JOIN works w ON aw.work_id = w.id
            LEFT JOIN work_genre wg ON w.id = wg.work_id
            LEFT JOIN genres g ON wg.genre_id = g.id
            WHERE ' . self::validThumbPredicate('a') . '
            GROUP BY a.id
            HAVING genre_work_count >= 3
            ORDER BY genre_work_count DESC, work_count DESC
            LIMIT ?
        ');
        $stmt->execute([$genreSlug, $limit]);
        $result = $stmt->fetchAll();

        Cache::set($cacheKey, $result);
        return $result;
    }

    /**
     * TOP「PV急上昇女優」用。actress_signals.pv_velocity_score 降順。
     * ノイズ除去のため sessions_7d >= しきい値 のものに限定。
     * 該当が少ない場合は sessions_7d 単純降順にフォールバック。
     * サムネイル必須・work URL のサムネ (誤データ) は除外。
     */
    public static function findHotByPv(int $limit = 6, int $minSessions = 10): array
    {
        $cacheKey = "hot_actresses_pv_{$limit}_{$minSessions}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT a.id, a.slug, a.name, a.thumbnail_url,
                   s.sessions_7d, s.pv_velocity_score,
                   COUNT(DISTINCT aw.work_id) AS work_count
            FROM actresses a
            INNER JOIN actress_signals s ON s.actress_id = a.id
            LEFT JOIN actress_work aw ON aw.actress_id = a.id
            WHERE ' . self::validThumbPredicate('a') . '
              AND s.sessions_7d >= ?
            GROUP BY a.id
            ORDER BY s.pv_velocity_score DESC, s.sessions_7d DESC
            LIMIT ?
        ');
        $stmt->execute([$minSessions, $limit]);
        $rows = $stmt->fetchAll();

        if (count($rows) < $limit) {
            $stmt2 = $db->prepare('
                SELECT a.id, a.slug, a.name, a.thumbnail_url,
                       s.sessions_7d, s.pv_velocity_score,
                       COUNT(DISTINCT aw.work_id) AS work_count
                FROM actresses a
                INNER JOIN actress_signals s ON s.actress_id = a.id
                LEFT JOIN actress_work aw ON aw.actress_id = a.id
                WHERE ' . self::validThumbPredicate('a') . '
                GROUP BY a.id
                ORDER BY s.sessions_7d DESC
                LIMIT ?
            ');
            $stmt2->execute([$limit]);
            $rows = $stmt2->fetchAll();
        }

        // 3段目: actress_signals 自体が空 (新規環境 / GA4 未集計) → 作品数多い順 (人気プロキシ)
        if (empty($rows)) {
            $stmt3 = $db->prepare('
                SELECT a.id, a.slug, a.name, a.thumbnail_url,
                       0 AS sessions_7d, 0 AS pv_velocity_score,
                       COUNT(DISTINCT aw.work_id) AS work_count
                FROM actresses a
                LEFT JOIN actress_work aw ON aw.actress_id = a.id
                WHERE ' . self::validThumbPredicate('a') . '
                GROUP BY a.id
                HAVING work_count >= 30
                ORDER BY work_count DESC, a.id DESC
                LIMIT ?
            ');
            $stmt3->execute([$limit]);
            $rows = $stmt3->fetchAll();
        }

        Cache::set($cacheKey, $rows, 1800);
        return $rows;
    }

    /**
     * 指定ジャンルで活動している人気女優を取得（PV急上昇ベース）。
     * findHotByPv() と同じ3段階フォールバック。指定 actress は除外。
     */
    public static function findHotByPvAndGenre(
        int $genreId,
        int $excludeActressId = 0,
        int $limit = 6,
        int $minSessions = 10
    ): array {
        $cacheKey = "hot_actresses_pv_genre_{$genreId}_{$excludeActressId}_{$limit}_{$minSessions}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $excludeClause = $excludeActressId > 0 ? ' AND a.id != ?' : '';

        // 第1段: pv_velocity_score 降順 + sessions_7d しきい値
        $stmt = $db->prepare('
            SELECT a.id, a.slug, a.name, a.thumbnail_url,
                   s.sessions_7d, s.pv_velocity_score,
                   (SELECT COUNT(DISTINCT aw2.work_id) FROM actress_work aw2 WHERE aw2.actress_id = a.id) AS work_count
            FROM actresses a
            INNER JOIN actress_signals s ON s.actress_id = a.id
            INNER JOIN actress_work aw ON aw.actress_id = a.id
            INNER JOIN work_genre wg ON wg.work_id = aw.work_id
            WHERE ' . self::validThumbPredicate('a') . '
              AND wg.genre_id = ?
              AND s.sessions_7d >= ?' . $excludeClause . '
            GROUP BY a.id
            ORDER BY s.pv_velocity_score DESC, s.sessions_7d DESC
            LIMIT ?
        ');
        $params = [$genreId, $minSessions];
        if ($excludeActressId > 0) $params[] = $excludeActressId;
        $params[] = $limit;
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // 第2段: sessions_7d 単純降順
        if (count($rows) < $limit) {
            $stmt2 = $db->prepare('
                SELECT a.id, a.slug, a.name, a.thumbnail_url,
                       s.sessions_7d, s.pv_velocity_score,
                       (SELECT COUNT(DISTINCT aw2.work_id) FROM actress_work aw2 WHERE aw2.actress_id = a.id) AS work_count
                FROM actresses a
                INNER JOIN actress_signals s ON s.actress_id = a.id
                INNER JOIN actress_work aw ON aw.actress_id = a.id
                INNER JOIN work_genre wg ON wg.work_id = aw.work_id
                WHERE ' . self::validThumbPredicate('a') . '
                  AND wg.genre_id = ?' . $excludeClause . '
                GROUP BY a.id
                ORDER BY s.sessions_7d DESC
                LIMIT ?
            ');
            $params2 = [$genreId];
            if ($excludeActressId > 0) $params2[] = $excludeActressId;
            $params2[] = $limit;
            $stmt2->execute($params2);
            $rows = $stmt2->fetchAll();
        }

        // 第3段: signals 不在 → ジャンル内作品数多い順
        if (empty($rows)) {
            $stmt3 = $db->prepare('
                SELECT a.id, a.slug, a.name, a.thumbnail_url,
                       0 AS sessions_7d, 0 AS pv_velocity_score,
                       COUNT(DISTINCT aw.work_id) AS work_count
                FROM actresses a
                INNER JOIN actress_work aw ON aw.actress_id = a.id
                INNER JOIN work_genre wg ON wg.work_id = aw.work_id
                WHERE ' . self::validThumbPredicate('a') . '
                  AND wg.genre_id = ?' . $excludeClause . '
                GROUP BY a.id
                HAVING work_count >= 5
                ORDER BY work_count DESC, a.id DESC
                LIMIT ?
            ');
            $params3 = [$genreId];
            if ($excludeActressId > 0) $params3[] = $excludeActressId;
            $params3[] = $limit;
            $stmt3->execute($params3);
            $rows = $stmt3->fetchAll();
        }

        Cache::set($cacheKey, $rows, 1800);
        return $rows;
    }

    /**
     * 指定女優の Top1 ジャンル（作品数最多）で人気な他女優を取得。
     * 女優ページの「人気女優」セクション用。
     */
    public static function findHotByPvFilteredByTopGenre(
        int $actressId,
        int $limit = 6,
        int $minSessions = 10
    ): array {
        $genres = self::getGenres($actressId);
        if (empty($genres)) return [];
        $topGenreId = (int)$genres[0]['id'];
        return self::findHotByPvAndGenre($topGenreId, $actressId, $limit, $minSessions);
    }

    /**
     * 新人女優セクション用（TOP・女優ページ・ジャンルページ共通）。
     * 「先月」を基本とし、データが無ければ DB 最新月にフォールバック。有効サムネのみ。
     * @return array{actresses: array, month: string, monthLabel: string, articleSlug: ?string}
     */
    public static function findRecentDebut(int $limit = 6): array
    {
        $latestMonth = date('Y-m', strtotime('first day of last month'));
        $actresses = self::findByDebutMonth($latestMonth);
        if (empty($actresses)) {
            $fallback = self::getLatestDebutMonth();
            if ($fallback && $fallback !== $latestMonth) {
                $latestMonth = $fallback;
                $actresses = self::findByDebutMonth($latestMonth);
            }
        }
        $actresses = array_values(array_filter($actresses, [self::class, 'hasValidThumbnail']));
        $actresses = array_slice($actresses, 0, $limit);
        $monthLabel = '';
        if ($latestMonth) {
            $parts = explode('-', $latestMonth);
            if (isset($parts[0], $parts[1])) {
                $monthLabel = (int)$parts[0] . '年' . (int)$parts[1] . '月';
            }
        }
        return [
            'actresses' => $actresses,
            'month' => $latestMonth,
            'monthLabel' => $monthLabel,
            'articleSlug' => $latestMonth ? ('shinjin-av-' . $latestMonth) : null,
        ];
    }

    /**
     * DBに存在する最新のデビュー月（YYYY-MM形式）を取得
     */
    public static function getLatestDebutMonth(): ?string
    {
        $cacheKey = 'latest_debut_month';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $db = Database::getInstance();
        $stmt = $db->query('
            SELECT DATE_FORMAT(MAX(debut_date), "%Y-%m") AS latest_month
            FROM actresses
            WHERE debut_date IS NOT NULL
        ');
        $result = $stmt->fetchColumn() ?: null;

        if ($result) Cache::set($cacheKey, $result);
        return $result;
    }

    public static function allForSitemap(): array
    {
        $db = Database::getInstance();
        return $db->query('SELECT slug, name, thumbnail_url, updated_at FROM actresses ORDER BY id')->fetchAll();
    }

    /**
     * 指定月にデビューした女優一覧を取得
     * @param string $yearMonth YYYY-MM形式
     */
    public static function findByDebutMonth(string $yearMonth): array
    {
        $cacheKey = 'actresses_debut_month_' . $yearMonth;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

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
        $result = $stmt->fetchAll();

        Cache::set($cacheKey, $result, 3600);
        return $result;
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
