<?php

class ArticleController
{
    private const ARTICLES_DIR = ROOT_DIR . '/content/articles';

    public function index(array $params): void
    {
        $articles = self::allArticles();

        render('articles/index', [
            'pageTitle' => '記事一覧 | ' . SITE_NAME,
            'metaDescription' => 'AV女優に関するまとめ記事・コラムの一覧です。',
            'breadcrumbs' => [
                ['label' => 'TOP', 'url' => ''],
                ['label' => '記事一覧', 'url' => ''],
            ],
            'articles' => $articles,
        ]);
    }

    public function show(array $params): void
    {
        $slug = $params['article_slug'];
        $file = self::ARTICLES_DIR . '/' . $slug . '.md';

        if (!file_exists($file)) {
            http_response_code(404);
            render('404', ['pageTitle' => 'ページが見つかりません | ' . SITE_NAME]);
            return;
        }

        $article = self::parseArticle($file);
        $allArticles = self::allArticles();
        $related = array_filter($allArticles, fn($a) => $a['slug'] !== $slug);
        $related = array_slice($related, 0, 3);

        render('articles/show', [
            'pageTitle' => $article['title'] . ' | ' . SITE_NAME,
            'metaDescription' => $article['description'],
            'breadcrumbs' => [
                ['label' => 'TOP', 'url' => ''],
                ['label' => '記事一覧', 'url' => 'articles/'],
                ['label' => $article['title'], 'url' => ''],
            ],
            'article' => $article,
            'related' => $related,
        ]);
    }

    public static function allArticles(): array
    {
        $cacheKey = 'articles_all';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $files = glob(self::ARTICLES_DIR . '/*.md');
        $articles = [];

        foreach ($files as $file) {
            $articles[] = self::parseArticle($file, false);
        }

        usort($articles, fn($a, $b) => strtotime($b['published_at']) - strtotime($a['published_at']));

        Cache::set($cacheKey, $articles);
        return $articles;
    }

    private static function parseArticle(string $file, bool $includeBody = true): array
    {
        $content = file_get_contents($file);
        $meta = [];
        $body = $content;

        // フロントマター解析
        if (preg_match('/^---\s*\n(.+?)\n---\s*\n(.*)/s', $content, $m)) {
            $body = $m[2];
            foreach (explode("\n", $m[1]) as $line) {
                if (str_contains($line, ':')) {
                    $key = trim(explode(':', $line, 2)[0]);
                    $val = trim(explode(':', $line, 2)[1]);
                    $meta[$key] = $val;
                }
            }
        }

        $article = [
            'title' => $meta['title'] ?? basename($file, '.md'),
            'slug' => $meta['slug'] ?? basename($file, '.md'),
            'description' => $meta['description'] ?? '',
            'category' => $meta['category'] ?? '',
            'published_at' => $meta['published_at'] ?? '',
            'updated_at' => $meta['updated_at'] ?? '',
        ];

        if ($includeBody) {
            $article['body_html'] = self::markdownToHtml($body);
        }

        return $article;
    }

    private static function markdownToHtml(string $md): string
    {
        $md = trim($md);
        $lines = explode("\n", $md);
        $html = '';
        $inList = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if ($inList) {
                    $html .= "</ul>\n";
                    $inList = false;
                }
                continue;
            }

            // H2
            if (str_starts_with($trimmed, '## ')) {
                if ($inList) { $html .= "</ul>\n"; $inList = false; }
                $text = h(substr($trimmed, 3));
                $html .= "<h2>{$text}</h2>\n";
                continue;
            }

            // H3
            if (str_starts_with($trimmed, '### ')) {
                if ($inList) { $html .= "</ul>\n"; $inList = false; }
                $text = h(substr($trimmed, 4));
                $html .= "<h3>{$text}</h3>\n";
                continue;
            }

            // リスト
            if (str_starts_with($trimmed, '- ')) {
                if (!$inList) {
                    $html .= "<ul>\n";
                    $inList = true;
                }
                $text = self::inlineFormat(substr($trimmed, 2));
                $html .= "<li>{$text}</li>\n";
                continue;
            }

