<?php

class CrossLinkController
{
    public function show(array $params): void
    {
        $links = [
            [
                'name' => 'ネトラレマガジン',
                'url' => 'https://ntr-magazine.com/',
                'description' => 'おすすめの2次元「寝取られ系」作品をご紹介するブログ。成年コミック/同人コミック/CG/アニメのレビューや特集記事、セール情報などをお届けします。',
            ],
        ];

        render('cross-link', [
            'pageTitle' => '相互リンク | ' . SITE_NAME,
            'metaDescription' => SITE_NAME . 'の相互リンク集。AV・アダルト系のおすすめサイトをご紹介しています。相互リンクも随時募集中です。',
            'breadcrumbs' => [
                ['label' => 'TOP', 'url' => ''],
                ['label' => '相互リンク', 'url' => ''],
            ],
            'links' => $links,
            'contactEmail' => 'contact@av-hakase.com',
            'siteUrl' => fullUrl(),
        ]);
    }
}
