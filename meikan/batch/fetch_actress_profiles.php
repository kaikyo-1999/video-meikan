<?php
/**
 * 女優プロフィール画像取得バッチ
 * FANZA ActressSearch API で女優のポートレート画像を取得し、thumbnail_url を更新する
 *
 * Usage: php batch/fetch_actress_profiles.php
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

/**
 * 女優名が一致するか判定（括弧内の別名も照合）
 *
 * 対応パターン:
 *   完全一致: 「AIKA」=「AIKA」
 *   括弧前: 「Hitomi（田中瞳）」⇔「Hitomi」
 *   括弧内: 「木下ひまり（花沢ひまり）」⇔「花沢ひまり」
 *   括弧前スペース: 「みひな （あずみひな、永井みひな）」⇔「みひな」
 *   括弧内読点区切り: 「みひな （あずみひな、永井みひな）」⇔「永井みひな」
 */
function actressNameMatches(string $apiName, string $dbName): bool
{
    if ($apiName === $dbName) return true;

    // 括弧付き: "メイン名（別名1、別名2）" または "メイン名 （別名1、別名2）" を分解
    if (preg_match('/^(.+?)\s*（(.+?)）$/', $apiName, $m)) {
        $mainName = trim($m[1]);
        if ($mainName === $dbName) return true;

        // 括弧内を読点・カンマで分割して各別名と照合
        $aliases = preg_split('/[、,]\s*/', $m[2]);
        foreach ($aliases as $alias) {
            if (trim($alias) === $dbName) return true;
        }
    }
    return false;
}

/**
 * ActressSearch APIの結果からプロフィール情報を抽出する
 */
function extractProfile(array $result): array
{
    return [
        'bust'        => !empty($result['bust'])        ? (int)$result['bust']        : null,
        'waist'       => !empty($result['waist'])       ? (int)$result['waist']       : null,
        'hip'         => !empty($result['hip'])          ? (int)$result['hip']         : null,
        'height'      => !empty($result['height'])       ? (int)$result['height']      : null,
        'birthday'    => !empty($result['birthday'])     ? $result['birthday']         : null,
        'blood_type'  => !empty($result['blood_type'])   ? $result['blood_type']       : null,
        'hobby'       => !empty($result['hobby'])        ? $result['hobby']            : null,
        'prefectures' => !empty($result['prefectures'])  ? $result['prefectures']      : null,
    ];
}

/**
 * プロフィール情報をDBに更新する
 */
function updateProfile(PDO $db, int $actressId, array $profile): void
{
    $sets = [];
    $params = [];
    foreach ($profile as $col => $val) {
        if ($val !== null) {
            $sets[] = "{$col} = ?";
            $params[] = $val;
        }
    }
    if (empty($sets)) return;

    $params[] = $actressId;
    $sql = 'UPDATE actresses SET ' . implode(', ', $sets) . ' WHERE id = ?';
    $db->prepare($sql)->execute($params);
}

/**
 * 作品APIから女優IDを逆引きし、ActressSearchでプロフィール画像を取得する
 * @return array ['image' => string, 'profile' => array]
 */
