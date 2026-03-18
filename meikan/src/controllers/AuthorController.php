<?php

class AuthorController
{
    public function show(array $params): void
    {
        $articles = ArticleController::allArticles();

        render('author', [
            'pageTitle' => 'av博士のプロフィール | ' . SITE_NAME,
            'metaDescription' => 'av博士のプロフィール。AV歴10年超、毎月1万円以上課金する独身ひとり暮らしのAVオタクが運営するデータベースサイトです。',
            'breadcrumbs' => [
                ['label' => 'TOP', 'url' => ''],
                ['label' => '著者について', 'url' => ''],
            ],
            'articles' => $articles,
        ]);
    }
}
