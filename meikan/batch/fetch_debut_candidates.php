<?php
/**
 * FANZA Affiliate API v3 で指定年月にデビューした女優候補を取得する。
 *
 * 戦略:
 *   1. ItemList を gte_date / lte_date で月内に絞り、sort=-date で全件ページネーション
 *   2. 各 item の iteminfo.genre に「デビュー作品」(id=6006) が含まれるものだけ抽出
 *   3. 出演女優を集合化 → JSON 出力
 *
 * 出力先: meikan/batch/data/newcomers_<YYYY>_<MM>.json
 *   既存の register_actresses.php が読める形式 ([{"name":"…","reading":"…"}] の配列)
 *
 * 使い方:
 *   cd meikan && php batch/fetch_debut_candidates.php 2026-04
 *
 * オプション:
 *   --skip-existing  既に actresses テーブルに同名レコードがあるものを除外
 *   --dry-run        JSON は書き込まず標準出力にのみ表示
 *   --no-verify      ピュアデビュー検証 (actress_id で全期間検索) をスキップ。デフォルト ON
 */

declare(strict_types=1);
chdir(__DIR__ . '/..');
define('ROOT_DIR', __DIR__ . '/..');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Cache.php';

const GENRE_ID_DEBUT = 6006; // FANZA「デビュー作品」ジャンル
const HITS_PER_PAGE = 100;
const MAX_OFFSET = 5000;     // FANZA API の offset 上限保険
const VERIFY_MAX_OFFSET = 500; // 検証時の actress 別 offset 上限 (ベテラン女優は数百件あるため切る)

if ($argc < 2) {
    fwrite(STDERR, "Usage: php batch/fetch_debut_candidates.php YYYY-MM [--skip-existing] [--dry-run]\n");
    exit(1);
}

$yearMonth = $argv[1];
if (!preg_match('/^(\d{4})-(\d{2})$/', $yearMonth, $m)) {
    fwrite(STDERR, "Invalid YYYY-MM: {$yearMonth}\n");
    exit(1);
}
$year = (int)$m[1];
$month = (int)$m[2];
$skipExisting = in_array('--skip-existing', $argv, true);
$dryRun = in_array('--dry-run', $argv, true);
$verify = !in_array('--no-verify', $argv, true);

$startDate = sprintf('%04d-%02d-01T00:00:00', $year, $month);
$lastDay = (int)date('t', strtotime("$year-$month-01"));
$endDate = sprintf('%04d-%02d-%02dT23:59:59', $year, $month, $lastDay);

$apiId = getenv('FANZA_API_ID');
$affiliateId = getenv('FANZA_AFFILIATE_ID');
if (!$apiId || !$affiliateId) {
    fwrite(STDERR, "FANZA_API_ID / FANZA_AFFILIATE_ID が未設定です (.env を確認)\n");
    exit(1);
}

/**
 * 指定 actress_id の全作品を fetch して min(release_date) を返す。
 * ベテラン女優の AI 復刻作が誤って「デビュー作品」タグされても、これで除外できる。
 */
function fetchActressFirstReleaseDate(string $apiId, string $affiliateId, int $actressId): ?string {
    $allDates = [];
    $offset = 1;
    while (true) {
        $params = http_build_query([
            'api_id' => $apiId,
            'affiliate_id' => $affiliateId,
            'site' => 'FANZA',
            'service' => 'digital',
            'floor' => 'videoa',
            'hits' => HITS_PER_PAGE,
            'offset' => $offset,
            'article' => 'actress',
            'article_id' => $actressId,
            'output' => 'json',
        ]);
        $url = 'https://api.dmm.com/affiliate/v3/ItemList?' . $params;
        $json = @file_get_contents($url);
        if ($json === false) break;
        $data = json_decode($json, true);
        $items = $data['result']['items'] ?? [];
        if (empty($items)) break;
        foreach ($items as $item) {
            $d = substr($item['date'] ?? '', 0, 10);
            if ($d) $allDates[] = $d;
        }
        $total = (int)($data['result']['total_count'] ?? 0);
        if ($offset + HITS_PER_PAGE > $total || $offset > VERIFY_MAX_OFFSET) break;
        $offset += HITS_PER_PAGE;
        usleep(120000);
    }
    if (empty($allDates)) return null;
    sort($allDates);
    return $allDates[0];
}

function fetchPage(string $apiId, string $affiliateId, string $gte, string $lte, int $offset): array {
    $params = http_build_query([
        'api_id' => $apiId,
        'affiliate_id' => $affiliateId,
        'site' => 'FANZA',
        'service' => 'digital',
        'floor' => 'videoa',
        'hits' => HITS_PER_PAGE,
        'offset' => $offset,
        'sort' => '-date',
        'gte_date' => $gte,
        'lte_date' => $lte,
        'output' => 'json',
    ]);
    $url = 'https://api.dmm.com/affiliate/v3/ItemList?' . $params;
    $json = @file_get_contents($url);
    if ($json === false) return [];
    $data = json_decode($json, true);
    return $data['result'] ?? [];
}

