<?php

class SaleController
{
    public function index(array $params): void
    {
        $page = currentPage();
        $sort = $_GET['sort'] ?? '';
        $query = trim($_GET['q'] ?? '');

        if (!in_array($sort, ['', 'ending_soon', 'discount', 'price_low', 'price_high', 'newest'], true)) {
            $sort = '';
        }

        $totalWorks = Work::countSale($query);
        $pagination = paginate($totalWorks, ITEMS_PER_PAGE, $page);
        $works = Work::searchSale($sort, $query, ITEMS_PER_PAGE, $pagination['offset']);

        // サンプル画像を一括取得（Inline 画像表示用）
        $workIds = array_column($works, 'id');
        $workSampleImages = Work::getSampleImagesBulk($workIds);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => 'FANZA セール作品一覧',
            'url' => fullUrl('sale/'),
            'description' => 'FANZA で現在セール中の作品を検索・割引率/終了日でソート可能な一覧ページ',
        ];

        $titleSuffix = $query !== '' ? "「{$query}」の検索結果 - " : '';
        $breadcrumbs = [
            ['label' => 'TOP', 'url' => ''],
            ['label' => 'FANZA セール中の作品一覧'],
        ];

        render('sale', [
            'pageTitle' => $titleSuffix . 'FANZA セール中の作品一覧 | ' . SITE_NAME,
            'metaDescription' => 'FANZA で現在セール中の作品を一覧で表示。割引率・セール終了日でソートし、タイトル検索でお目当ての作品を素早く探せます。',
            'jsonLd' => $jsonLd,
            'breadcrumbs' => $breadcrumbs,
            'works' => $works,
            'workSampleImages' => $workSampleImages,
            'totalWorks' => $totalWorks,
            'pagination' => $pagination,
            'sort' => $sort,
            'query' => $query,
        ]);
    }
}
