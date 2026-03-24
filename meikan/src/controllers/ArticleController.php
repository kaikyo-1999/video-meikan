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
            if ($trimmed === ':::cast') {
                $closeList();
                $flushBlockquote();
                $inBox = true;
                $boxType = 'cast';
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

            // --- 装飾ボックス / 吹き出し / キャスト終了 ---
            if ($trimmed === ':::' && $inBox) {
                if ($boxType === 'cast') {
                    $out = '<div class="cast-list">';
                    foreach ($boxLines as $bl) {
                        $bl = trim($bl);
                        if ($bl === '') continue;
                        $parts = array_map('trim', explode('|', $bl));
                        $name = h($parts[0] ?? '');
                        $img = h($parts[1] ?? '');
                        $size = h($parts[2] ?? '');
                        $height = h($parts[3] ?? '');
                        $age = h($parts[4] ?? '');
                        $out .= '<div class="cast-card">';
                        if ($img) {
                            $out .= '<div class="cast-card__image"><img src="' . $img . '" alt="' . $name . '" loading="lazy"></div>';
                        }
                        $out .= '<div class="cast-card__name">' . $name . '</div>';
                        $out .= '<div class="cast-card__meta">';
                        if ($size) $out .= '<span>体型：' . $size . '</span>';
                        if ($height) $out .= '<span>身長：' . $height . '</span>';
                        if ($age) $out .= '<span>年齢：' . $age . '</span>';
                        $out .= '</div>';
                        $out .= '</div>';
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
                $html .= "<h2>{$text}</h2>\n";
                continue;
            }

            // H3
            if (str_starts_with($trimmed, '### ')) {
                $closeList();
                $text = h(substr($trimmed, 4));
                $html .= "<h3>{$text}</h3>\n";
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

            // 段落
            $closeList();
            $text = self::inlineFormat($trimmed);
            $html .= "<p>{$text}</p>\n";
        }

        $closeList();
        $flushBlockquote();
        if ($inTable) $html .= "</tbody></table></div>\n";

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

        $affiliateUrl = self::buildAffiliateUrl('https://www.dmm.co.jp/digital/videoa/-/detail/=/cid=' . $sourceId . '/');

        $fallbackThumb = 'https://pics.dmm.co.jp/digital/video/' . $sourceId . '/' . $sourceId . 'pl.jpg';

        if (!$work) {
            $html = '<div class="embed-card">';
            $html .= '<a href="' . h($affiliateUrl) . '" target="_blank" rel="nofollow noopener" class="embed-card__inner">';
            $html .= '<div class="embed-card__image"><img src="' . h($fallbackThumb) . '" alt="' . h($linkText ?: $sourceId) . '" loading="lazy"></div>';
            $html .= '<div class="embed-card__info">';
            $html .= '<p class="embed-card__title">' . h($linkText ?: $sourceId) . '</p>';
            $html .= '<span class="embed-card__cta">FANZAで見る →</span>';
            $html .= '</div>';
            $html .= '</a></div>' . "\n";
            return $html;
        }

        $thumb = h($work['thumbnail_url'] ?? '') ?: h($fallbackThumb);
        $title = h($work['title'] ?? '');
        $date = h($work['release_date'] ?? '');
        $label = h($work['label'] ?? '');
        $url = !empty($work['affiliate_url']) ? $work['affiliate_url'] : $affiliateUrl;

        $html = '<div class="embed-card">';
        $html .= '<a href="' . h($url) . '" target="_blank" rel="nofollow noopener" class="embed-card__inner">';
        $html .= '<div class="embed-card__image"><img src="' . $thumb . '" alt="' . $title . '" loading="lazy"></div>';
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

    private static function buildAffiliateUrl(string $directUrl): string
    {
        $affiliateId = getenv('FANZA_DISPLAY_AFFILIATE_ID') ?: getenv('FANZA_AFFILIATE_ID');
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