echo "対象期間: {$startDate} 〜 {$endDate}\n";
echo "デビュー作品ジャンル ID: " . GENRE_ID_DEBUT . "\n\n";

$candidates = [];
$offset = 1;
$total = null;
$scanned = 0;

while (true) {
    $result = fetchPage($apiId, $affiliateId, $startDate, $endDate, $offset);
    $items = $result['items'] ?? [];
    if (empty($items)) break;

    if ($total === null) {
        $total = (int)($result['total_count'] ?? 0);
        echo "全 {$total} 件をスキャン中...\n";
    }

    foreach ($items as $item) {
        $genreIds = array_map(fn($g) => (int)$g['id'], $item['iteminfo']['genre'] ?? []);
        if (!in_array(GENRE_ID_DEBUT, $genreIds, true)) continue;

        foreach ($item['iteminfo']['actress'] ?? [] as $a) {
            $name = trim($a['name'] ?? '');
            if ($name === '') continue;
            if (isset($candidates[$name])) continue;

            $candidates[$name] = [
                'name' => $name,
                'reading' => $a['ruby'] ?? '',
                'fanza_actress_id' => (int)($a['id'] ?? 0),
                'first_cid' => $item['content_id'] ?? '',
                'first_date' => substr($item['date'] ?? '', 0, 10),
                'first_title' => $item['title'] ?? '',
            ];
        }
    }
    $scanned += count($items);
    echo "  offset={$offset}: scanned={$scanned}/{$total}, debut候補=" . count($candidates) . "\n";

    if ($scanned >= $total) break;
    $offset += HITS_PER_PAGE;
    if ($offset > MAX_OFFSET) break;
    usleep(200000); // 200ms wait — API レート保険
}

ksort($candidates);
echo "\n=== 抽出結果 ===\n";
echo "計 " . count($candidates) . " 名\n\n";

// 既存チェック
$db = Database::getInstance();
foreach ($candidates as &$c) {
    $stmt = $db->prepare('SELECT id, debut_date FROM actresses WHERE name = ? LIMIT 1');
    $stmt->execute([$c['name']]);
    $row = $stmt->fetch();
    $c['exists_in_db'] = $row !== false;
    $c['existing_debut_date'] = $row ? $row['debut_date'] : null;
}
unset($c);

// ピュアデビュー検証 (default ON)
if ($verify) {
    echo "\n--- ピュアデビュー検証中 (actress_id で全期間検索) ---\n";
    $expectedFrom = sprintf('%04d-%02d-01', $year, $month);
    $expectedTo = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);
    $impure = [];
    foreach ($candidates as $name => &$c) {
        $aid = (int)($c['fanza_actress_id'] ?? 0);
        if ($aid <= 0) {
            $c['verify_status'] = 'skipped_no_id';
            continue;
        }
        $first = fetchActressFirstReleaseDate($apiId, $affiliateId, $aid);
        $c['verified_first_date'] = $first;
        if ($first === null) {
            $c['verify_status'] = 'unknown';
        } elseif ($first >= $expectedFrom && $first <= $expectedTo) {
            $c['verify_status'] = 'pure';
        } else {
            $c['verify_status'] = 'impure';
            $impure[$name] = $first;
        }
    }
    unset($c);
    echo "  pure: " . count(array_filter($candidates, fn($c) => ($c['verify_status'] ?? '') === 'pure')) . " 名\n";
    echo "  impure (除外): " . count($impure) . " 名\n";
    foreach ($impure as $n => $d) echo "    - $n (first=$d)\n";
}

$inDb = array_filter($candidates, fn($c) => $c['exists_in_db']);
$notInDb = array_filter($candidates, fn($c) => !$c['exists_in_db']);

echo "  - 既にDB登録済: " . count($inDb) . " 名\n";
echo "  - 新規: " . count($notInDb) . " 名\n\n";

echo "【新規候補】\n";
foreach ($notInDb as $c) {
    echo sprintf("  %s | %s | %s | %s\n", $c['first_date'], $c['name'], $c['reading'], $c['first_cid']);
}
echo "\n【DB既存・参考】\n";
foreach ($inDb as $c) {
    echo sprintf("  %s | %s (existing debut=%s) | %s\n", $c['first_date'], $c['name'], $c['existing_debut_date'] ?? 'NULL', $c['first_cid']);
}

// JSON 出力 (verify ON のときは pure のみ)
$out = $skipExisting ? $notInDb : $candidates;
if ($verify) {
    $out = array_filter($out, fn($c) => ($c['verify_status'] ?? '') === 'pure');
}
$json = array_values(array_map(fn($c) => ['name' => $c['name'], 'reading' => $c['reading']], $out));

$outFile = __DIR__ . "/data/newcomers_{$year}_" . sprintf('%02d', $month) . '.json';
if ($dryRun) {
    echo "\n[dry-run] would write " . count($json) . " entries to {$outFile}\n";
} else {
    file_put_contents($outFile, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\n書き込み完了: {$outFile} (" . count($json) . " 名)\n";
}
