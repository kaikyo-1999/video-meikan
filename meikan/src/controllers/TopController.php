<?php

class TopController
{
    public function index(array $params): void
    {
        $actresses = Actress::all();

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => SITE_TITLE,
            'numberOfItems' => count($actresses),
            'itemListElement' => [],
        ];

        foreach ($actresses as $i => $actress) {
            $jsonLd['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $actress['name'],
                'url' => fullUrl($actress['slug'] . '/'),
            ];
        }

        render('top', [
            'pageTitle' => SITE_TITLE . ' | ' . SITE_NAME,
            'metaDescription' => '人気AV女優のジャンル別作品データベース。女優名×ジャンルで作品を探せます。',
            'breadcrumbs' => [
                ['label' => '名鑑TOP', 'url' => ''],
            ],
            'actresses' => $actresses,
            'jsonLd' => $jsonLd,
        ]);
    }
}
