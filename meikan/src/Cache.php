<?php

/**
 * キャッシュ層。
 *
 * バックエンドは2段構成:
 *  1. OPcache 配列キャッシュ (cache/opc/{hash}.php) — var_export → include 方式。
 *     OPcache がメモリに乗せるため APCu 同等の速度。
 *  2. 既存ファイルキャッシュ (cache/{hash}.cache) — fallback / 互換用。
 *
 * 既存呼び出し API (get/set/clear) は変更なし。
 */
class Cache
{
    public static function get(string $key): mixed
    {
        // OPcache backend を先に試す
        $opcFile = self::opcPath($key);
        if (file_exists($opcFile)) {
            $entry = @include $opcFile;
            if (is_array($entry) && isset($entry['expires'], $entry['value'])) {
                if ($entry['expires'] >= time()) {
                    return $entry['value'];
                }
                @unlink($opcFile);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($opcFile, true);
                }
            }
        }

        // 旧ファイルキャッシュ fallback
        $file = self::path($key);
        if (!file_exists($file)) return null;

        $data = @unserialize(file_get_contents($file));
        if (!is_array($data) || !isset($data['expires'])) return null;
        if ($data['expires'] < time()) {
            @unlink($file);
            return null;
        }

        return $data['value'];
    }

    public static function set(string $key, mixed $value, int $ttl = CACHE_TTL): void
    {
        // 1) OPcache backend (var_export可能なデータのみ)
        if (self::isVarExportable($value)) {
            $opcFile = self::opcPath($key);
            $opcDir  = dirname($opcFile);
            if (!is_dir($opcDir)) {
                @mkdir($opcDir, 0755, true);
            }
            $entry = ['expires' => time() + $ttl, 'value' => $value];
            $php   = "<?php return " . var_export($entry, true) . ";\n";
            // アトミック書き込み (rename) で半端な include を防ぐ
            $tmp = $opcFile . '.tmp.' . getmypid();
            if (@file_put_contents($tmp, $php, LOCK_EX) !== false) {
                @rename($tmp, $opcFile);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($opcFile, true);
                }
                return;
            }
        }

        // 2) fallback: 旧ファイルキャッシュ
        if (!is_dir(CACHE_DIR)) {
            @mkdir(CACHE_DIR, 0755, true);
        }
        $data = ['expires' => time() + $ttl, 'value' => $value];
        @file_put_contents(self::path($key), serialize($data), LOCK_EX);
    }

    public static function clear(): void
    {
        // OPcache backend
        $opcDir = CACHE_DIR . '/opc';
        if (is_dir($opcDir)) {
            foreach (glob($opcDir . '/*.php') ?: [] as $file) {
                @unlink($file);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($file, true);
                }
            }
        }
        // 旧ファイルキャッシュ
        if (is_dir(CACHE_DIR)) {
            foreach (glob(CACHE_DIR . '/*.cache') ?: [] as $file) {
                @unlink($file);
            }
        }
    }

    private static function path(string $key): string
    {
        return CACHE_DIR . '/' . md5($key) . '.cache';
    }

    private static function opcPath(string $key): string
    {
        return CACHE_DIR . '/opc/' . md5($key) . '.php';
    }

    /**
     * var_export で再構築可能か判定 (リソース・無名関数・無名クラスを含むデータは不可)
     */
    private static function isVarExportable(mixed $value): bool
    {
        if (is_resource($value)) return false;
        if (is_object($value)) {
            if ($value instanceof Closure) return false;
            // 無名クラス / __set_state 未実装の class は var_export 不可
            $rc = new ReflectionClass($value);
            if ($rc->isAnonymous()) return false;
            if (!$rc->hasMethod('__set_state')) return false;
        }
        if (is_array($value)) {
            foreach ($value as $v) {
                if (!self::isVarExportable($v)) return false;
            }
        }
        return true;
    }
}
