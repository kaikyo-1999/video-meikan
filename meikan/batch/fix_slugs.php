<?php
/**
 * スラッグ修正バッチ
 * 1. 不正スラッグをrubyから再生成
 * 2. Ai（誤データ）を削除
 *
 * Usage: php batch/fix_slugs.php
 */

require_once __DIR__ . '/config.php';

$db = Database::getInstance();

// ── ローマ字変換テーブル ──
$romajiMap = [
    'きゃ' => 'kya', 'きゅ' => 'kyu', 'きょ' => 'kyo',
    'しゃ' => 'sha', 'しゅ' => 'shu', 'しょ' => 'sho',
    'ちゃ' => 'cha', 'ちゅ' => 'chu', 'ちょ' => 'cho',
    'にゃ' => 'nya', 'にゅ' => 'nyu', 'にょ' => 'nyo',
    'ひゃ' => 'hya', 'ひゅ' => 'hyu', 'ひょ' => 'hyo',
    'みゃ' => 'mya', 'みゅ' => 'myu', 'みょ' => 'myo',
    'りゃ' => 'rya', 'りゅ' => 'ryu', 'りょ' => 'ryo',
    'ぎゃ' => 'gya', 'ぎゅ' => 'gyu', 'ぎょ' => 'gyo',
    'じゃ' => 'ja',  'じゅ' => 'ju',  'じょ' => 'jo',
    'びゃ' => 'bya', 'びゅ' => 'byu', 'びょ' => 'byo',
    'ぴゃ' => 'pya', 'ぴゅ' => 'pyu', 'ぴょ' => 'pyo',
    'ゐ' => 'wi', 'ゑ' => 'we',
    'あ' => 'a',  'い' => 'i',  'う' => 'u',  'え' => 'e',  'お' => 'o',
    'か' => 'ka', 'き' => 'ki', 'く' => 'ku', 'け' => 'ke', 'こ' => 'ko',
    'さ' => 'sa', 'し' => 'shi','す' => 'su', 'せ' => 'se', 'そ' => 'so',
    'た' => 'ta', 'ち' => 'chi','つ' => 'tsu','て' => 'te', 'と' => 'to',
    'な' => 'na', 'に' => 'ni', 'ぬ' => 'nu', 'ね' => 'ne', 'の' => 'no',
    'は' => 'ha', 'ひ' => 'hi', 'ふ' => 'fu', 'へ' => 'he', 'ほ' => 'ho',
    'ま' => 'ma', 'み' => 'mi', 'む' => 'mu', 'め' => 'me', 'も' => 'mo',
    'や' => 'ya',              'ゆ' => 'yu',              'よ' => 'yo',
    'ら' => 'ra', 'り' => 'ri', 'る' => 'ru', 'れ' => 're', 'ろ' => 'ro',
    'わ' => 'wa',                                          'を' => 'wo',
    'ん' => 'n',
    'が' => 'ga', 'ぎ' => 'gi', 'ぐ' => 'gu', 'げ' => 'ge', 'ご' => 'go',
    'ざ' => 'za', 'じ' => 'ji', 'ず' => 'zu', 'ぜ' => 'ze', 'ぞ' => 'zo',
    'だ' => 'da', 'ぢ' => 'di', 'づ' => 'du', 'で' => 'de', 'ど' => 'do',
    'ば' => 'ba', 'び' => 'bi', 'ぶ' => 'bu', 'べ' => 'be', 'ぼ' => 'bo',
    'ぱ' => 'pa', 'ぴ' => 'pi', 'ぷ' => 'pu', 'ぺ' => 'pe', 'ぽ' => 'po',
    'っ' => '_tsu_',
    'ー' => '',
    'ぁ' => 'a', 'ぃ' => 'i', 'ぅ' => 'u', 'ぇ' => 'e', 'ぉ' => 'o',
    'ゃ' => 'ya', 'ゅ' => 'yu', 'ょ' => 'yo',
];

function rubyToSlug(string $ruby, array $romajiMap): string
{
    // カタカナ→ひらがな
    $hiragana = mb_convert_kana($ruby, 'c');

    // 姓名の区切りを検出（ひらがな文字列の中間にある自然な区切り）
    // rubyは通常 "やまだはなこ" のように姓名がつながっている
    // FANZA APIの ruby はスペースなしが多いので、そのまま1パートとして処理
    $chars = mb_str_split($hiragana);
    $roma = '';
    $i = 0;
    while ($i < count($chars)) {
        if ($i + 1 < count($chars)) {
            $two = $chars[$i] . $chars[$i + 1];
            if (isset($romajiMap[$two])) {
                $roma .= $romajiMap[$two];
                $i += 2;
                continue;
            }
        }
        $one = $chars[$i];
        if (isset($romajiMap[$one])) {
            $roma .= $romajiMap[$one];
        } elseif (preg_match('/[a-zA-Z0-9]/', $one)) {
            $roma .= strtolower($one);
        }
        $i++;
    }

    // 促音処理
    $roma = preg_replace_callback('/_tsu_([a-z])/', function ($m) {
        return $m[1] . $m[1];
    }, $roma);
    $roma = str_replace('_tsu_', 'tsu', $roma);

    return $roma;
}

