<?php

class SitemapController
{
    /**
     * サイトマップindex（sitemapindex形式・/sitemap.xml）
     * 子サイトマップ4本を束ねる。
     *
     * lastmod は各子サイトマップ内 URL の最大更新日時から算出する。
     * 生成時刻ではなく実コンテンツの更新時刻を使うのは Google ガイダンス遵守:
     * https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap
     */
    public function index(array $params): void
    {
        header('Content-Type: application/xml; charset=UTF-8');

        $articlesLastmod = self::articlesMaxLastmod();
        $actressesLastmod = self::actressesMaxLastmod();
        $worksLastmod = self::worksMaxLastmod();

        // core: TOP/meikan が女優・作品データを動的表示するため、両者の MAX を使う
        $coreLastmod = self::pickMax($actressesLastmod, $worksLastmod);
        // genres: 作品データが変わるとジャンルページの内容も変わる
        $genresLastmod = $worksLastmod;

        $sitemaps = [
            ['loc' => fullUrl('sitemap-core.xml'),      'lastmod' => $coreLastmod],
            ['loc' => fullUrl('sitemap-articles.xml'),  'lastmod' => $articlesLastmod],
            ['loc' => fullUrl('sitemap-actresses.xml'), 'lastmod' => $actressesLastmod],
            ['loc' => fullUrl('sitemap-genres.xml'),    'lastmod' => $genresLastmod],
        ];
        render('sitemap-index', ['noLayout' => true, 'sitemaps' => $sitemaps]);
    }

    private static function articlesMaxLastmod(): ?string
    {
        $cacheKey = 'sitemap_articles_lastmod';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached !== '' ? $cached : null;

        $articles = ArticleController::allArticles();
        $max = null;
        foreach ($articles as $article) {
            if (!empty($article['noindex'])) continue;
            $date = !empty($article['updated_at']) ? $article['updated_at']
                  : (!empty($article['published_at']) ? $article['published_at'] : null);
            if (!$date) continue;
            if ($max === null || $date > $max) $max = $date;
        }

        Cache::set($cacheKey, $max ?? '', 3600);
        return $max;
    }

    private static function actressesMaxLastmod(): ?string
    {
        return self::dbMaxDate('sitemap_actresses_lastmod', 'SELECT MAX(updated_at) AS max_dt FROM actresses');
    }

    private static function worksMaxLastmod(): ?string
    {
        return self::dbMaxDate('sitemap_works_lastmod', 'SELECT MAX(updated_at) AS max_dt FROM works');
    }

    private static function dbMaxDate(string $cacheKey, string $sql): ?string
    {
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached !== '' ? $cached : null;

        $db = Database::getInstance();
        $row = $db->query($sql)->fetch();
        $max = ($row && !empty($row['max_dt'])) ? date('Y-m-d', strtotime($row['max_dt'])) : null;

        Cache::set($cacheKey, $max ?? '', 3600);
        return $max;
    }

    private static function pickMax(?string ...$dates): ?string
    {
        $valid = array_filter($dates, fn($d) => $d !== null && $d !== '');
        return empty($valid) ? null : max($valid);
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
            ['loc' => fullUrl('fc2/'), 'changefreq' => 'daily', 'priority' => '0.9'],
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
