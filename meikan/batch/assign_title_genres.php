<?php
/**
 * タイトルベースのジャンル紐付けバッチ
 * FANZA genre_idでカバーできないジャンルを、作品タイトルから判定して work_genre に紐付ける
 *
 * Usage: php batch/assign_title_genres.php
 */

require_once __DIR__ . '/config.php';

$db = Database::getInstance();

// タイトルベースで紐付けるジャンル定義
// slug => [タイトルに含むキーワード（OR条件）]
$titleGenres = [
    'vr'       => ['【VR】'],
    'chikubi'  => ['乳首'],
];

$totalAssigned = 0;

foreach ($titleGenres as $slug => $keywords) {
    // ジャンルIDを取得
    $stmt = $db->prepare('SELECT id FROM genres WHERE slug = ?');
    $stmt->execute([$slug]);
    $genreId = $stmt->fetchColumn();

    if (!$genreId) {
        batchLog("SKIP: ジャンル '{$slug}' がgenresテーブルに存在しません");
        continue;
    }

    // LIKE条件を構築
    $likeClauses = [];
    $params = [];
    foreach ($keywords as $kw) {
        $likeClauses[] = 'w.title LIKE ?';
        $params[] = '%' . $kw . '%';
    }
    $whereTitle = '(' . implode(' OR ', $likeClauses) . ')';

    // 未紐付けの作品を検索して紐付け
    $sql = "
        INSERT IGNORE INTO work_genre (work_id, genre_id)
        SELECT w.id, ?
        FROM works w
        WHERE {$whereTitle}
          AND NOT EXISTS (
              SELECT 1 FROM work_genre wg WHERE wg.work_id = w.id AND wg.genre_id = ?
          )
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge([$genreId], $params, [$genreId]));
    $assigned = $stmt->rowCount();

    $totalAssigned += $assigned;
    batchLog("ジャンル '{$slug}': {$assigned} 件紐付け");
}

batchLog("タイトルベース紐付け完了: 合計 {$totalAssigned} 件");

// キャッシュクリア
if ($totalAssigned > 0) {
    Cache::clear();
    batchLog("キャッシュクリア完了");
}
