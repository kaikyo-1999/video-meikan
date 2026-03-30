<?php
/**
 * FANZA API データ取得バッチ
 * Usage: php batch/fetch_fanza.php
 */

require_once __DIR__ . '/config.php';

$apiId = getenv('FANZA_API_ID');
$affiliateId = getenv('FANZA_AFFILIATE_ID');

if (!$apiId) {
    batchLog('ERROR: FANZA_API_ID が設定されていません。.envを確認してください。');
    exit(1);
}
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

                $displayAffiliateId = getenv('FANZA_DISPLAY_AFFILIATE_ID') ?: $affiliateId;
                $directUrl = 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=' . $sourceId . '/';
                $affiliateUrl = 'https://al.dmm.co.jp/?lurl=' . urlencode($directUrl) . '&af_id=' . $displayAffiliateId . '&ch=toolbar&ch_id=text';
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

                    // genre_fanza_mapping で紐付けされたジャンルを検索（複数ヒットあり得る）
                    $matchedGenreIds = [];
                    if ($genreId) {
                        $gStmt = $db->prepare('SELECT genre_id FROM genre_fanza_mapping WHERE fanza_genre_id = ?');
                        $gStmt->execute([$genreId]);
                        $matchedGenreIds = $gStmt->fetchAll(PDO::FETCH_COLUMN);
                    }

                    // マッピングになければ旧方式（genres.fanza_genre_id or name）でフォールバック
                    if (empty($matchedGenreIds)) {
                        $gStmt = $db->prepare('SELECT id FROM genres WHERE fanza_genre_id = ? OR name = ? LIMIT 1');
                        $gStmt->execute([$genreId, $genreName]);
                        $fallbackId = $gStmt->fetchColumn();
                        if ($fallbackId) {
                            $matchedGenreIds = [$fallbackId];
                        }
                    }

                    foreach ($matchedGenreIds as $dbGenreId) {
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
