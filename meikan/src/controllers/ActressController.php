<?php

class ActressController
{
    public function show(array $params): void
    {
        $actress = Actress::findBySlug($params['actress_slug']);
        if (!$actress) {
            http_response_code(404);
            render('404', ['pageTitle' => 'ページが見つかりません | ' . SITE_NAME]);
            return;
        }

        $genres = Actress::getGenres($actress['id']);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $actress['name'] . 'のジャンル別作品',
            'numberOfItems' => count($genres),
            'itemListElement' => [],
        ];

        foreach ($genres as $i => $genre) {
            $jsonLd['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $genre['name'],
                'url' => fullUrl($actress['slug'] . '/' . $genre['slug'] . '/'),
            ];
        }

        render('actress', [
            'pageTitle' => $actress['name'] . 'のジャンル別作品一覧 | ' . SITE_NAME,
            'metaDescription' => $actress['name'] . 'の出演作品をジャンル別に一覧表示。',
            'breadcrumbs' => [
                ['label' => 'TOP', 'url' => ''],
                ['label' => $actress['name'], 'url' => ''],
            ],
            'actress' => $actress,
            'genres' => $genres,
            'jsonLd' => $jsonLd,
        ]);
    }
}
