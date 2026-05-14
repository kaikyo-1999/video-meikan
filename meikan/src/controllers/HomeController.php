<?php

class HomeController
{
    public function index(array $params): void
    {
        // 新人女優セクション（Actress::findRecentDebut で共通化）
        $debut = Actress::findRecentDebut(6);
        $debutActresses = $debut['actresses'];
        $debutMonthLabel = $debut['monthLabel'];
        $debutArticleSlug = $debut['articleSlug'];

        // 最新記事5件
        $articles = ArticleController::allArticles();
        $latestArticles = array_slice($articles, 0, 5);

        // ② FC2 注目ランキング (週間 TOP10)
        $fc2Ranking = Fc2Work::getRanking('week', 10, 0);

        // ③ 今週ホットな作品 (fanza_click 急上昇)
        $hotWorks = Work::findHotByVelocity(10);

        // ④ FANZA セール中 (works テーブル直接抽出)
        $saleWorks = Work::findOnSale(10);

        // ⑤ PV急上昇女優 TOP6
        $hotActresses = Actress::findHotByPv(6);

        // ⑦ 「ジャンルから探す」12タイル
        $featuredGenres = Genre::findFeatured(12);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => SITE_NAME,
            'url' => fullUrl(),
            'description' => SITE_DESCRIPTION,
        ];

        $actressCount = Actress::count();
        $workCount = Work::countAll();
        $articleCount = count($articles);

        // ③ ホット作品 / ⑤ PV急上昇女優 の最終集計時刻
        $hotWorksUpdatedAt = Work::lastSignalUpdate();
        $hotActressesUpdatedAt = Actress::lastSignalUpdate();

        render('home', [
            'pageTitle' => SITE_NAME . ' | ' . SITE_DESCRIPTION,
            'metaDescription' => '人気AV女優' . $actressCount . '人以上のジャンル別作品データベース。新人デビュー情報・ホット作品・FANZAセール作品・最新コラムを毎日更新中。',
            'jsonLd' => $jsonLd,
            'actressCount' => $actressCount,
            'workCount' => $workCount,
            'articleCount' => $articleCount,
            'hotWorksUpdatedAt' => $hotWorksUpdatedAt,
            'hotActressesUpdatedAt' => $hotActressesUpdatedAt,
            'debutActresses' => $debutActresses,
            'debutMonthLabel' => $debutMonthLabel,
            'debutArticleSlug' => $debutArticleSlug,
            'latestArticles' => $latestArticles,
            'fc2Ranking' => $fc2Ranking,
            'hotWorks' => $hotWorks,
            'saleWorks' => $saleWorks,
            'hotActresses' => $hotActresses,
            'featuredGenres' => $featuredGenres,
        ]);
    }
}