function resolveImageViaWork(PDO $db, array $actress, string $apiId, string $affiliateId): array
{
    // 出演者数が少ない作品を優先（名前特定しやすい）、最大10作品試す
    $stmt = $db->prepare('
        SELECT w.source_id
        FROM works w
        INNER JOIN actress_work aw ON w.id = aw.work_id
        WHERE aw.actress_id = ? AND w.source = "fanza"
        ORDER BY (SELECT COUNT(*) FROM actress_work aw2 WHERE aw2.work_id = w.id) ASC
        LIMIT 10
    ');
    $stmt->execute([$actress['id']]);
    $works = $stmt->fetchAll();

    foreach ($works as $work) {
        usleep(500000);
        $params = http_build_query([
            'api_id' => $apiId, 'affiliate_id' => $affiliateId,
            'site' => 'FANZA', 'service' => 'digital',
            'cid' => $work['source_id'], 'hits' => 1, 'output' => 'json',
        ]);
        $resp = @file_get_contents('https://api.dmm.com/affiliate/v3/ItemList?' . $params);
        if ($resp === false) continue;

        $data = json_decode($resp, true);
        $item = $data['result']['items'][0] ?? null;
        if (!$item) continue;

        // 作品の出演者リストから名前一致でIDを取得
        // 括弧付き別名も照合: API「木下ひまり（花沢ひまり）」⇔ DB「木下ひまり」or「花沢ひまり」
        foreach ($item['iteminfo']['actress'] ?? [] as $a) {
            if (!actressNameMatches($a['name'] ?? '', $actress['name'])) continue;
            $actressId = $a['id'] ?? null;
            if (!$actressId) continue;

            // ActressSearch by ID
            usleep(500000);
            $params2 = http_build_query([
                'api_id' => $apiId, 'affiliate_id' => $affiliateId,
                'site' => 'FANZA', 'actress_id' => $actressId, 'output' => 'json',
            ]);
            $resp2 = @file_get_contents('https://api.dmm.com/affiliate/v3/ActressSearch?' . $params2);
            if ($resp2 === false) continue;

            $data2 = json_decode($resp2, true);
            $result = $data2['result']['actress'][0] ?? null;
            if (!$result) continue;

            $img = $result['imageURL']['large'] ?? $result['imageURL']['small'] ?? '';
            if ($img) {
                batchLog("  ID逆引き成功: {$actress['name']} (actress_id={$actressId})");
                return ['image' => $img, 'profile' => extractProfile($result)];
            }
        }
    }
    return ['image' => '', 'profile' => []];
}

// thumbnail_url が未設定/作品画像URL の女優、またはプロフィール未取得の女優を対象
// ※ actjpgs/ を含むURLは正しいプロフィール画像なので除外
$actresses = $db->query('
    SELECT * FROM actresses
    WHERE thumbnail_url IS NULL
       OR thumbnail_url = ""
       OR (thumbnail_url LIKE "%/digital/video/%" OR thumbnail_url LIKE "%now_printing%")
       OR bust IS NULL
       OR height IS NULL
       OR birthday IS NULL
       OR prefectures IS NULL
       OR blood_type IS NULL
       OR hobby IS NULL
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

    // 名前一致する女優を探す（画像あり優先、括弧内別名も照合）
    $matched = null;
    foreach ($actresses_result as $result) {
        if (!actressNameMatches($result['name'] ?? '', $actress['name'])) continue;
        $hasImage = !empty($result['imageURL']['large']) || !empty($result['imageURL']['small']);
        if ($hasImage) {
            $matched = $result;
            break;
        }
        if ($matched === null) {
            $matched = $result;
        }
    }

    // プロフィール画像を取得（large > small の優先順）
    $imageUrl = '';
    $profile = [];
    if ($matched) {
        if (!empty($matched['imageURL']['large'])) {
            $imageUrl = $matched['imageURL']['large'];
        } elseif (!empty($matched['imageURL']['small'])) {
            $imageUrl = $matched['imageURL']['small'];
        }
        $profile = extractProfile($matched);
    }

    // フォールバック: 名前検索で画像が取れなかった場合、作品APIから女優IDを逆引き
    if (!$imageUrl) {
        $resolved = resolveImageViaWork($db, $actress, $apiId, $affiliateId);
        $imageUrl = $resolved['image'];
        if (!empty($resolved['profile'])) {
            $profile = $resolved['profile'];
        }
    }

    if (!$imageUrl) {
        // 画像がなくてもプロフィール情報があれば保存
        if (!empty($profile)) {
            updateProfile($db, $actress['id'], $profile);
            batchLog("  画像なし（プロフィールのみ更新）: {$actress['name']}");
        } else {
            batchLog("  画像なし: {$actress['name']}");
        }
        $notFound++;
        usleep(500000);
        continue;
    }

    $db->prepare('UPDATE actresses SET thumbnail_url = ? WHERE id = ?')
       ->execute([$imageUrl, $actress['id']]);
    // プロフィール情報も保存
    if (!empty($profile)) {
        updateProfile($db, $actress['id'], $profile);
    }
    $updated++;
    batchLog("  更新: {$actress['name']} => {$imageUrl}");

    usleep(500000); // レートリミット対策
}

batchLog("プロフィール画像取得完了: 更新 {$updated} 名, 未取得 {$notFound} 名");

// キャッシュクリア（女優一覧キャッシュに古い画像が残るのを防ぐ）
if ($updated > 0) {
    Cache::clear();
    batchLog("キャッシュクリア完了");
}