            // FANZA商品リンク埋め込み（**おすすめ作品**：[text](url) パターン）
            if (preg_match('/\[(.+?)\]\(https?:\/\/(www\.)?dmm\.co\.jp\/digital\/videoa\/-\/detail\/=\/cid=([a-z0-9_]+)/', $trimmed, $cidMatch)) {
                if ($inList) { $html .= "</ul>\n"; $inList = false; }
                $html .= self::renderWorkEmbed($cidMatch[3], $cidMatch[1]);
                continue;
            }
            // FANZA検索リンク埋め込み
            if (preg_match('/\[(.+?)\]\((https?:\/\/(www\.)?dmm\.co\.jp\/digital\/videoa\/-\/list\/search\/.+?)\)/', $trimmed, $searchMatch)) {
                if ($inList) { $html .= "</ul>\n"; $inList = false; }
                $html .= self::renderSearchEmbed($searchMatch[1], $searchMatch[2]);
                continue;
            }

            // 段落
            if ($inList) { $html .= "</ul>\n"; $inList = false; }
            $text = self::inlineFormat($trimmed);
            $html .= "<p>{$text}</p>\n";
        }

        if ($inList) $html .= "</ul>\n";

        return $html;
    }

    private static function renderWorkEmbed(string $sourceId, string $linkText = ''): string
    {
        $work = null;
        if (class_exists('Database')) {
            try {
                $db = Database::getInstance();
                $stmt = $db->prepare('SELECT * FROM works WHERE source = ? AND source_id = ? LIMIT 1');
                $stmt->execute(['fanza', $sourceId]);
                $work = $stmt->fetch();
            } catch (\Throwable $e) {
                $work = null;
            }
        }

        $url = 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=' . h($sourceId) . '/';
        $thumbUrl = 'https://pics.dmm.co.jp/digital/video/' . $sourceId . '/' . $sourceId . 'pl.jpg';

        if (!$work) {
            $html = '<div class="embed-card embed-card--search">';
            $html .= '<a href="' . $url . '" target="_blank" rel="nofollow noopener" class="embed-card__inner embed-card__inner--search">';
            $html .= '<div class="embed-card__info">';
            $html .= '<p class="embed-card__title">' . h($linkText ?: $sourceId) . '</p>';
            $html .= '<span class="embed-card__cta">FANZAで見る →</span>';
            $html .= '</div>';
            $html .= '</a></div>' . "\n";
            return $html;
        }

        $thumb = h($work['thumbnail_url'] ?? '');
        $title = h($work['title'] ?? '');
        $date = h($work['release_date'] ?? '');
        $label = h($work['label'] ?? '');
        $url = 'https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=' . h($sourceId) . '/';

        $html = '<div class="embed-card">';
        $html .= '<a href="' . $url . '" target="_blank" rel="nofollow noopener" class="embed-card__inner">';
        if ($thumb) {
            $html .= '<div class="embed-card__image"><img src="' . $thumb . '" alt="' . $title . '" loading="lazy"></div>';
        }
        $html .= '<div class="embed-card__info">';
        $html .= '<p class="embed-card__title">' . $title . '</p>';
        $html .= '<div class="embed-card__meta">';
        if ($date) $html .= '<span>発売日：' . $date . '</span>';
        if ($label) $html .= '<span>レーベル：' . $label . '</span>';
        $html .= '</div>';
        $html .= '<span class="embed-card__cta">FANZAで見る →</span>';
        $html .= '</div>';
        $html .= '</a></div>' . "\n";

        return $html;
    }

    private static function renderSearchEmbed(string $text, string $url): string
    {
        $html = '<div class="embed-card embed-card--search">';
        $html .= '<a href="' . h($url) . '" target="_blank" rel="nofollow noopener" class="embed-card__inner embed-card__inner--search">';
        $html .= '<div class="embed-card__info">';
        $html .= '<p class="embed-card__title">' . h($text) . '</p>';
        $html .= '<span class="embed-card__cta">FANZAで検索する →</span>';
        $html .= '</div>';
        $html .= '</a></div>' . "\n";

        return $html;
    }

    private static function inlineFormat(string $text): string
    {
        // [text](url) → リンクを先に抽出して保護
        $links = [];
        $text = preg_replace_callback('/\[(.+?)\]\((.+?)\)/', function ($m) use (&$links) {
            $key = '%%LINK' . count($links) . '%%';
            $links[$key] = '<a href="' . h($m[2]) . '" target="_blank" rel="nofollow noopener">' . h($m[1]) . '</a>';
            return $key;
        }, $text);

        $text = h($text);
        // **bold**
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        // リンクを復元
        $text = str_replace(array_keys($links), array_values($links), $text);
        return $text;
    }
}
