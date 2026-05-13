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

        $workCount = (int)$actress['work_count'];
        $isFewWorks = $workCount <= ACTRESS_WORK_THRESHOLD;

        // 作品数が少ない場合はジャンル取得をスキップ
        $genres = [];
        if (!$isFewWorks) {
            $genres = Actress::getGenres($actress['id']);
        }

        $similarActresses = Actress::getSimilarActresses($actress['id']);

        // 似ている女優が空の場合、タグ+デビュー時期ベースの関連女優をフォールバック
        // さらに関連女優も空なら逆引き（他女優のsimilar/relatedに含まれている）
        $relatedActresses = [];
        if (empty($similarActresses)) {
            $relatedActresses = Actress::getRelatedActresses($actress['id']);
            if (empty($relatedActresses)) {
                $relatedActresses = Actress::getReverseLookupActresses($actress['id']);
            }
        }

        // 作品一覧を表示（デフォルト: 単体作品のみ・人気順）
        $worksPage = currentPage();
        $totalWorks = Work::countSingleByActress($actress['id']);
        $worksPagination = paginate($totalWorks, ITEMS_PER_PAGE, $worksPage);
        $works = Work::findByActressPaginated($actress['id'], ITEMS_PER_PAGE, $worksPagination['offset']);

        // サンプル画像を一括取得
        $workIds = array_column($works, 'id');
        $workSampleImages = Work::getSampleImagesBulk($workIds);

        // Person スキーマ
        $person = [
            '@type' => 'Person',
            'name' => $actress['name'],
            'url' => fullUrl($actress['slug'] . '/'),
        ];
        if (!empty($actress['thumbnail_url'])) {
            $person['image'] = $actress['thumbnail_url'];
        }
        if (!empty($actress['birthday'])) {
            $person['birthDate'] = $actress['birthday'];
        }
        if (!empty($actress['height'])) {
            $person['height'] = [
                '@type' => 'QuantitativeValue',
                'value' => (int)$actress['height'],
                'unitCode' => 'CMT',
            ];
        }

        // ItemList スキーマ
        $itemList = [
            '@type' => 'ItemList',
            'name' => $actress['name'] . 'のジャンル別作品',
            'numberOfItems' => count($genres) ?: count($works),
            'itemListElement' => [],
        ];

        if (!empty($genres)) {
            foreach ($genres as $i => $genre) {
                $itemList['itemListElement'][] = [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $genre['name'],
                    'url' => fullUrl($actress['slug'] . '/' . $genre['slug'] . '/'),
                ];
            }
        } else {
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
                $itemList['itemListElement'][] = [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'item' => $product,
                ];
            }
        }

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [$person, $itemList],
        ];

        $metaName = $actress['name'];
        $metaWorkCount = $workCount;

        $profileParts = [];
        if (!empty($actress['birthday'])) {
            $age = (new DateTime($actress['birthday']))->diff(new DateTime())->y;
            $profileParts[] = "{$age}歳";
        }
        if (!empty($actress['bust']) && !empty($actress['waist']) && !empty($actress['hip'])) {
            $profileParts[] = "B{$actress['bust']}/W{$actress['waist']}/H{$actress['hip']}";
        }
        $profilePart = $profileParts ? '（' . implode('・', $profileParts) . '）' : '';

        $latestRelease = Work::latestReleaseDateByActress($actress['id']);
        $latestTag = latestReleaseTag($latestRelease);
        $latestMonth = latestReleaseMonth($latestRelease);
        $latestTagSuffix = $latestTag ? " {$latestTag}" : '';
        $latestSentence = $latestMonth ? "{$latestMonth}発売の最新作まで収録。" : '';

        if ($isFewWorks) {
            $pageTitle = "{$metaName}のAV作品一覧（{$metaWorkCount}本）｜無料画像・動画付き{$latestTagSuffix} | " . SITE_NAME;
            $metaDescription = "{$metaName}{$profilePart}のAV作品{$metaWorkCount}本をまとめて一覧化。無料で見れる画像・動画を掲載。{$latestSentence}FANZAで配信中。";
        } else {
            $topGenresText = implode('、', array_slice(array_column($genres, 'name'), 0, 3));
            $genresClause = $topGenresText ? "{$topGenresText}など人気ジャンル別に整理し、" : '';
            $pageTitle = "{$metaName}の作品一覧 全{$metaWorkCount}本網羅｜無料画像・動画付き{$latestTagSuffix} | " . SITE_NAME;
            $metaDescription = "{$metaName}{$profilePart}の全作品{$metaWorkCount}本を一覧化。{$genresClause}無料で見れる画像・動画を掲載。{$latestSentence}FANZAで配信中。";
        }

        render('actress', [
            'pageTitle' => $pageTitle,
            'metaDescription' => $metaDescription,
            'breadcrumbs' => [
                ['label' => 'TOP', 'url' => ''],
                ['label' => $actress['name'], 'url' => ''],
            ],
            'actress' => $actress,
            'genres' => $genres,
            'works' => $works,
            'workSampleImages' => $workSampleImages,
            'totalWorks' => $totalWorks,
            'worksPagination' => $worksPagination,
            'isFewWorks' => $isFewWorks,
            'similarActresses' => $similarActresses,
            'relatedActresses' => $relatedActresses,
            'jsonLd' => $jsonLd,
        ]);
    }
}
