<?php

class SitemapController
{
    public function index(array $params): void
    {
        header('Content-Type: application/xml; charset=UTF-8');

        $actresses = Actress::allForSitemap();
        $urls = [];

        // TOP
        $urls[] = [
            'loc' => fullUrl(),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];

        // 女優ページ + ジャンルページ
        foreach ($actresses as $actress) {
            $urls[] = [
                'loc' => fullUrl($actress['slug'] . '/'),
                'lastmod' => date('Y-m-d', strtotime($actress['updated_at'])),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];

            // 女優の全ジャンルスラッグ取得
            $actressObj = Actress::findBySlug($actress['slug']);
            if ($actressObj) {
                $genreSlugs = Genre::allSlugsForActress($actressObj['id']);
                foreach ($genreSlugs as $genreSlug) {
                    $urls[] = [
                        'loc' => fullUrl($actress['slug'] . '/' . $genreSlug . '/'),
                        'changefreq' => 'weekly',
                        'priority' => '0.6',
                    ];
                }
            }
        }

        render('sitemap', [
            'noLayout' => true,
            'urls' => $urls,
        ]);
    }
}
