<?php

class FavoritesController
{
    public function show(array $params = []): void
    {
        $pageTitle = 'お気に入り | ' . SITE_NAME;
        $metaDescription = 'お気に入り登録した女優・作品の一覧。端末ごとにブラウザに保存されます。';
        $noindex = true; // ユーザー固有ページなので検索結果に出さない
        $breadcrumbs = [
            ['label' => 'TOP', 'url' => ''],
            ['label' => 'お気に入り', 'url' => 'favorites/'],
        ];

        render('favorites', compact('pageTitle', 'metaDescription', 'noindex', 'breadcrumbs'));
    }
}
