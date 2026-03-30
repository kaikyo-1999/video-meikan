<?php
/**
 * 関連女優計算スクリプト（タグ + デビュー時期ベース）
 *
 * 既存の calculate_similar_actresses.php（コサイン類似度、作品10本以上）とは独立。
 * 作品10本未満の女優を対象に、以下の基準で関連女優を算出する:
 *   - ジャンルタグの一致数
 *   - デビュー時期の近さ（±6ヶ月以内にボーナス）
 *
 * 結果は related_actresses テーブルに保存。
 *
 * Usage: php batch/calculate_related_actresses.php
 */

require_once __DIR__ . '/config.php';

batchLog('=== 関連女優計算（タグ+デビュー時期）開始 ===');

$db = Database::getInstance();

// similar_actressesに既にデータがある女優（作品10本以上）は除外
$alreadyHasSimilar = $db->query('SELECT DISTINCT actress_id FROM similar_actresses')->fetchAll(PDO::FETCH_COLUMN);
$hasSimilarSet = array_flip($alreadyHasSimilar);
batchLog("既存の似ている女優データ: " . count($alreadyHasSimilar) . " 名");

// 全女優を取得（作品数とデビュー日付き）
$actresses = $db->query('
    SELECT a.id, a.name, a.debut_date, COUNT(aw.work_id) AS work_count
    FROM actresses a
    LEFT JOIN actress_work aw ON a.id = aw.actress_id
    GROUP BY a.id
    ORDER BY a.id
')->fetchAll();

batchLog("全女優数: " . count($actresses) . " 名");

// 対象: similar_actressesにデータがない女優
$targets = [];
$allActresses = []; // id => actress（全女優分、関連候補用）
foreach ($actresses as $a) {
    $allActresses[$a['id']] = $a;
    if (!isset($hasSimilarSet[$a['id']])) {
        $targets[] = $a;
    }
}
batchLog("計算対象（似ている女優なし）: " . count($targets) . " 名");

if (empty($targets)) {
    batchLog("対象なし — 全女優に似ている女優データが存在します");
    batchLog('=== 関連女優計算 完了 ===');
    exit(0);
}

// 全女優のジャンル情報を取得
$genreStmt = $db->prepare('
    SELECT g.id
    FROM actress_work aw
    INNER JOIN work_genre wg ON aw.work_id = wg.work_id
    INNER JOIN genres g ON wg.genre_id = g.id
    WHERE aw.actress_id = ?
');

$actressGenres = []; // actress_id => [genre_id => count]
foreach ($allActresses as $id => $a) {
    $genreStmt->execute([$id]);
    $genres = $genreStmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($genres)) {
        $counts = array_count_values($genres);
        $actressGenres[$id] = $counts;
    }
}
batchLog("ジャンル情報取得完了: " . count($actressGenres) . " 名");

// 関連スコア計算
$insertData = [];

foreach ($targets as $target) {
    $tid = $target['id'];
    $tGenres = $actressGenres[$tid] ?? [];
    $tDebut = $target['debut_date'] ? strtotime($target['debut_date']) : null;

    $scores = [];

    foreach ($allActresses as $cid => $candidate) {
        if ($cid === $tid) continue;

        $cGenres = $actressGenres[$cid] ?? [];

        // ジャンルタグ一致スコア（共通ジャンル数 / 最大ジャンル数）
        $genreScore = 0.0;
        if (!empty($tGenres) && !empty($cGenres)) {
            $commonGenres = array_intersect_key($tGenres, $cGenres);
            $totalGenres = max(count($tGenres), count($cGenres));
            $genreScore = count($commonGenres) / $totalGenres;
        }

        // デビュー時期ボーナス（±6ヶ月以内で最大0.3加算）
        $debutBonus = 0.0;
        $cDebut = $candidate['debut_date'] ? strtotime($candidate['debut_date']) : null;
        if ($tDebut && $cDebut) {
            $diffMonths = abs($tDebut - $cDebut) / (30 * 86400);
            if ($diffMonths <= 6) {
                $debutBonus = 0.3 * (1 - $diffMonths / 6);
            }
        }

        $totalScore = $genreScore + $debutBonus;
        if ($totalScore > 0.01) {
            $scores[$cid] = $totalScore;
        }
    }

    // 上位5件を保存
    arsort($scores);
    $top5 = array_slice($scores, 0, 5, true);

    foreach ($top5 as $relatedId => $score) {
        $insertData[] = [$tid, $relatedId, round($score, 4)];
    }
}

batchLog("計算完了: " . count($insertData) . " 件のペア");

// テーブルがなければ作成
$db->exec('
    CREATE TABLE IF NOT EXISTS related_actresses (
        actress_id INT UNSIGNED NOT NULL,
        related_actress_id INT UNSIGNED NOT NULL,
        score DECIMAL(5,4) NOT NULL,
        PRIMARY KEY (actress_id, related_actress_id),
        INDEX idx_actress_score (actress_id, score DESC),
        FOREIGN KEY (actress_id) REFERENCES actresses(id) ON DELETE CASCADE,
        FOREIGN KEY (related_actress_id) REFERENCES actresses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
');

// 既存データ削除 → 再挿入
$db->exec('TRUNCATE TABLE related_actresses');

$insertStmt = $db->prepare('INSERT INTO related_actresses (actress_id, related_actress_id, score) VALUES (?, ?, ?)');
$insertCount = 0;
foreach ($insertData as $row) {
    $insertStmt->execute($row);
    $insertCount++;
}

batchLog("保存完了: {$insertCount} 件");

// キャッシュクリア（related_actressesに関連するキーのみ — 一括クリアで安全に）
Cache::clear();
batchLog("キャッシュクリア完了");

batchLog('=== 関連女優計算 完了 ===');
