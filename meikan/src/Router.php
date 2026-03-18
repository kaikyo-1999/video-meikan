<?php

class Router
{
    private array $routes = [];

    public function add(string $pattern, string $handler): void
    {
        $this->routes[] = ['pattern' => $pattern, 'handler' => $handler];
    }

    public function dispatch(): void
    {
        $path = $this->getPath();

        foreach ($this->routes as $route) {
            $params = $this->match($route['pattern'], $path);
            if ($params !== false) {
                $this->call($route['handler'], $params);
                return;
            }
        }

        http_response_code(404);
        render('404', ['pageTitle' => 'ページが見つかりません | ' . SITE_NAME]);
    }

    private function getPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $uri = parse_url($uri, PHP_URL_PATH);
        $base = rtrim(BASE_PATH, '/') . '/';

        if (str_starts_with($uri, $base)) {
            return substr($uri, strlen($base));
        }

        return '';
    }

    private function match(string $pattern, string $path): array|false
    {
        // 完全一致
        if ($pattern === $path) {
            return [];
        }

        // パラメータ付きパターン
        $regex = preg_replace('/\{([a-z_]+)\}/', '(?P<$1>[a-z0-9-]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            // スラッグのバリデーション
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($params as $value) {
                if (!preg_match(SLUG_PATTERN, $value)) {
                    return false;
                }
            }
            return $params;
        }

        return false;
    }

    private function call(string $handler, array $params): void
    {
        [$class, $method] = explode('@', $handler);
        $controller = new $class();
        $controller->$method($params);
    }
}
