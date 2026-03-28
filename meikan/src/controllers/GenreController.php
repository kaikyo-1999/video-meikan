<?php

class GenreController
{
    public function show(array $params): void
    {
        $actress = Actress::findBySlug($params['actress_slug']);
        if (!$actress) {
            http_response_code(404);
            render('404', ['pageTitle' => 'ページが見つかりません | ' . SITE_NAME]);
            return;
        }

        $genre = Genre::findBySlug($params['genre_slug']);
        if (!$genre) {
            http_response_code(404);
            render('404', ['pageTitle' => 'ページが見つかりません | ' . SITE_NAME]);
            return;
        }

        $page = currentPage();
        $totalWorks = Work::countByActressAndGenre($actress['id'], $genre['id']);

        if ($totalWorks === 0) {
            http_response_code(404);
            render('404', ['pageTitle' => 'ページが見つかりません | ' . SITE_NAME]);
            return;
        }

        $pagination = paginate($totalWorks, ITEMS_PER_PAGE, $page);
        $works = Work::findByActressAndGenre($actress['id'], $genre['id'], ITEMS_PER_PAGE, $pagination['offset']);
        $similarActresses = Actress::getSimilarActresses($actress['id']);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $actress['name'] . 'の' . $genre['name'] . '作品一覧',
            'numberOfItems' => $totalWorks,
            'itemListElement' => [],
        ];

        foreach ($works as $i => $work) {
            $jsonLd['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $pagination['offset'] + $i + 1,
                'name' => $work['title'],
                'url' => $work['affiliate_url'] ?? '',
            ];
        }

        render('genre', [
            'pageTitle' => $actress['name'] . 'の' . $genre['name'] . '作品一覧 | ' . SITE_NAME,
            'metaDescription' => $actress['name'] . 'の' . $genre['name'] . '作品を一覧表示。全' . $totalWorks . '作品。',
            'breadcrumbs' => [
                ['label' => 'TOP', 'url' => ''],
                ['label' => $actress['name'], 'url' => $actress['slug'] . '/'],
                ['label' => $genre['name'], 'url' => ''],
            ],
            'actress' => $actress,
            'genre' => $genre,
            'works' => $works,
            'totalWorks' => $totalWorks,
            'pagination' => $pagination,
            'similarActresses' => $similarActresses,
            'jsonLd' => $jsonLd,
        ]);
    }
}
