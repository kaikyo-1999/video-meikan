<?php

class HomeController
{
    public function index(array $params): void
    {
        // 人気女優（作品数順、上位8名）
        $actresses = Actress::all();
        $pickupActresses = array_slice($actresses, 0, 8);

        // 最新記事5件
        $articles = ArticleController::allArticles();
        $latestArticles = array_slice($articles, 0, 5);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => SITE_NAME,
            'url' => fullUrl(),
            'description' => SITE_DESCRIPTION,
        ];

        render('home', [
            'pageTitle' => SITE_NAME . ' | ' . SITE_DESCRIPTION,
            'metaDescription' => SITE_DESCRIPTION,
            'jsonLd' => $jsonLd,
            'pickupActresses' => $pickupActresses,
            'latestArticles' => $latestArticles,
        ]);
    }
}
