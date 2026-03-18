<?php
/**
 * 初期女優データ投入バッチ
 * Usage: php batch/import_actresses.php
 */

require_once __DIR__ . '/config.php';

$actresses = [
    ['name' => '三上悠亜', 'slug' => 'mikami-yua'],
    ['name' => '橋本ありな', 'slug' => 'hashimoto-arina'],
    ['name' => '明日花キララ', 'slug' => 'asuka-kirara'],
    ['name' => '河北彩花', 'slug' => 'kawakita-saika'],
    ['name' => '小倉由菜', 'slug' => 'ogura-yuna'],
    ['name' => '天使もえ', 'slug' => 'amatsuka-moe'],
    ['name' => '深田えいみ', 'slug' => 'fukada-eimi'],
    ['name' => '篠田ゆう', 'slug' => 'shinoda-yuu'],
    ['name' => '楓カレン', 'slug' => 'kaede-karen'],
    ['name' => '架乃ゆら', 'slug' => 'kano-yura'],
];

$genres = [
    ['name' => '巨乳', 'slug' => 'kyonyu'],
    ['name' => '熟女', 'slug' => 'jukujo'],
    ['name' => 'ギャル', 'slug' => 'gal'],
    ['name' => '人妻・主婦', 'slug' => 'hitozuma'],
    ['name' => '女子校生', 'slug' => 'joshikousei'],
    ['name' => '中出し', 'slug' => 'nakadashi'],
    ['name' => 'アナル', 'slug' => 'anal'],
    ['name' => 'アイドル・芸能人', 'slug' => 'idol'],
    ['name' => '姉・妹', 'slug' => 'ane-imouto'],
    ['name' => 'OL', 'slug' => 'ol'],
    ['name' => 'お母さん', 'slug' => 'okasan'],
    ['name' => 'お姉さん', 'slug' => 'oneesan'],
    ['name' => '女教師', 'slug' => 'onna-kyoushi'],
    ['name' => '女上司', 'slug' => 'onna-joushi'],
    ['name' => '看護婦・ナース', 'slug' => 'nurse'],
    ['name' => '義母', 'slug' => 'gibo'],
    ['name' => '家庭教師', 'slug' => 'kateikyoushi'],
    ['name' => '近親相姦', 'slug' => 'kinshin'],
    ['name' => '幼なじみ', 'slug' => 'osananajimi'],
    ['name' => 'エステ', 'slug' => 'esthe'],
    ['name' => 'カップル', 'slug' => 'couple'],
    ['name' => '逆ナン', 'slug' => 'gyakunan'],
    ['name' => 'M男', 'slug' => 'm-otoko'],
    ['name' => '寝取り・寝取られ・NTR', 'slug' => 'ntr'],
    ['name' => '痴漢', 'slug' => 'chikan'],
    ['name' => '痴女', 'slug' => 'chijo'],
    ['name' => '3P・4P', 'slug' => '3p-4p'],
    ['name' => '乱交', 'slug' => 'rankou'],
    ['name' => 'コスプレ', 'slug' => 'cosplay'],
    ['name' => 'アクメ・オーガズム', 'slug' => 'acme'],
];

$db = Database::getInstance();

// 女優を挿入
$stmt = $db->prepare('INSERT IGNORE INTO actresses (name, slug) VALUES (?, ?)');
foreach ($actresses as $a) {
    $stmt->execute([$a['name'], $a['slug']]);
    batchLog("女優登録: {$a['name']} ({$a['slug']})");
}

// ジャンルを挿入
$stmt = $db->prepare('INSERT IGNORE INTO genres (name, slug) VALUES (?, ?)');
foreach ($genres as $g) {
    $stmt->execute([$g['name'], $g['slug']]);
    batchLog("ジャンル登録: {$g['name']} ({$g['slug']})");
}

batchLog("初期データ投入完了: 女優 " . count($actresses) . "名, ジャンル " . count($genres) . "件");
