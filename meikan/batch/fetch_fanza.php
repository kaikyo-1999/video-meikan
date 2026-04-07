<?php
/**
 * FANZA API データ取得バッチ
 * Usage: php batch/fetch_fanza.php
 */

require_once __DIR__ . '/config.php';

/**
 * 女優名が一致するか判定（括弧内の別名も照合）
 * fetch_actress_profiles.php と同じロジック
 */
function actressNameMatchesForWork(string $apiName, string $dbName): bool
{
    if ($apiName === $dbName) return true;

    if (preg_match('/^(.+?)\s*（(.+?)）$/', $apiName, $m)) {
        $mainName = trim($m[1]);
        if ($mainName === $dbName) return true;

        $aliases = preg_split('/[、,]\s*/', $m[2]);
        foreach ($aliases as $alias) {
            if (trim($alias) === $dbName) return true;
        }
    }
    return false;
}

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

            // 価格情報を抽出（downloadタイプを優先、なければトップレベル）
            $price = null;
            $listPrice = null;
            if (!empty($item['prices'])) {
                $deliveries = $item['prices']['deliveries']['delivery'] ?? [];
                foreach ($deliveries as $d) {
                    if (($d['type'] ?? '') === 'download') {
                        $price = (int)preg_replace('/[^0-9]/', '', $d['price']);
                        $listPrice = (int)preg_replace('/[^0-9]/', '', $d['list_price']);
                        break;
                    }
                }
                if ($price === null && isset($item['prices']['price'])) {
                    $price = (int)preg_replace('/[^0-9]/', '', $item['prices']['price']);
                    $listPrice = (int)preg_replace('/[^0-9]/', '', $item['prices']['list_price'] ?? '');
                }
            }

            // サンプル動画URL（最大サイズを優先）
            $sampleMovieUrl = null;
            if (!empty($item['sampleMovieURL'])) {
                $movieSizes = ['size_720_480', 'size_644_414', 'size_560_360', 'size_476_306'];
                foreach ($movieSizes as $size) {
                    if (!empty($item['sampleMovieURL'][$size])) {
                        $sampleMovieUrl = $item['sampleMovieURL'][$size];
                        break;
                    }
                }
            }

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
                    INSERT INTO works (title, thumbnail_url, release_date, label, affiliate_url, review_count, review_average, sample_movie_url, price, list_price, price_updated_at, source, source_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
                ');
                $stmt->execute([
                    $item['title'] ?? '',
                    $thumbnail,
                    $releaseDate,
                    $label,
                    $affiliateUrl,
                    $reviewCount,
                    $reviewAverage,
                    $sampleMovieUrl,
                    $price,
                    $listPrice,
                    'fanza',
                    $sourceId,
                ]);
                $workId = $db->lastInsertId();
                $totalFetched++;
            } else {
                // 既存レコードのレビュー・動画・価格情報を更新
                $db->prepare('UPDATE works SET review_count = COALESCE(?, review_count), review_average = COALESCE(?, review_average), sample_movie_url = COALESCE(?, sample_movie_url), price = ?, list_price = ?, price_updated_at = NOW() WHERE id = ?')
                   ->execute([$reviewCount, $reviewAverage, $sampleMovieUrl, $price, $listPrice, $workId]);
            }

            // サンプル画像の保存
            if (!empty($item['sampleImageURL']['sample_l']['image'])) {
                $existingSamples = $db->prepare('SELECT COUNT(*) FROM work_sample_images WHERE work_id = ?');
                $existingSamples->execute([$workId]);
                if ((int)$existingSamples->fetchColumn() === 0) {
                    $sampleImages = $item['sampleImageURL']['sample_l']['image'];
                    $insertSample = $db->prepare('INSERT INTO work_sample_images (work_id, image_url, sort_order) VALUES (?, ?, ?)');
                    foreach ($sampleImages as $sortOrder => $imageUrl) {
                        $insertSample->execute([$workId, $imageUrl, $sortOrder]);
                    }
                }
            }

            // 女優×作品の紐付け（APIの出演者リストに含まれる場合のみ）
            $actressFound = false;
            if (!empty($item['iteminfo']['actress'])) {
                foreach ($item['iteminfo']['actress'] as $actressInfo) {
                    if (actressNameMatchesForWork($actressInfo['name'] ?? '', $actress['name'])) {
                        $actressFound = true;
                        break;
                    }
                }
            }
            if ($actressFound) {
                $db->prepare('INSERT IGNORE INTO actress_work (actress_id, work_id) VALUES (?, ?)')
                   ->execute([$actress['id'], $workId]);
            }

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
