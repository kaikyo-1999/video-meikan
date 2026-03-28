<?php

class ArticleController
{
    private const ARTICLES_DIR = ROOT_DIR . '/content/articles';
    private static ?string $currentAffiliateId = null;

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

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article['title'],
            'datePublished' => $article['published_at'],
            'dateModified' => $article['updated_at'] ?: $article['published_at'],
            'author' => [
                '@type' => 'Person',
                'name' => 'av博士',
                'url' => fullUrl('author/'),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => SITE_NAME,
            ],
            'description' => $article['description'],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => fullUrl('article/' . $slug . '/'),
            ],
        ];

        render('articles/show', [
            'pageTitle' => $article['title'] . ' | ' . SITE_NAME,
            'metaDescription' => $article['description'],
            'noindex' => $article['noindex'],
            'ogType' => 'article',
            'breadcrumbs' => [
                ['label' => 'TOP', 'url' => ''],
                ['label' => '記事一覧', 'url' => 'article/'],
                ['label' => $article['title'], 'url' => ''],
            ],
            'jsonLd' => $jsonLd,
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
            'noindex' => ($meta['noindex'] ?? '') === 'true',
        ];

        if ($includeBody) {
            $affiliateId = $meta['affiliate_id'] ?? null;
            $result = self::markdownToHtml($body, $affiliateId);
            $article['body_html'] = $result['html'];
            $article['toc'] = $result['toc'];
        }

        return $article;
    }

    private static function markdownToHtml(string $md, ?string $affiliateIdOverride = null): array
    {
        self::$currentAffiliateId = $affiliateIdOverride;
        $md = trim($md);
        $lines = explode("\n", $md);
        $html = '';
        $toc = [];
        $inList = false;
        $inOl = false;
        $inTable = false;
        $inBox = false;
        $boxType = '';
        $boxTitle = '';
        $boxLines = [];
        $inBlockquote = false;
        $blockquoteLines = [];

        $closeList = function () use (&$html, &$inList, &$inOl) {
            if ($inList) { $html .= "</ul>\n"; $inList = false; }
            if ($inOl) { $html .= "</ol>\n"; $inOl = false; }
        };

        $flushBlockquote = function () use (&$html, &$inBlockquote, &$blockquoteLines) {
            if ($inBlockquote) {
                $content = implode("<br>\n", array_map(function ($l) {
                    return self::inlineFormat($l);
                }, $blockquoteLines));
                $html .= "<blockquote>{$content}</blockquote>\n";
                $inBlockquote = false;
                $blockquoteLines = [];
            }
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // --- インライン目次 ---
            if ($trimmed === ':::toc' && !$inBox) {
                $closeList();
                $flushBlockquote();
                $html .= "%%TOC%%\n";
                continue;
            }

            // --- 吹き出し開始 ---
            if ($trimmed === ':::say') {
                $closeList();
                $flushBlockquote();
                $inBox = true;
                $boxType = 'say';
                $boxTitle = '';
                $boxLines = [];
                continue;
            }

            // --- キャストカード開始 ---
            if (preg_match('/^:::cast(?:\[(.+?)\])?$/', $trimmed, $castMatch)) {
                $closeList();
                $flushBlockquote();
                $inBox = true;
                $boxType = 'cast';
                $boxTitle = $castMatch[1] ?? '';
                $boxLines = [];
                continue;
            }

            // --- サンプル画像ギャラリー開始 ---
            if ($trimmed === ':::samples') {
                $closeList();
                $flushBlockquote();
                $inBox = true;
                $boxType = 'samples';
                $boxTitle = '';
                $boxLines = [];
                continue;
            }

            // --- 装飾ボックス開始 ---
            if (preg_match('/^:::(box|memo|alert)(?:\[(.+?)\])?$/', $trimmed, $boxMatch)) {
                $closeList();
                $flushBlockquote();
                $inBox = true;
                $boxType = $boxMatch[1];
                $boxTitle = $boxMatch[2] ?? '';
                $boxLines = [];
                continue;
            }

            // --- FAQ アコーディオン開始 ---
            if (preg_match('/^:::faq\[(.+?)\]$/', $trimmed, $faqMatch)) {
                $closeList();
                $flushBlockquote();
                $inBox = true;
                $boxType = 'faq';
                $boxTitle = $faqMatch[1];
                $boxLines = [];
                continue;
            }

            // --- チャット会話開始 ---
            if ($trimmed === ':::chat') {
                $closeList();
                $flushBlockquote();
                $inBox = true;
                $boxType = 'chat';
                $boxTitle = '';
                $boxLines = [];
                continue;
            }

            // --- 装飾ボックス / 吹き出し / キャスト終了 ---
            if ($trimmed === ':::' && $inBox) {
                if ($boxType === 'cast') {
                    $tableTitle = $boxTitle ?: 'AV女優';
                    $out = '<div class="article-table-wrap"><table class="cast-table">';
                    $out .= '<thead><tr>';
                    $out .= '<th>' . h($tableTitle) . '</th>';
                    $out .= '<th>体型</th>';
                    $out .= '</tr></thead><tbody>';

                    foreach ($boxLines as $bl) {
                        $bl = trim($bl);
                        if ($bl === '') continue;
                        $parts = array_map('trim', explode('|', $bl));
                        $name = h($parts[0] ?? '');
                        $img = h($parts[2] ?? '');
                        $size = h($parts[3] ?? '');
                        $height = h($parts[4] ?? '');
                        $age = h($parts[5] ?? '');

                        $out .= '<tr>';
                        $out .= '<td class="cast-table__actress">';
                        if ($img) {
                            $out .= '<img src="' . $img . '" alt="' . $name . '" loading="lazy">';
                        }
                        $out .= '<span class="cast-table__name">' . $name . '</span>';
                        $out .= '</td>';
                        $out .= '<td class="cast-table__body">';
                        if ($size) $out .= '<span>体型：' . $size . '</span>';
                        if ($height) $out .= '<span>身長：' . $height . '</span>';
                        if ($age) $out .= '<span>年齢：' . $age . '</span>';
                        $out .= '</td>';
                        $out .= '</tr>';
                    }

                    $out .= '</tbody></table></div>' . "\n";
                    $html .= $out;
                    $inBox = false;
                    continue;
                }
                if ($boxType === 'samples') {
                    $out = '<div class="article-samples">';
                    foreach ($boxLines as $bl) {
                        $bl = trim($bl);
                        if ($bl === '' || !str_starts_with($bl, 'http')) continue;
                        $out .= '<img src="' . h($bl) . '" alt="" loading="lazy">';
                    }
                    $out .= '</div>' . "\n";
                    $html .= $out;
                    $inBox = false;
                    continue;
                }
                if ($boxType === 'say') {
                    $out = '<div class="article-say">';
                    $out .= '<div class="article-say__avatar"><img src="' . h(url('public/images/author-avatar.png')) . '" alt="av女優博士" loading="lazy"></div>';
                    $out .= '<div class="article-say__bubble">';
                    foreach ($boxLines as $bl) {
                        $bl = trim($bl);
                        if ($bl === '') continue;
                        $out .= '<p>' . self::inlineFormat($bl) . '</p>';
                    }
                    $out .= '</div></div>' . "\n";
                    $html .= $out;
                    $inBox = false;
                    continue;
                }
                if ($boxType === 'faq') {
                    $question = h($boxTitle);
                    $out = '<details class="article-faq">';
                    $out .= '<summary class="article-faq__question"><span class="article-faq__q">Q</span>' . $question . '</summary>';
                    $out .= '<div class="article-faq__answer">';
                    foreach ($boxLines as $bl) {
                        $bl = trim($bl);
                        if ($bl === '') continue;
                        $out .= '<p>' . self::inlineFormat($bl) . '</p>';
                    }
                    $out .= '</div></details>' . "\n";
                    $html .= $out;
                    $inBox = false;
                    continue;
                }
                if ($boxType === 'chat') {
                    $out = '<div class="article-chat">';
                    $speakers = [];
                    foreach ($boxLines as $bl) {
                        $bl = trim($bl);
                        if ($bl === '') continue;
                        if (!preg_match('/^(.+?):\s*(.+)$/', $bl, $chatMatch)) continue;
                        $speaker = trim($chatMatch[1]);
                        $message = self::inlineFormat(trim($chatMatch[2]));
                        if (!in_array($speaker, $speakers, true)) {
                            $speakers[] = $speaker;
                        }
                        $side = array_search($speaker, $speakers, true) === 0 ? 'left' : 'right';
                        $out .= '<div class="article-chat__row article-chat__row--' . $side . '">';
                        $out .= '<span class="article-chat__label">' . h($speaker) . '</span>';
                        $out .= '<div class="article-chat__bubble">' . $message . '</div>';
                        $out .= '</div>';
                    }
                    $out .= '</div>' . "\n";
                    $html .= $out;
                    $inBox = false;
                    continue;
                }
                $modifier = $boxType === 'box' ? '' : " article-box--{$boxType}";
                $out = '<div class="article-box' . $modifier . '">';
                if ($boxTitle !== '') {
                    $out .= '<div class="article-box__title">' . h($boxTitle) . '</div>';
                }
                $out .= '<div class="article-box__body">';
                $boxInList = false;
                foreach ($boxLines as $bl) {
                    $bl = trim($bl);
                    if ($bl === '') {
                        if ($boxInList) { $out .= '</ul>'; $boxInList = false; }
                        continue;
                    }
                    if (str_starts_with($bl, '- ')) {
                        if (!$boxInList) { $out .= '<ul>'; $boxInList = true; }
                        $out .= '<li>' . self::inlineFormat(substr($bl, 2)) . '</li>';
                    } else {
                        if ($boxInList) { $out .= '</ul>'; $boxInList = false; }
                        $out .= '<p>' . self::inlineFormat($bl) . '</p>';
                    }
                }
                if ($boxInList) $out .= '</ul>';
                $out .= '</div></div>' . "\n";
                $html .= $out;
                $inBox = false;
                continue;
            }
            if ($inBox) {
                $boxLines[] = $trimmed;
                continue;
            }

            // --- 引用ブロック ---
            if (str_starts_with($trimmed, '> ') || $trimmed === '>') {
                $closeList();
                $inBlockquote = true;
                $blockquoteLines[] = $trimmed === '>' ? '' : substr($trimmed, 2);
                continue;
            }
            if ($inBlockquote && $trimmed !== '') {
                $flushBlockquote();
            } elseif ($inBlockquote && $trimmed === '') {
                $flushBlockquote();
                continue;
            }

            if ($trimmed === '') {
                $closeList();
                if ($inTable) {
                    $html .= "</tbody></table></div>\n";
                    $inTable = false;
                }
                continue;
            }

            // --- 区切り線 ---
            if (preg_match('/^-{3,}$/', $trimmed) && !$inTable) {
                $closeList();
                $html .= "<hr>\n";
                continue;
            }

            // --- テーブル ---
            if (str_starts_with($trimmed, '|') && str_ends_with($trimmed, '|')) {
                $closeList();
                if (preg_match('/^\|[\s\-:|]+\|$/', $trimmed)) {
                    continue;
                }
                $cells = array_map('trim', explode('|', trim($trimmed, '|')));
                if (!$inTable) {
                    $html .= '<div class="article-table-wrap"><table>';
                    $html .= '<thead><tr>';
                    foreach ($cells as $cell) {
                        $html .= '<th>' . self::inlineFormat($cell) . '</th>';
                    }
                    $html .= '</tr></thead><tbody>';
                    $inTable = true;
                } else {
                    $html .= '<tr>';
                    foreach ($cells as $cell) {
                        $html .= '<td>' . self::inlineFormat($cell) . '</td>';
                    }
                    $html .= '</tr>';
                }
                continue;
            }
            if ($inTable) {
                $html .= "</tbody></table></div>\n";
                $inTable = false;
            }

            // H2
            if (str_starts_with($trimmed, '## ')) {
                $closeList();
                $text = h(substr($trimmed, 3));
                $id = 'h-' . count($toc);
                $toc[] = ['level' => 2, 'text' => $text, 'id' => $id];
                $html .= "<h2 id=\"{$id}\">{$text}</h2>\n";
                continue;
            }

            // H3
            if (str_starts_with($trimmed, '### ')) {
                $closeList();
                $text = h(substr($trimmed, 4));
                $id = 'h-' . count($toc);
                $toc[] = ['level' => 3, 'text' => $text, 'id' => $id];
                $html .= "<h3 id=\"{$id}\">{$text}</h3>\n";
                continue;
            }

            // 番号付きリスト
            if (preg_match('/^(\d+)\.\s+(.+)$/', $trimmed, $olMatch)) {
                if ($inList) { $html .= "</ul>\n"; $inList = false; }
                if (!$inOl) {
                    $html .= "<ol>\n";
                    $inOl = true;
                }
                $text = self::inlineFormat($olMatch[2]);
                $html .= "<li>{$text}</li>\n";
                continue;
            }

            // リスト
            if (str_starts_with($trimmed, '- ')) {
                if ($inOl) { $html .= "</ol>\n"; $inOl = false; }
                if (!$inList) {
                    $html .= "<ul>\n";
                    $inList = true;
                }
                $text = self::inlineFormat(substr($trimmed, 2));
                $html .= "<li>{$text}</li>\n";
                continue;
            }

            // ブロックレベル画像 ![alt](url)
            if (preg_match('/^!\[(.+?)\]\((.+?)\)$/', $trimmed, $imgMatch)) {
                $closeList();
                $html .= '<figure class="article-figure"><img src="' . h($imgMatch[2]) . '" alt="' . h($imgMatch[1]) . '" loading="lazy"></figure>' . "\n";
                continue;
            }

            // 女優カード埋め込み @actress[slug]
            if (preg_match('/^@actress\[([a-z0-9-]+)\]$/', $trimmed, $actressMatch)) {
                $closeList();
                $html .= self::renderActressCard($actressMatch[1]);
                continue;
            }

            // FANZA商品リンク埋め込み
            if (preg_match('/\[(.+?)\]\(https?:\/\/(www\.)?dmm\.co\.jp\/digital\/videoa\/-\/detail\/=\/cid=([a-z0-9_]+)/', $trimmed, $cidMatch)) {
                $closeList();
                $html .= self::renderWorkEmbed($cidMatch[3], $cidMatch[1]);
                continue;
            }
            // FANZA検索リンク埋め込み
            if (preg_match('/\[(.+?)\]\((https?:\/\/(www\.)?dmm\.co\.jp\/digital\/videoa\/-\/list\/search\/.+?)\)/', $trimmed, $searchMatch)) {
                $closeList();
                $html .= self::renderSearchEmbed($searchMatch[1], $searchMatch[2]);
                continue;
            }

            // ※ 注釈段落
            if (str_starts_with($trimmed, '※')) {
                $closeList();
                $text = self::inlineFormat($trimmed);
                $html .= "<p class=\"article-note\">{$text}</p>\n";
                continue;
            }

            // 段落
            $closeList();
            $text = self::inlineFormat($trimmed);
            $html .= "<p>{$text}</p>\n";
        }

        $closeList();
        $flushBlockquote();
        if ($inTable) $html .= "</tbody></table></div>\n";

        // インライン目次の展開
        if (str_contains($html, '%%TOC%%')) {
            if (count($toc) >= 3) {
                $html = str_replace("%%TOC%%\n", self::renderTocHtml($toc), $html);
                $toc = [];
            } else {
                $html = str_replace("%%TOC%%\n", '', $html);
            }
        }

        return ['html' => $html, 'toc' => $toc];
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

        $affiliateUrl = self::buildAffiliateUrl('https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=' . $sourceId . '/');

        $fallbackThumb = 'https://pics.dmm.co.jp/digital/video/' . $sourceId . '/' . $sourceId . 'pl.jpg';

        if (!$work) {
            $html = '<div class="embed-card embed-card--work">';
            $html .= '<a href="' . h($affiliateUrl) . '" target="_blank" rel="nofollow noopener" class="embed-card__inner embed-card__inner--work">';
            $html .= '<div class="embed-card__image embed-card__image--work"><img src="' . h($fallbackThumb) . '" alt="' . h($linkText ?: $sourceId) . '" loading="lazy"></div>';
            $html .= '<p class="embed-card__title embed-card__title--work">' . h($linkText ?: $sourceId) . '</p>';
            $html .= '</a></div>' . "\n";
            return $html;
        }

        $thumb = h($work['thumbnail_url'] ?? '') ?: h($fallbackThumb);
        $title = h($work['title'] ?? '');
        $url = !empty($work['affiliate_url']) ? $work['affiliate_url'] : $affiliateUrl;

        $html = '<div class="embed-card embed-card--work">';
        $html .= '<a href="' . h($url) . '" target="_blank" rel="nofollow noopener" class="embed-card__inner embed-card__inner--work">';
        $html .= '<div class="embed-card__image embed-card__image--work"><img src="' . $thumb . '" alt="' . $title . '" loading="lazy"></div>';
        $html .= '<p class="embed-card__title embed-card__title--work">' . $title . '</p>';
        $html .= '</a></div>' . "\n";

        return $html;
    }

    private static function renderSearchEmbed(string $text, string $url): string
    {
        $affiliateUrl = self::buildAffiliateUrl($url);
        $html = '<div class="embed-card embed-card--search">';
        $html .= '<a href="' . h($affiliateUrl) . '" target="_blank" rel="nofollow noopener" class="embed-card__inner embed-card__inner--search">';
        $html .= '<div class="embed-card__info">';
        $html .= '<p class="embed-card__title">' . h($text) . '</p>';
        $html .= '<span class="embed-card__cta">FANZAで検索する →</span>';
        $html .= '</div>';
        $html .= '</a></div>' . "\n";

        return $html;
    }

    private static function renderActressCard(string $slug): string
    {
        $actress = null;
        if (class_exists('Actress')) {
            try {
                $actress = Actress::findBySlug($slug);
            } catch (\Throwable $e) {
                $actress = null;
            }
        }

        $pageUrl = url($slug . '/');
        if (!$actress) {
            return '<div class="embed-card embed-card--search"><a href="' . h($pageUrl) . '" class="embed-card__inner embed-card__inner--search"><div class="embed-card__info"><p class="embed-card__title">' . h($slug) . ' の作品一覧</p><span class="embed-card__cta">作品一覧を見る →</span></div></a></div>' . "\n";
        }

        $name = h($actress['name'] ?? '');
        $thumb = h($actress['thumbnail_url'] ?? '');
        $count = (int)($actress['work_count'] ?? 0);

        $html = '<div class="embed-card">';
        $html .= '<a href="' . h($pageUrl) . '" class="embed-card__inner">';
        if ($thumb) {
            $html .= '<div class="embed-card__image embed-card__image--portrait"><img src="' . $thumb . '" alt="' . $name . '" loading="lazy"></div>';
        }
        $html .= '<div class="embed-card__info">';
        $html .= '<p class="embed-card__title">' . $name . '</p>';
        if ($count > 0) {
            $html .= '<div class="embed-card__meta"><span>作品数：' . $count . '本</span></div>';
        }
        $html .= '<span class="embed-card__cta">出演作品一覧を見る →</span>';
        $html .= '</div>';
        $html .= '</a></div>' . "\n";

        return $html;
    }

    private static function renderTocHtml(array $toc): string
    {
        $svgIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
            . '<line x1="8" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
            . '<line x1="8" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
            . '<line x1="8" y1="18" x2="21" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
            . '<circle cx="3" cy="6" r="1.5" fill="currentColor"/>'
            . '<circle cx="3" cy="12" r="1.5" fill="currentColor"/>'
            . '<circle cx="3" cy="18" r="1.5" fill="currentColor"/>'
            . '</svg>';
        $out = '<nav class="article-toc" aria-label="目次">'
            . '<details class="article-toc__details">'
            . '<summary class="article-toc__summary">'
            . '<span class="article-toc__icon">' . $svgIcon . '</span>'
            . '<span class="article-toc__title-text">目次</span>'
            . '<span class="article-toc__toggle-label"></span>'
            . '</summary>'
            . '<ol class="article-toc__list">';
        foreach ($toc as $item) {
            $out .= '<li class="article-toc__item article-toc__item--h' . (int)$item['level'] . '">'
                . '<a href="#' . h($item['id']) . '">' . $item['text'] . '</a>'
                . '</li>';
        }
        $out .= '</ol></details></nav>' . "\n";
        return $out;
    }

    private static function buildAffiliateUrl(string $directUrl): string
    {
        $affiliateId = self::$currentAffiliateId
            ?: getenv('FANZA_DISPLAY_AFFILIATE_ID')
            ?: getenv('FANZA_AFFILIATE_ID');
        if (!$affiliateId) {
            return $directUrl;
        }
        return 'https://al.dmm.co.jp/?lurl=' . urlencode($directUrl) . '&af_id=' . $affiliateId . '&ch=toolbar&ch_id=text';
    }

    private static function inlineFormat(string $text): string
    {
        // !img[url] → インライン画像を先に抽出して保護
        $links = [];
        $text = preg_replace_callback('/!img\[(.+?)\]/', function ($m) use (&$links) {
            $key = '%%LINK' . count($links) . '%%';
            $links[$key] = '<img src="' . h($m[1]) . '" alt="" class="article-inline-img" loading="lazy">';
            return $key;
        }, $text);

        // [btn text](url) → ボタンリンク（先に処理）
        $text = preg_replace_callback('/\[btn ([^\]]+)\]\(([^)]+)\)/', function ($m) use (&$links) {
            $key = '%%LINK' . count($links) . '%%';
            $links[$key] = '<a href="' . h($m[2]) . '" class="article-btn" target="_blank" rel="nofollow noopener">' . h($m[1]) . '</a>';
            return $key;
        }, $text);

        // [text](url) → リンクを先に抽出して保護
        $text = preg_replace_callback('/\[(.+?)\]\((.+?)\)/', function ($m) use (&$links) {
            $key = '%%LINK' . count($links) . '%%';
            $links[$key] = '<a href="' . h($m[2]) . '" target="_blank" rel="nofollow noopener">' . h($m[1]) . '</a>';
            return $key;
        }, $text);

        $text = h($text);
        // **bold**
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        // ==マーカー== / ==[red]マーカー==
        $text = preg_replace('/==\[red\](.+?)==/', '<span class="article-marker article-marker--red">$1</span>', $text);
        $text = preg_replace('/==(.+?)==/', '<span class="article-marker">$1</span>', $text);
        // リンクを復元
        $text = str_replace(array_keys($links), array_values($links), $text);
        return $text;
    }
}
