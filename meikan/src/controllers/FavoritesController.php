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

        // 「人気の女優TOP5」（DB利用可能時のみ。離脱防止のための導線）
        $popularActresses = [];
        if (class_exists('Actress')) {
            try {
                $all = Actress::all();
                $popularActresses = array_slice($all, 0, 5);
            } catch (Throwable $e) {
                $popularActresses = [];
            }
        }

        render('favorites', compact('pageTitle', 'metaDescription', 'noindex', 'breadcrumbs', 'popularActresses'));
    }
}
