<?php
/**
 * 女優プロフィール画像取得バッチ
 * FANZA ActressSearch API で女優のポートレート画像を取得し、thumbnail_url を更新する
 *
 * Usage: php batch/fetch_actress_profiles.php
 */

require_once __DIR__ . '/config.php';

$apiId = getenv('FANZA_API_ID') ?: '47sPXfBnNCUgVfKabDPy';
$affiliateId = getenv('FANZA_AFFILIATE_ID');

if (!$affiliateId) {
    batchLog('ERROR: FANZA_AFFILIATE_ID が設定されていません。.envを確認してください。');
    exit(1);
}

$db = Database::getInstance();

// thumbnail_url が未設定または作品画像（pics.dmm.co.jp を含む）の女優を対象にする
$actresses = $db->query('
    SELECT * FROM actresses
    WHERE thumbnail_url IS NULL
       OR thumbnail_url = ""
       OR thumbnail_url LIKE "%pics.dmm.co.jp%"
    ORDER BY id
')->fetchAll();

batchLog("プロフィール画像取得対象: " . count($actresses) . "名");

$updated = 0;
$notFound = 0;

foreach ($actresses as $actress) {
    $params = http_build_query([
        'api_id' => $apiId,
        'affiliate_id' => $affiliateId,
        'site' => 'FANZA',
        'keyword' => $actress['name'],
        'hits' => 5,
        'output' => 'json',
    ]);

    $url = 'https://api.dmm.com/affiliate/v3/ActressSearch?' . $params;
    $response = @file_get_contents($url);

    if ($response === false) {
        batchLog("  API エラー: {$actress['name']}");
        usleep(500000);
        continue;
    }

    $data = json_decode($response, true);
    $actresses_result = $data['result']['actress'] ?? [];

    // 名前が完全一致する女優を探す
    $matched = null;
    foreach ($actresses_result as $result) {
        if (($result['name'] ?? '') === $actress['name']) {
            $matched = $result;
            break;
        }
    }

    if (!$matched) {
        batchLog("  見つからず: {$actress['name']}");
        $notFound++;
        usleep(500000);
        continue;
    }

    // プロフィール画像を取得（large > small の優先順）
    $imageUrl = '';
    if (!empty($matched['imageURL']['large'])) {
        $imageUrl = $matched['imageURL']['large'];
    } elseif (!empty($matched['imageURL']['small'])) {
        $imageUrl = $matched['imageURL']['small'];
    }

    if (!$imageUrl) {
        batchLog("  画像なし: {$actress['name']}");
        $notFound++;
        usleep(500000);
        continue;
    }

    $db->prepare('UPDATE actresses SET thumbnail_url = ? WHERE id = ?')
       ->execute([$imageUrl, $actress['id']]);
    $updated++;
    batchLog("  更新: {$actress['name']} => {$imageUrl}");

    usleep(500000); // レートリミット対策
}

batchLog("プロフィール画像取得完了: 更新 {$updated} 名, 未取得 {$notFound} 名");
