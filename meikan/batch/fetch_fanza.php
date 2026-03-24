<?php
/**
 * FANZA API データ取得バッチ
 * Usage: php batch/fetch_fanza.php
 */

require_once __DIR__ . '/config.php';

$apiId = getenv('FANZA_API_ID') ?: '47sPXfBnNCUgVfKabDPy';
$affiliateId = getenv('FANZA_AFFILIATE_ID');

if (!$affiliateId) {
    batchLog('ERROR: FANZA_AFFILIATE_ID が設定されていません。.envを確認してください。');
    exit(1);
}

$db = Database::getInstance();

// 全女優を取得
$actresses = $db->query('SELECT * FROM actresses ORDER BY id')->fetchAll();
batchLog("対象女優: " . count($actresses) . "名");

foreach ($actresses as $actress) {
    batchLog("処理開始: {$actress['name']}");
    $offset = 1;
    $totalFetched = 0;

    while (true) {
        $params = http_build_query([
            'api_id' => $apiId,
            'affiliate_id' => $affiliateId,
            'site' => 'FANZA',
            'service' => 'digital',
            'floor' => 'videoa',
            'hits' => 100,
            'sort' => 'date',
            'keyword' => $actress['name'],
            'offset' => $offset,
            'output' => 'json',
        ]);

        $url = 'https://api.dmm.com/affiliate/v3/ItemList?' . $params;
        $response = @file_get_contents($url);

        if ($response === false) {
            batchLog("  API エラー (offset={$offset})");
            break;
        }

        $data = json_decode($response, true);
        if (empty($data['result']['items'])) {
            batchLog("  データなし (offset={$offset})");
            break;
        }

        $items = $data['result']['items'];
        $totalCount = $data['result']['total_count'] ?? 0;

        foreach ($items as $item) {
            $sourceId = $item['content_id'] ?? ($item['product_id'] ?? '');
            if (!$sourceId) continue;

            // 作品の重複チェック & 挿入
            $existing = $db->prepare('SELECT id FROM works WHERE source = ? AND source_id = ?');
            $existing->execute(['fanza', $sourceId]);
            $workId = $existing->fetchColumn();

            // レビュー情報を抽出
            $reviewCount = isset($item['review']['count']) ? (int)$item['review']['count'] : null;
            $reviewAverage = isset($item['review']['average']) ? (float)$item['review']['average'] : null;

            if (!$workId) {
                $thumbnail = '';
                if (!empty($item['imageURL']['large'])) {
                    $thumbnail = $item['imageURL']['large'];
                } elseif (!empty($item['imageURL']['list'])) {
                    $thumbnail = $item['imageURL']['list'];
                }

                $affiliateUrl = $item['affiliateURL'] ?? ($item['URL'] ?? '');
                $affiliateUrl = str_replace('al.fanza.co.jp', 'al.dmm.co.jp', $affiliateUrl);
                $releaseDate = !empty($item['date']) ? date('Y-m-d', strtotime($item['date'])) : null;
                $label = '';
                if (!empty($item['iteminfo']['label'])) {
                    $label = $item['iteminfo']['label'][0]['name'] ?? '';
                }

                $stmt = $db->prepare('
                    INSERT INTO works (title, thumbnail_url, release_date, label, affiliate_url, review_count, review_average, source, source_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $item['title'] ?? '',
                    $thumbnail,
                    $releaseDate,
                    $label,
                    $affiliateUrl,
                    $reviewCount,
                    $reviewAverage,
                    'fanza',
                    $sourceId,
                ]);
                $workId = $db->lastInsertId();
                $totalFetched++;
            } else {
                // 既存レコードのレビュー情報を更新
                if ($reviewCount !== null || $reviewAverage !== null) {
                    $db->prepare('UPDATE works SET review_count = ?, review_average = ? WHERE id = ?')
                       ->execute([$reviewCount, $reviewAverage, $workId]);
                }
            }

            // 女優×作品の紐付け
            $db->prepare('INSERT IGNORE INTO actress_work (actress_id, work_id) VALUES (?, ?)')
               ->execute([$actress['id'], $workId]);

            // ジャンル処理
            if (!empty($item['iteminfo']['genre'])) {
                foreach ($item['iteminfo']['genre'] as $genreInfo) {
                    $genreId = $genreInfo['id'] ?? '';
                    $genreName = $genreInfo['name'] ?? '';
                    if (!$genreName) continue;

                    // ジャンルがDBに存在するか確認（fanza_genre_idまたはnameで）
                    $gStmt = $db->prepare('SELECT id FROM genres WHERE fanza_genre_id = ? OR name = ? LIMIT 1');
                    $gStmt->execute([$genreId, $genreName]);
                    $dbGenreId = $gStmt->fetchColumn();

                    if ($dbGenreId) {
                        // fanza_genre_idを更新
                        if ($genreId) {
                            $db->prepare('UPDATE genres SET fanza_genre_id = ? WHERE id = ? AND fanza_genre_id IS NULL')
                               ->execute([$genreId, $dbGenreId]);
                        }

                        // 作品×ジャンルの紐付け
                        $db->prepare('INSERT IGNORE INTO work_genre (work_id, genre_id) VALUES (?, ?)')
                           ->execute([$workId, $dbGenreId]);
                    }
                }
            }
        }

        $offset += count($items);
        if ($offset > $totalCount) break;

        // レートリミット対策
        usleep(500000); // 0.5秒
    }

    // 女優サムネイルは fetch_actress_profiles.php で設定する（作品画像を使わない）

    batchLog("  完了: 新規 {$totalFetched} 件取得");
    usleep(500000);
}

batchLog("全女優の処理完了");

// キャッシュクリア
Cache::clear();
batchLog("キャッシュクリア完了");
