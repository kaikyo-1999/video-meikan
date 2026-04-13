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

        // 作品数が少ない女優はジャンルページを作らない → 女優ページへ301リダイレクト
        if ((int)$actress['work_count'] <= ACTRESS_WORK_THRESHOLD) {
            header('Location: ' . url($actress['slug'] . '/'), true, 301);
            return;
        }

        $genre = Genre::findBySlug($params['genre_slug']);
        if (!$genre) {
            http_response_code(404);
            render('404', ['pageTitle' => 'ページが見つかりません | ' . SITE_NAME]);
            return;
        }

        $page = currentPage();
        $totalWorksAll = Work::countByActressAndGenre($actress['id'], $genre['id']);

        if ($totalWorksAll === 0) {
            http_response_code(404);
            render('404', ['pageTitle' => 'ページが見つかりません | ' . SITE_NAME]);
            return;
        }

        // デフォルト: 単体作品のみ・人気順
        $totalWorks = Work::countSingleByActressAndGenre($actress['id'], $genre['id']);
        $pagination = paginate($totalWorks, ITEMS_PER_PAGE, $page);
        $works = Work::findByActressAndGenre($actress['id'], $genre['id'], ITEMS_PER_PAGE, $pagination['offset']);
        $workIds = array_column($works, 'id');
        $workSampleImages = Work::getSampleImagesBulk($workIds);
        $allGenres = Actress::getGenres($actress['id']);
        $similarActresses = Actress::getSimilarActresses($actress['id']);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $actress['name'] . 'の' . $genre['name'] . '作品一覧',
            'numberOfItems' => $totalWorksAll,
            'itemListElement' => [],
        ];

        foreach ($works as $i => $work) {
            $product = [
                '@type' => 'Product',
                'name' => $work['title'],
                'sku' => $work['source_id'],
            ];
            if (!empty($work['thumbnail_url'])) {
                $product['image'] = $work['thumbnail_url'];
            }
            if (!empty($work['affiliate_url'])) {
                $product['offers'] = [
                    '@type' => 'Offer',
                    'url' => $work['affiliate_url'],
                    'priceCurrency' => 'JPY',
                    'availability' => 'https://schema.org/InStock',
                ];
            }
            if (!empty($work['review_average']) && !empty($work['review_count'])) {
                $product['aggregateRating'] = [
                    '@type' => 'AggregateRating',
                    'ratingValue' => round((float)$work['review_average'], 2),
                    'reviewCount' => (int)$work['review_count'],
                ];
            }
            $jsonLd['itemListElement'][] = [
                '@type' => 'ListItem',
                'position' => $pagination['offset'] + $i + 1,
                'item' => $product,
            ];
        }

        render('genre', [
            'pageTitle' => $actress['name'] . 'の' . $genre['name'] . '作品一覧 | ' . SITE_NAME,
            'metaDescription' => $actress['name'] . 'の' . $genre['name'] . '作品' . $totalWorksAll . '本を人気順・新着順で一覧。サンプル画像・レビュー評価付きで好みの作品が見つかる。' . date('Y') . '年最新作も随時更新。',
            'breadcrumbs' => [
                ['label' => 'TOP', 'url' => ''],
                ['label' => $actress['name'], 'url' => $actress['slug'] . '/'],
                ['label' => $genre['name'], 'url' => ''],
            ],
            'actress' => $actress,
            'genre' => $genre,
            'works' => $works,
            'workSampleImages' => $workSampleImages,
            'totalWorks' => $totalWorks,
            'pagination' => $pagination,
            'allGenres' => $allGenres,
            'similarActresses' => $similarActresses,
            'jsonLd' => $jsonLd,
        ]);
    }
}
