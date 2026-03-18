<?php

class Cache
{
    public static function get(string $key): mixed
    {
        $file = self::path($key);
        if (!file_exists($file)) return null;

        $data = unserialize(file_get_contents($file));
        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }

    public static function set(string $key, mixed $value, int $ttl = CACHE_TTL): void
    {
        if (!is_dir(CACHE_DIR)) {
            mkdir(CACHE_DIR, 0755, true);
        }

        $data = [
            'expires' => time() + $ttl,
            'value' => $value,
        ];

        file_put_contents(self::path($key), serialize($data), LOCK_EX);
    }

    public static function clear(): void
    {
        if (!is_dir(CACHE_DIR)) return;

        $files = glob(CACHE_DIR . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    private static function path(string $key): string
    {
        return CACHE_DIR . '/' . md5($key) . '.cache';
    }
}
