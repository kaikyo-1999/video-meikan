<?php

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Cache.php';
require_once __DIR__ . '/src/Router.php';

// 301リダイレクト: 旧URL → 新URL
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (preg_match('#^/articles/(.*)$#', $uri, $m)) {
    header('Location: /article/' . $m[1], true, 301);
    exit;
}

// スラグ変更リダイレクト: 旧女優slug → 新slug
$slugRedirectFile = __DIR__ . '/config/slug_redirects.php';
if (file_exists($slugRedirectFile)) {
    $slugRedirects = require $slugRedirectFile;
    // /old-slug/ or /old-slug/genre-slug/ のパターンに対応
    if (preg_match('#^/([a-z0-9][a-z0-9-]*)(/.*)?\s*$#', $uri, $m)) {
        $slug = $m[1];
        $rest = $m[2] ?? '/';
        if (isset($slugRedirects[$slug])) {
            header('Location: /' . $slugRedirects[$slug] . $rest, true, 301);
            exit;
        }
    }
}

// 記事系（DB不要）
require_once __DIR__ . '/src/controllers/ArticleController.php';
require_once __DIR__ . '/src/controllers/AuthorController.php';

// DB系（ファイルが存在する場合のみ読み込み）
$dbAvailable = file_exists(__DIR__ . '/config/database.php') && file_exists(__DIR__ . '/.env');
if ($dbAvailable) {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/src/Database.php';
    require_once __DIR__ . '/src/models/Actress.php';
    require_once __DIR__ . '/src/models/Genre.php';
    require_once __DIR__ . '/src/models/Work.php';
    require_once __DIR__ . '/src/controllers/HomeController.php';
    require_once __DIR__ . '/src/controllers/TopController.php';
    require_once __DIR__ . '/src/controllers/ActressController.php';
    require_once __DIR__ . '/src/controllers/GenreController.php';
    require_once __DIR__ . '/src/controllers/SitemapController.php';
    require_once __DIR__ . '/src/controllers/ApiController.php';
    require_once __DIR__ . '/src/models/Fc2Work.php';
    require_once __DIR__ . '/src/controllers/Fc2RankingController.php';
}

$router = new Router();

// 記事系ルート（常に有効）
$router->add('article/', 'ArticleController@index');
$router->add('article/{article_slug}/', 'ArticleController@show');
$router->add('author/', 'AuthorController@show');

// DB系ルート（DB接続可能な場合のみ）
if ($dbAvailable) {
    $router->add('', 'HomeController@index');
    $router->add('meikan/', 'TopController@index');
    $router->add('api/works/', 'ApiController@works');
    $router->add('sitemap.xml', 'SitemapController@index');
    $router->add('fc2/', 'Fc2RankingController@index');
    $router->add('fc2/submit/', 'Fc2RankingController@submit');
    $router->add('fc2/vote/', 'Fc2RankingController@vote');
    $router->add('{actress_slug}/', 'ActressController@show');
    $router->add('{actress_slug}/{genre_slug}/', 'GenreController@show');
} else {
    // DBなし時はTOPを記事一覧にフォールバック
    $router->add('', 'ArticleController@index');
}

$router->dispatch();
