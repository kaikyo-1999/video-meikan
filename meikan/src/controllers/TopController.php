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
            'metaDescription' => 'AV女優' . count($actresses) . '人をジャンル別に検索できる名鑑。巨乳・痴女・素人など多数のジャンルから好みの作品を探せます。',
            'breadcrumbs' => [
                ['label' => 'TOP', 'url' => ''],
                ['label' => '名鑑TOP', 'url' => ''],
            ],
            'actresses' => $actresses,
            'jsonLd' => $jsonLd,
        ]);
    }
}
