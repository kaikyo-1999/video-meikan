<?php
/**
 * キャッシュクリアバッチ
 * Usage: php batch/clear_cache.php
 */

require_once __DIR__ . '/config.php';

Cache::clear();
batchLog("キャッシュクリア完了");
