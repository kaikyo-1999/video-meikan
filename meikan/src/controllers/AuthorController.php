<?php

class AuthorController
{
    public function show(array $params): void
    {
        $articles = ArticleController::allArticles();

        render('author', [
            'pageTitle' => SITE_NAME . 'のプロフィール | ' . SITE_NAME,
            'metaDescription' => SITE_NAME . 'のプロフィール。AV歴10年超、毎月1万円以上課金する独身ひとり暮らしのAVオタクが運営するデータベースサイトです。',
            'breadcrumbs' => [
                ['label' => 'TOP', 'url' => ''],
                ['label' => '著者について', 'url' => ''],
            ],
            'articles' => $articles,
        ]);
    }
}
