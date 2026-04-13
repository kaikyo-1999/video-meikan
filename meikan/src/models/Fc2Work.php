<?php

class Fc2Work
{
    /**
     * ランキング取得（投票数順）
     */
    public static function getRanking(string $period = 'all', int $limit = 20, int $offset = 0): array
    {
        $db = Database::getInstance();

        $periodJoin = '';
        if ($period === 'week') {
            $periodJoin = 'AND v.voted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        } elseif ($period === 'month') {
            $periodJoin = 'AND v.voted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        }

        $stmt = $db->prepare("
            SELECT w.*, COUNT(v.id) AS vote_count
            FROM fc2_works w
            LEFT JOIN fc2_votes v ON v.fc2_work_id = w.id {$periodJoin}
            WHERE w.is_approved = 1
            GROUP BY w.id
            ORDER BY vote_count DESC, w.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }

    /**
     * 承認済み件数
     */
    public static function countApproved(): int
    {
        $db = Database::getInstance();
        $stmt = $db->query('SELECT COUNT(*) FROM fc2_works WHERE is_approved = 1');
        return (int)$stmt->fetchColumn();
    }

    /**
     * CIDで検索
     */
    public static function findByCid(string $cid): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM fc2_works WHERE cid = ?');
        $stmt->execute([$cid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * IDで検索
     */
    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM fc2_works WHERE id = ? AND is_approved = 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * 新規登録
     */
    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO fc2_works (cid, title, thumbnail_url, price, duration, affiliate_url, is_approved, submitted_ip)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['cid'],
            $data['title'],
            $data['thumbnail_url'] ?? null,
            $data['price'] ?? 0,
            $data['duration'] ?? null,
            $data['affiliate_url'] ?? null,
            $data['is_approved'] ?? 0,
            $data['submitted_ip'] ?? null,
        ]);
        return (int)$db->lastInsertId();
    }

    /**
     * 投票（成功: true / 既に投票済み: false）
     */
    public static function vote(int $workId, string $ip): bool
    {
        $db = Database::getInstance();
        try {
            $stmt = $db->prepare('INSERT INTO fc2_votes (fc2_work_id, voter_ip) VALUES (?, ?)');
            $stmt->execute([$workId, $ip]);
            return true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return false; // UNIQUE制約違反 = 投票済み
            }
            throw $e;
        }
    }

    /**
     * 現在表示中のwork_idのうち、指定IPが投票済みのものを返す
     */
    public static function getVotedIds(array $workIds, string $ip): array
    {
        if (empty($workIds)) return [];
        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($workIds), '?'));
        $stmt = $db->prepare("
            SELECT fc2_work_id FROM fc2_votes
            WHERE fc2_work_id IN ({$placeholders}) AND voter_ip = ?
        ");
        $stmt->execute([...$workIds, $ip]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
