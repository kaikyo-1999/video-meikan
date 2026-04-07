<?php
/**
 * キャッシュウォームアップバッチ
 * clear_cache.php 後に実行し、重いクエリのキャッシュを事前生成する
 * Usage: php batch/warm_cache.php
 */

require_once __DIR__ . '/config.php';

batchLog("キャッシュウォームアップ開始");

// TopページのActress::all()を事前実行
Actress::all();
batchLog("Actress::all() キャッシュ生成完了");

batchLog("キャッシュウォームアップ完了");
