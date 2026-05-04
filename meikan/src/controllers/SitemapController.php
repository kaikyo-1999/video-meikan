<?php

class SitemapController
{
    /**
     * サイトマップindex（sitemapindex形式・/sitemap.xml）
     * 子サイトマップ4本を束ねる
     */
    public function index(array $params): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        $today = date('Y-m-d');
        $sitemaps = [
            ['loc' => fullUrl('sitemap-core.xml'), 'lastmod' => $today],
            ['loc' => fullUrl('sitemap-articles.xml'), 'lastmod' => $today],
            ['loc' => fullUrl('sitemap-actresses.xml'), 'lastmod' => $today],
            ['loc' => fullUrl('sitemap-genres.xml'), 'lastmod' => $today],
        ];
        render('sitemap-index', ['noLayout' => true, 'sitemaps' => $sitemaps]);
    }

    /**
     * サイト基幹ページ
     */
    public function core(array $params): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        $urls = [
            ['loc' => fullUrl(), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => fullUrl('meikan/'), 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => fullUrl('article/'), 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => fullUrl('author/'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => fullUrl('cross-link/'), 'changefreq' => 'monthly', 'priority' => '0.3'],
        ];
        render('sitemap', ['noLayout' => true, 'urls' => $urls]);
    }

    /**
     * 個別記事
     */
    public function articles(array $params): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        $articles = ArticleController::allArticles();
        $urls = [];
        foreach ($articles as $article) {
            if (!empty($article['noindex'])) continue;
            $entry = [
                'loc' => fullUrl('article/' . $article['slug'] . '/'),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
            if (!empty($article['updated_at'])) {
                $entry['lastmod'] = $article['updated_at'];
            } elseif (!empty($article['published_at'])) {
                $entry['lastmod'] = $article['published_at'];
            }
            $urls[] = $entry;
        }
        render('sitemap', ['noLayout' => true, 'urls' => $urls]);
    }

    /**
     * 女優ページ
     */
    public function actresses(array $params): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        $actresses = Actress::allForSitemap();
        $urls = [];
        foreach ($actresses as $actress) {
            $entry = [
                'loc' => fullUrl($actress['slug'] . '/'),
                'lastmod' => date('Y-m-d', strtotime($actress['updated_at'])),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
            if (!empty($actress['thumbnail_url'])) {
                $entry['images'] = [
                    ['loc' => $actress['thumbnail_url'], 'title' => $actress['name']],
                ];
            }
            $urls[] = $entry;
        }
        render('sitemap', ['noLayout' => true, 'urls' => $urls]);
    }

    /**
     * 女優×ジャンルページ
     * - 親女優の作品数 > ACTRESS_WORK_THRESHOLD のもの
     * - かつ ジャンル該当作品数 >= GENRE_MIN_WORKS のもの
     */
    public function genres(array $params): void
    {
        header('Content-Type: application/xml; charset=UTF-8');
        $actresses = Actress::allForSitemap();
        $urls = [];
        foreach ($actresses as $actress) {
            $actressObj = Actress::findBySlug($actress['slug']);
            if (!$actressObj || (int)$actressObj['work_count'] <= ACTRESS_WORK_THRESHOLD) {
                continue;
            }
            $genreSlugs = Genre::allSlugsForActress($actressObj['id']);
            foreach ($genreSlugs as $genreSlug) {
                $genre = Genre::findBySlug($genreSlug);
                if (!$genre) continue;
                $cnt = Work::countByActressAndGenre($actressObj['id'], $genre['id']);
                if ($cnt < GENRE_MIN_WORKS) continue;
                $urls[] = [
                    'loc' => fullUrl($actress['slug'] . '/' . $genreSlug . '/'),
                    'changefreq' => 'weekly',
                    'priority' => '0.5',
                ];
            }
        }
        render('sitemap', ['noLayout' => true, 'urls' => $urls]);
    }
}
