<?php

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/src/helpers.php';
require_once __DIR__ . '/src/Cache.php';
require_once __DIR__ . '/src/Router.php';

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
    require_once __DIR__ . '/src/controllers/TopController.php';
    require_once __DIR__ . '/src/controllers/ActressController.php';
    require_once __DIR__ . '/src/controllers/GenreController.php';
    require_once __DIR__ . '/src/controllers/SitemapController.php';
}

$router = new Router();

// 記事系ルート（常に有効）
$router->add('articles/', 'ArticleController@index');
$router->add('articles/{article_slug}/', 'ArticleController@show');
$router->add('author/', 'AuthorController@show');

// DB系ルート（DB接続可能な場合のみ）
if ($dbAvailable) {
    $router->add('', 'TopController@index');
    $router->add('sitemap.xml', 'SitemapController@index');
    $router->add('{actress_slug}/', 'ActressController@show');
    $router->add('{actress_slug}/{genre_slug}/', 'GenreController@show');
} else {
    // DBなし時はTOPを記事一覧にフォールバック
    $router->add('', 'ArticleController@index');
}

$router->dispatch();