// ── 既存の有効なslugを取得（重複チェック用）──
$existingSlugs = $db->query('
    SELECT slug FROM actresses WHERE slug REGEXP "^[a-z0-9][a-z0-9-]*$"
')->fetchAll(PDO::FETCH_COLUMN);
$slugSet = array_flip($existingSlugs);

// ── 1. rubyありの不正スラッグを修正 ──
batchLog("=== スラッグ修正開始 ===");

$badActresses = $db->query('
    SELECT id, name, slug, ruby FROM actresses
    WHERE slug NOT REGEXP "^[a-z0-9][a-z0-9-]*$"
    AND ruby IS NOT NULL AND ruby != ""
    ORDER BY id
')->fetchAll(PDO::FETCH_ASSOC);

batchLog("ruby有りの不正スラッグ: " . count($badActresses) . " 件");

$updateStmt = $db->prepare('UPDATE actresses SET slug = ? WHERE id = ?');
$fixed = 0;

foreach ($badActresses as $a) {
    $newSlug = rubyToSlug($a['ruby'], $romajiMap);
    if (!$newSlug) {
        batchLog("  変換失敗: {$a['name']} (ruby: {$a['ruby']})");
        continue;
    }

    // 重複チェック
    $baseSlug = $newSlug;
    $counter = 2;
    while (isset($slugSet[$newSlug])) {
        $newSlug = $baseSlug . '-' . $counter;
        $counter++;
    }
    $slugSet[$newSlug] = true;

    $updateStmt->execute([$newSlug, $a['id']]);
    $fixed++;
    batchLog("  修正: {$a['name']} ({$a['ruby']}) {$a['slug']} → {$newSlug}");
}

// ── 2. rubyなしの4件を手動設定 ──
$manualSlugs = [
    608 => 'danmitsu',        // 壇蜜
    730 => 'suzuki-sachiko',   // 鈴木早智子
    773 => 'shibuya-kaho',    // 澁谷果歩
    849 => 'kondo-yuko',      // 近藤裕子
];

foreach ($manualSlugs as $id => $slug) {
    if (!isset($slugSet[$slug])) {
        $updateStmt->execute([$slug, $id]);
        $slugSet[$slug] = true;
        $fixed++;
        batchLog("  手動修正: ID={$id} → {$slug}");
    } else {
        batchLog("  WARNING: slug重複 ID={$id} {$slug}");
    }
}

batchLog("スラッグ修正完了: {$fixed} 件");

// ── 3. Ai（誤データ）削除 ──
batchLog("=== Ai削除開始 ===");

$aiStmt = $db->prepare('SELECT id FROM actresses WHERE name = ? AND slug = ?');
$aiStmt->execute(['Ai', 'ai']);
$aiId = $aiStmt->fetchColumn();

if ($aiId) {
    // Aiにのみ紐付く作品IDを取得（他の女優に紐付いていない）
    $uniqueWorkIds = $db->prepare('
        SELECT aw1.work_id FROM actress_work aw1
        WHERE aw1.actress_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM actress_work aw2 WHERE aw2.work_id = aw1.work_id AND aw2.actress_id != ?
        )
    ');
    $uniqueWorkIds->execute([$aiId, $aiId]);
    $workIds = $uniqueWorkIds->fetchAll(PDO::FETCH_COLUMN);
    batchLog("Ai固有の作品: " . count($workIds) . " 件（削除対象）");

    // バッチで削除（1000件ずつ）
    $chunks = array_chunk($workIds, 1000);
    foreach ($chunks as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));

        // work_genre削除
        $db->prepare("DELETE FROM work_genre WHERE work_id IN ({$placeholders})")->execute($chunk);

        // works削除
        $db->prepare("DELETE FROM works WHERE id IN ({$placeholders})")->execute($chunk);
    }
    batchLog("Ai固有の作品・ジャンル紐付け削除完了");

    // actress_work削除（全件）
    $db->prepare('DELETE FROM actress_work WHERE actress_id = ?')->execute([$aiId]);
    batchLog("Aiのactress_work削除完了");

    // actress削除
    $db->prepare('DELETE FROM actresses WHERE id = ?')->execute([$aiId]);
    batchLog("Ai女優レコード削除完了");
} else {
    batchLog("Aiが見つかりません（既に削除済み？）");
}

// ── キャッシュクリア ──
Cache::clear();
batchLog("キャッシュクリア完了");
batchLog("=== 全修正完了 ===");
