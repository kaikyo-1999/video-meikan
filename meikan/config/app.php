<?php

define('SITE_NAME', 'av博士');
define('SITE_TITLE', 'av博士');
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
define('CACHE_TTL', 3600); // 1時間

define('SLUG_PATTERN', '/^[a-z0-9][a-z0-9-]*$/');
