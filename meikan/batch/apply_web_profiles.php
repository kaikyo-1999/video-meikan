<?php
/**
 * Web検索プロフィール適用バッチ
 * batch/data/actress_web_profiles.json の内容を DB に反映する
 *
 * Usage:
 *   php batch/apply_web_profiles.php             # 通常実行
 *   php batch/apply_web_profiles.php --dry-run   # DB書き込みなし（確認用）
 */

require_once __DIR__ . '/config.php';

$dryRun = in_array('--dry-run', $argv ?? []);

if ($dryRun) {
    batchLog('=== DRY RUN モード（DBへの書き込みなし）===');
}

$jsonPath = __DIR__ . '/data/actress_web_profiles.json';

if (!file_exists($jsonPath)) {
    batchLog("ERROR: {$jsonPath} が存在しません。");
    exit(1);
}

$json = json_decode(file_get_contents($jsonPath), true);
if (!is_array($json)) {
    batchLog("ERROR: JSONの解析に失敗しました。");
    exit(1);
}

// 数値フィールドの妥当性チェック用の範囲
$validRanges = [
    'bust'   => [60, 130],
    'waist'  => [40,  90],
    'hip'    => [70, 130],
    'height' => [140, 185],
];

$db = Database::getInstance();

$updated  = 0;
$skipped  = 0;
$notFound = 0;

foreach ($json as $slug => $data) {
    // slug で女優を検索
    $stmt = $db->prepare('SELECT id, name FROM actresses WHERE slug = ?');
    $stmt->execute([$slug]);
    $actress = $stmt->fetch();

    if (!$actress) {
        batchLog("  SKIP（DB未登録）: {$slug}");
        $notFound++;
        continue;
    }

    // 書き込む値を組み立て（バリデーション込み）
    $sets   = ['web_searched_at = ?'];
    $params = [$data['searched_at'] ?? date('Y-m-d')];

    // 数値フィールド
    foreach (['bust', 'waist', 'hip', 'height'] as $field) {
        $val = isset($data[$field]) && $data[$field] !== null ? (int)$data[$field] : null;
        if ($val !== null) {
            [$min, $max] = $validRanges[$field];
            if ($val >= $min && $val <= $max) {
                $sets[]   = "{$field} = ?";
                $params[] = $val;
            } else {
                batchLog("  WARN: {$actress['name']} の {$field}={$val} は範囲外のためスキップ");
            }
        }
    }

    // birthday: YYYY-MM-DD 形式チェック
    $bday = $data['birthday'] ?? null;
    if ($bday && preg_match('/^\d{4}-\d{2}-\d{2}$/', $bday)) {
        $year = (int)substr($bday, 0, 4);
        if ($year >= 1970 && $year <= 2015) {
            $sets[]   = 'birthday = ?';
            $params[] = $bday;
        }
    }

    // blood_type: A/B/O/AB のみ
    $bt = $data['blood_type'] ?? null;
    if ($bt && in_array($bt, ['A', 'B', 'O', 'AB'], true)) {
        $sets[]   = 'blood_type = ?';
        $params[] = $bt;
    }

    // hobby, prefectures: 文字列
    foreach (['hobby', 'prefectures'] as $field) {
        $val = $data[$field] ?? null;
        if ($val && is_string($val)) {
            $sets[]   = "{$field} = ?";
            $params[] = mb_substr($val, 0, 255);
        }
    }

    // web_searched_at しか書くものがない（全フィールド null）場合もそのまま記録
    $fieldsWritten = count($sets) - 1; // web_searched_at を除く

    $got = array_filter([
        'bust'        => $data['bust']        ?? null,
        'waist'       => $data['waist']       ?? null,
        'hip'         => $data['hip']         ?? null,
        'height'      => $data['height']      ?? null,
        'birthday'    => $data['birthday']    ?? null,
        'blood_type'  => $data['blood_type']  ?? null,
        'hobby'       => $data['hobby']       ?? null,
        'prefectures' => $data['prefectures'] ?? null,
    ], fn($v) => $v !== null);

    if (!empty($got)) {
        $summary = implode(', ', array_map(
            fn($k, $v) => "{$k}={$v}",
            array_keys($got), $got
        ));
        batchLog("  更新: {$actress['name']} ({$slug}) — {$summary}");
    } else {
        batchLog("  記録: {$actress['name']} ({$slug}) — 取得なし（web_searched_atのみ）");
        $skipped++;
    }

    if (!$dryRun) {
        $params[] = $actress['id'];
        $sql = 'UPDATE actresses SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $db->prepare($sql)->execute($params);
    }

    $updated++;
}

batchLog(sprintf(
    "完了: 更新 %d件（うち取得なし %d件）, DB未登録 %d件 / JSON合計 %d件",
    $updated, $skipped, $notFound, count($json)
));
