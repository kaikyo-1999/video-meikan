<?php
/**
 * 女優一括登録バッチ
 * 名前リスト（JSON）を読み込み、slug自動生成してDBに登録する
 *
 * Usage: php batch/register_actresses.php [JSONファイルパス]
 */

require_once __DIR__ . '/config.php';

$jsonPath = $argv[1] ?? __DIR__ . '/data/new_actresses.json';
if (!file_exists($jsonPath)) {
    batchLog("ERROR: {$jsonPath} が見つかりません");
    exit(1);
}

$names = json_decode(file_get_contents($jsonPath), true);
if (!$names) {
    batchLog("ERROR: JSONの読み込みに失敗しました");
    exit(1);
}

batchLog("登録対象: " . count($names) . " 名");

$db = Database::getInstance();

// 既存の女優名を取得（重複チェック用）
$existingNames = $db->query('SELECT name FROM actresses')->fetchAll(PDO::FETCH_COLUMN);
$existingSet = array_flip($existingNames);
batchLog("既存女優: " . count($existingNames) . " 名");

// 既存のslugを取得（slug重複チェック用）
$existingSlugs = $db->query('SELECT slug FROM actresses')->fetchAll(PDO::FETCH_COLUMN);
$slugSet = array_flip($existingSlugs);

/**
 * 日本語名からslugを生成
 * ローマ字変換ライブラリがないため、名前をハイフン区切りのURL-safeな形式に変換
 */
function generateSlug(string $name, array &$slugSet): string
{
    // 基本的なローマ字変換テーブル（ひらがな→ローマ字）
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
        'っ' => '_tsu_', // 促音は後で処理
        'ー' => '-',
        'ぁ' => 'a', 'ぃ' => 'i', 'ぅ' => 'u', 'ぇ' => 'e', 'ぉ' => 'o',
        'ゃ' => 'ya', 'ゅ' => 'yu', 'ょ' => 'yo',
    ];

    // カタカナ→ひらがな変換
    $hiragana = mb_convert_kana($name, 'c'); // カタカナ→ひらがな

    // スペース・全角スペースで分割（姓名の区切り）
    $parts = preg_split('/[\s　・]+/u', $hiragana);
    $romaParts = [];

    foreach ($parts as $part) {
        $roma = '';
        $chars = mb_str_split($part);
        $i = 0;
        while ($i < count($chars)) {
            // 2文字の組み合わせを先にチェック
            if ($i + 1 < count($chars)) {
                $two = $chars[$i] . $chars[$i + 1];
                if (isset($romajiMap[$two])) {
                    $roma .= $romajiMap[$two];
                    $i += 2;
                    continue;
                }
            }
            // 1文字チェック
            $one = $chars[$i];
            if (isset($romajiMap[$one])) {
                $roma .= $romajiMap[$one];
            } elseif (preg_match('/[a-zA-Z0-9]/', $one)) {
                $roma .= strtolower($one);
            }
            // 変換不能な文字はスキップ
            $i++;
        }

        // 促音処理: _tsu_ → 次の子音を重ねる
        $roma = preg_replace_callback('/_tsu_([a-z])/', function ($m) {
            return $m[1] . $m[1];
        }, $roma);
        $roma = str_replace('_tsu_', 'tsu', $roma); // 末尾の促音

        if ($roma) {
            $romaParts[] = $roma;
        }
    }

    $slug = implode('-', $romaParts);

    // slugが空の場合（英語名など）はそのまま小文字化
    if (!$slug) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $slug = trim($slug, '-');
    }

    // スラッグが空または不正な場合は生成失敗
    if (!$slug || !preg_match(SLUG_PATTERN, $slug)) {
        return "";
    }

    // slug重複チェック
    $baseSlug = $slug;
    $counter = 2;
    while (isset($slugSet[$slug])) {
        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
    $slugSet[$slug] = true;

    return $slug;
}

$inserted = 0;
$skipped = 0;
$errors = [];

$insertStmt = $db->prepare('INSERT IGNORE INTO actresses (name, slug) VALUES (?, ?)');

foreach ($names as $name) {
    $name = trim($name);
    if (!$name) continue;

    // 重複チェック
    if (isset($existingSet[$name])) {
        $skipped++;
        continue;
    }

    $slug = generateSlug($name, $slugSet);
    if (!$slug) {
        $errors[] = $name;
        continue;
    }

    $insertStmt->execute([$name, $slug]);
    if ($insertStmt->rowCount() > 0) {
        $inserted++;
    } else {
        $skipped++;
    }
}

batchLog("完了: 新規 {$inserted} 名登録, {$skipped} 名スキップ");
if ($errors) {
    batchLog("slug生成エラー: " . implode(', ', $errors));
}

// キャッシュクリア
Cache::clear();
batchLog("キャッシュクリア完了");

// 登録結果のサマリー
$totalCount = $db->query('SELECT COUNT(*) FROM actresses')->fetchColumn();
batchLog("現在の総女優数: {$totalCount} 名");
