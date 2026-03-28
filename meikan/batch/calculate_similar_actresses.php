<?php
/**
 * 似ている女優 計算バッチ
 * 各女優のジャンル分布をベクトル化し、コサイン類似度で類似女優を算出する
 * 作品10本以上の女優のみ対象、各女優の上位5件を保存
 *
 * Usage: php batch/calculate_similar_actresses.php
 */

require_once __DIR__ . '/config.php';

$db = Database::getInstance();

// --- Step 1: 作品10本以上の女優を取得 ---
batchLog('=== 似ている女優 計算開始 ===');

$actresses = $db->query('
    SELECT a.id, a.name
    FROM actresses a
    INNER JOIN actress_work aw ON a.id = aw.actress_id
    GROUP BY a.id
    HAVING COUNT(aw.work_id) >= 10
    ORDER BY a.id
')->fetchAll();

$actressCount = count($actresses);
batchLog("対象女優: {$actressCount}名（作品10本以上）");

if ($actressCount < 2) {
    batchLog('対象女優が2名未満のため終了');
    exit(0);
}

// --- Step 2: 各女優のジャンルベクトルを構築 ---
batchLog('ジャンルベクトル構築中...');

$stmt = $db->prepare('
    SELECT wg.genre_id, COUNT(DISTINCT w.id) AS work_count
    FROM works w
    INNER JOIN actress_work aw ON w.id = aw.work_id
    INNER JOIN work_genre wg ON w.id = wg.work_id
    WHERE aw.actress_id = ?
    GROUP BY wg.genre_id
');

$vectors = [];
$actressIds = [];

foreach ($actresses as $actress) {
    $stmt->execute([$actress['id']]);
    $genres = $stmt->fetchAll();

    if (empty($genres)) continue;

    // ジャンルベクトル: genre_id => 作品割合（正規化）
    $total = 0;
    $vec = [];
    foreach ($genres as $g) {
        $vec[$g['genre_id']] = (int)$g['work_count'];
        $total += (int)$g['work_count'];
    }
    // 割合に変換
    foreach ($vec as $gid => $count) {
        $vec[$gid] = $count / $total;
    }

    $vectors[$actress['id']] = $vec;
    $actressIds[] = $actress['id'];
}

$vectorCount = count($vectors);
batchLog("ベクトル構築完了: {$vectorCount}名");

// --- Step 3: コサイン類似度を計算 ---
batchLog('コサイン類似度計算中...');

// ノルムを事前計算
$norms = [];
foreach ($vectors as $id => $vec) {
    $sum = 0.0;
    foreach ($vec as $val) {
        $sum += $val * $val;
    }
    $norms[$id] = sqrt($sum);
}

// 各女優の上位5件を格納
$TOP_N = 5;
$results = [];

for ($i = 0; $i < count($actressIds); $i++) {
    $idA = $actressIds[$i];
    $vecA = $vectors[$idA];
    $normA = $norms[$idA];
    $scores = [];

    for ($j = 0; $j < count($actressIds); $j++) {
        if ($i === $j) continue;

        $idB = $actressIds[$j];
        $vecB = $vectors[$idB];
        $normB = $norms[$idB];

        // 内積
        $dot = 0.0;
        foreach ($vecA as $gid => $valA) {
            if (isset($vecB[$gid])) {
                $dot += $valA * $vecB[$gid];
            }
        }

        $denom = $normA * $normB;
        if ($denom > 0) {
            $scores[$idB] = $dot / $denom;
        }
    }

    // 上位5件を取得
    arsort($scores);
    $top = array_slice($scores, 0, $TOP_N, true);
    $results[$idA] = $top;
}

// --- Step 4: DBに保存（TRUNCATE→INSERT） ---
batchLog('DBに保存中...');

$db->exec('TRUNCATE TABLE similar_actresses');

$insertStmt = $db->prepare('
    INSERT INTO similar_actresses (actress_id, similar_actress_id, score)
    VALUES (?, ?, ?)
');

$insertCount = 0;
foreach ($results as $actressId => $similars) {
    foreach ($similars as $similarId => $score) {
        $insertStmt->execute([$actressId, $similarId, round($score, 4)]);
        $insertCount++;
    }
}

batchLog("保存完了: {$insertCount}件（{$vectorCount}名 × 最大{$TOP_N}件）");

// --- Step 5: キャッシュクリア ---
Cache::clear();
batchLog('キャッシュクリア完了');

batchLog('=== 似ている女優 計算完了 ===');
