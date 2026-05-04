<?php

define('SITE_NAME', 'AV博士');
define('SITE_TITLE', 'AV女優ジャンル別名鑑');
define('SITE_DESCRIPTION', '人気AV女優のジャンル別作品データベース');
define('BASE_PATH', '');
define('BASE_URL', BASE_PATH . '/');

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}
define('TEMPLATE_DIR', ROOT_DIR . '/templates');
define('CACHE_DIR', ROOT_DIR . '/cache');
define('LOG_DIR', ROOT_DIR . '/logs');

define('ITEMS_PER_PAGE', 20);
define('ACTRESS_WORK_THRESHOLD', 10); // この数以下の作品数ならジャンルページを作らない
define('GENRE_MIN_WORKS', 3); // この数未満のジャンル該当作品数なら noindex かつ sitemap 除外
define('CACHE_TTL', 3600); // 1時間

define('SLUG_PATTERN', '/^[a-z0-9][a-z0-9-]*$/');

/**
 * TOPページ「ジャンルから探す」タイル枠の固定スラッグ (12件)
 * DB に存在するもののみ表示される。順序はそのまま表示順に使う。
 */
define('FEATURED_GENRE_SLUGS', [
    'kyonyu',     // 巨乳
    'chijo',      // 痴女
    'shiroto',    // 素人
    'bakunyu',    // 爆乳
    'fera',       // フェラ・イラマチオ
    'paizuri',    // パイズリ
    'shiofuki',   // 潮吹き
    'mens-esthe', // メンズエステ
    'soap',       // ソープ
    'ahegao',     // アヘ顔
    'shukanpov',  // 主観POV
    'gansha',     // 顔射・ぶっかけ
]);
