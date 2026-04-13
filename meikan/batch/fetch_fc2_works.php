<?php
/**
 * FC2人気作品スクレイピングバッチ
 * FC2コンテンツの人気順ランキングから100作品を取得してDBに登録する
 *
 * Usage: php batch/fetch_fc2_works.php
 */

require_once __DIR__ . '/config.php';

define('FC2_TARGET',    100);
define('FC2_SLEEP_SEC', 2);   // レート制限対策（秒）

$db = Database::getInstance();

batchLog('FC2人気作品スクレイピング開始（目標: ' . FC2_TARGET . '作品）');

$inserted = 0;
$skipped  = 0;
$errors   = 0;
$page     = 1;

while ($inserted < FC2_TARGET) {
    $rankUrl = "https://adult.contents.fc2.com/search/?sort=total_sales&page={$page}";
    batchLog("ランキングページ取得: {$rankUrl}");

    $html = fc2Fetch($rankUrl);
    if ($html === null) {
        batchLog("ERROR: ランキングページ取得失敗 (page={$page})");
        break;
    }

    $cids = fc2ExtractCids($html);
    if (empty($cids)) {
        batchLog("CID抽出なし (page={$page}) — 終了");
        break;
    }

    batchLog('CID抽出: ' . count($cids) . '件 (page=' . $page . ')');

    foreach ($cids as $cid) {
        if ($inserted >= FC2_TARGET) break;

        // 重複チェック
        $stmt = $db->prepare('SELECT COUNT(*) FROM fc2_works WHERE cid = ?');
        $stmt->execute([$cid]);
        if ((int)$stmt->fetchColumn() > 0) {
            batchLog("SKIP: {$cid} (既登録)");
            $skipped++;
            continue;
        }

        sleep(FC2_SLEEP_SEC);

        $meta = fc2FetchMeta($cid);
        if ($meta === null) {
            batchLog("ERROR: メタデータ取得失敗 CID={$cid}");
            $errors++;
            continue;
        }

        $stmt = $db->prepare('
            INSERT INTO fc2_works (cid, title, thumbnail_url, price, duration, is_approved)
            VALUES (?, ?, ?, ?, ?, 1)
        ');
        $stmt->execute([
            $cid,
            $meta['title'],
            $meta['thumbnail_url'],
            $meta['price'],
            $meta['duration'],
        ]);

        $inserted++;
        batchLog("INSERT [{$inserted}]: CID={$cid} / {$meta['title']}");
    }

    $page++;
    sleep(FC2_SLEEP_SEC);
}

batchLog("完了: 登録={$inserted} / スキップ={$skipped} / エラー={$errors}");

// ---------- ヘルパー ----------

function fc2Fetch(string $url): ?string
{
    $ctx = stream_context_create(['http' => [
        'timeout'         => 15,
        'follow_location' => 1,
        'max_redirects'   => 3,
        'user_agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        'header'          => implode("\r\n", [
            'Accept: text/html,application/xhtml+xml',
            'Accept-Language: ja,en-US;q=0.9',
        ]),
    ]]);

    $html = @file_get_contents($url, false, $ctx);
    return $html !== false ? $html : null;
}

function fc2ExtractCids(string $html): array
{
    // /article/1234567/ 形式のリンクからCIDを抽出
    preg_match_all('#/article/(\d{7})/#', $html, $matches);
    return array_values(array_unique($matches[1]));
}

function fc2FetchMeta(string $cid): ?array
{
    $url  = 'https://adult.contents.fc2.com/article/' . $cid . '/';
    $html = fc2Fetch($url);
    if ($html === null) return null;

    // 商品なしページの検出
    if (strpos($html, 'お探しの商品が見つかりませんでした') !== false) return null;

    // タイトル
    if (!preg_match('/<title[^>]*>([^<]+)</i', $html, $m)) return null;
    $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    $title = preg_replace('/\s*[-|｜]\s*FC2.*/u', '', $title);
    if (empty($title)) return null;

    // サムネイル（og:image）
    $thumbnail = null;
    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/', $html, $tm)) {
        $thumbnail = $tm[1];
    }

    // 価格（例: ¥ 990）
    $price = 0;
    if (preg_match('/¥\s*([\d,]+)/', $html, $pm)) {
        $price = (int)str_replace(',', '', $pm[1]);
    }

    // 再生時間（分）
    $duration = null;
    if (preg_match('/(\d+)\s*分/', $html, $dm)) {
        $duration = (int)$dm[1];
    }

    return [
        'title'         => $title,
        'thumbnail_url' => $thumbnail,
        'price'         => $price,
        'duration'      => $duration,
    ];
}
