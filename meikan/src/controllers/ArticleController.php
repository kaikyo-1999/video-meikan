<?php

class ArticleController
{
    private const ARTICLES_DIR = ROOT_DIR . '/content/articles';
    private static ?string $currentAffiliateId = null;
    private static ?string $lastWorkUrl = null;

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
        $castCustomLabels = null;
        $inBlockquote = false;
        $blockquoteLines = [];
        $imgBuffer = [];

        $closeList = function () use (&$html, &$inList, &$inOl) {
            if ($inList) { $html .= "</ul>\n"; $inList = false; }
            if ($inOl) { $html .= "</ol>\n"; $inOl = false; }
        };

        $flushImgBuffer = function () use (&$html, &$imgBuffer) {
            if (empty($imgBuffer)) return;
            if (count($imgBuffer) >= 3) {
                $html .= '<div class="article-samples">';
                foreach ($imgBuffer as $entry) {
                    $html .= '<img src="' . h($entry['url']) . '" alt="' . h($entry['alt']) . '" loading="lazy">';
                }
                $html .= '</div>' . "\n";
            } else {
                foreach ($imgBuffer as $entry) {
                    if ($entry['alt'] !== '') {
                        $html .= '<figure class="article-figure"><img src="' . h($entry['url']) . '" alt="' . h($entry['alt']) . '" loading="lazy"></figure>' . "\n";
                    } else {
                        $html .= '<p><img src="' . h($entry['url']) . '" alt="" class="article-inline-img" loading="lazy"></p>' . "\n";
                    }
                }
            }
            $imgBuffer = [];
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

        // h2見出し名を事前収集（キャスト表のアンカーリンク有無の判定に使用）
        $h2Names = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if (str_starts_with($t, '## ')) {
                $h2Names[] = trim(substr($t, 3));
            }
        }

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
            if (preg_match('/^:::cast(?:\[(.+?)\])?(?:\{(.+?)\})?$/', $trimmed, $castMatch)) {
                $closeList();
                $flushBlockquote();
                $inBox = true;
                $boxType = 'cast';
                $boxTitle = $castMatch[1] ?? '';
                $castCustomLabels = isset($castMatch[2]) ? array_map('trim', explode(',', $castMatch[2])) : null;
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

            // --- メリデメ比較表開始 ---
            if (preg_match('/^:::proscons(?:\[(.+?)\])?$/', $trimmed, $pcMatch)) {
                $closeList();
                $flushBlockquote();
                $inBox = true;
                $boxType = 'proscons';
                $boxTitle = $pcMatch[1] ?? '';
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
                    $label3 = $castCustomLabels[0] ?? '体型';
                    $label4 = $castCustomLabels[1] ?? '身長';
                    $label5 = $castCustomLabels[2] ?? '年齢';
                    $castCustomLabels = null;

                    // 有効な行だけ抽出
                    $castRows = array_values(array_filter($boxLines, fn($l) => trim($l) !== ''));
                    $totalRows = count($castRows);
                    $collapseThreshold = 4;
                    $hasExtra = $totalRows > $collapseThreshold;

                    $out = '<div class="article-table-wrap cast-table-collapsible' . ($hasExtra ? ' is-collapsed' : '') . '">';
                    $out .= '<table class="cast-table">';
                    $out .= '<thead><tr>';
                    $out .= '<th>' . h($tableTitle) . '</th>';
                    $out .= '<th>' . h($label3) . ' / ' . h($label4) . ' / ' . h($label5) . '</th>';
                    $out .= '</tr></thead><tbody>';

                    foreach ($castRows as $i => $bl) {
                        $parts = array_map('trim', explode('|', $bl));
                        $nameRaw = $parts[0] ?? '';
                        $name = h($nameRaw);
                        $anchorId = 'sec-' . str_replace(' ', '-', $nameRaw);
                        $img = h($parts[2] ?? '');
                        $val3 = h($parts[3] ?? '');
                        $val4 = h($parts[4] ?? '');
                        $val5 = h($parts[5] ?? '');

                        $extraClass = ($i >= $collapseThreshold) ? ' cast-table__row--extra' : '';
                        $out .= '<tr class="cast-table__row' . $extraClass . '">';
                        $out .= '<td class="cast-table__actress">';
                        if ($img) {
                            $out .= '<img src="' . $img . '" alt="' . $name . '" loading="lazy">';
                        }
                        if (in_array($nameRaw, $h2Names, true)) {
                            $out .= '<a href="#' . h($anchorId) . '" class="cast-table__name">' . $name . '</a>';
                        } else {
                            $out .= '<span class="cast-table__name">' . $name . '</span>';
                        }
                        $out .= '</td>';
                        $out .= '<td class="cast-table__body">';
                        if ($val3) $out .= '<span>' . h($label3) . '：' . $val3 . '</span>';
                        if ($val4) $out .= '<span>' . h($label4) . '：' . $val4 . '</span>';
                        if ($val5) $out .= '<span>' . h($label5) . '：' . $val5 . '</span>';
                        $out .= '</td>';
                        $out .= '</tr>';
                    }

                    $out .= '</tbody></table>';
                    if ($hasExtra) {
                        $extraCount = $totalRows - $collapseThreshold;
                        $out .= '<button class="cast-table__toggle" type="button">';
                        $out .= '<span class="cast-table__toggle-more">もっと見る（残り' . $extraCount . '名）▼</span>';
                        $out .= '<span class="cast-table__toggle-less">閉じる ▲</span>';
                        $out .= '</button>';
                    }
                    $out .= '</div>' . "\n";
                    $html .= $out;
                    $inBox = false;
                    continue;
                }
                if ($boxType === 'samples') {
                    $out = '<div class="article-samples">';
                    foreach ($boxLines as $bl) {
                        $bl = trim($bl);
                        if ($bl === '') continue;
                        // !img[url] 形式と生URL両対応
                        if (preg_match('/^!img\[(.+?)\]$/', $bl, $imgM)) {
                            $url = $imgM[1];
                        } elseif (str_starts_with($bl, 'http')) {
                            $url = $bl;
                        } else {
                            continue;
                        }
                        $out .= '<img src="' . h($url) . '" alt="" loading="lazy">';
                    }
                    $out .= '</div>' . "\n";
                    if (self::$lastWorkUrl) {
                        $out .= '<div class="article-samples-cta"><a href="' . h(self::$lastWorkUrl) . '" target="_blank" rel="nofollow noopener" class="article-samples-cta__btn">高画質フル動画をダウンロード →</a></div>' . "\n";
                        self::$lastWorkUrl = null;
                    }
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
                if ($boxType === 'proscons') {
                    $title = $boxTitle ?: '';
                    $pros = [];
                    $cons = [];
                    $currentSide = null;
                    $prosLabel = 'メリット';
                    $consLabel = 'デメリット';
                    foreach ($boxLines as $bl) {
                        $bl = trim($bl);
                        if ($bl === '') continue;
                        if (preg_match('/^pros(?:\[(.+?)\])?:$/', $bl, $plMatch)) {
                            $currentSide = 'pros';
                            if (!empty($plMatch[1])) $prosLabel = $plMatch[1];
                            continue;
                        }
                        if (preg_match('/^cons(?:\[(.+?)\])?:$/', $bl, $clMatch)) {
                            $currentSide = 'cons';
                            if (!empty($clMatch[1])) $consLabel = $clMatch[1];
                            continue;
                        }
                        if ($currentSide === 'pros' && str_starts_with($bl, '- ')) {
                            $pros[] = self::inlineFormat(substr($bl, 2));
                        } elseif ($currentSide === 'cons' && str_starts_with($bl, '- ')) {
                            $cons[] = self::inlineFormat(substr($bl, 2));
                        }
                    }
                    $out = '<div class="article-proscons">';
                    if ($title) {
                        $out .= '<div class="article-proscons__title">' . h($title) . '</div>';
                    }
                    $out .= '<div class="article-proscons__grid">';
                    $out .= '<div class="article-proscons__col article-proscons__col--pros">';
                    $out .= '<div class="article-proscons__label article-proscons__label--pros">' . h($prosLabel) . '</div>';
                    $out .= '<ul>';
                    foreach ($pros as $p) $out .= '<li>' . $p . '</li>';
                    $out .= '</ul></div>';
                    $out .= '<div class="article-proscons__col article-proscons__col--cons">';
                    $out .= '<div class="article-proscons__label article-proscons__label--cons">' . h($consLabel) . '</div>';
                    $out .= '<ul>';
                    foreach ($cons as $c) $out .= '<li>' . $c . '</li>';
                    $out .= '</ul></div>';
                    $out .= '</div></div>' . "\n";
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
                        if (!preg_match('/^((?:[^\[:]|\[.*?\])+):\s*(.+)$/', $bl, $chatMatch)) continue;
                        $speaker = trim($chatMatch[1]);
                        $message = self::inlineFormat(trim($chatMatch[2]));
                        // 名前[avatar_url] 形式のアバターURL抽出
                        $avatarUrl = null;
                        if (preg_match('/^(.+?)\[(.+?)\]$/', $speaker, $avMatch)) {
                            $speaker = trim($avMatch[1]);
                            $avatarUrl = trim($avMatch[2]);
                        }
                        if (!in_array($speaker, $speakers, true)) {
                            $speakers[] = $speaker;
                        }
                        $side = array_search($speaker, $speakers, true) === 0 ? 'left' : 'right';
                        $rowClass = 'article-chat__row article-chat__row--' . $side . ($avatarUrl ? ' article-chat__row--avatar' : '');
                        $out .= '<div class="' . $rowClass . '">';
                        if ($avatarUrl) {
                            $out .= '<img src="' . h($avatarUrl) . '" alt="' . h($speaker) . '" class="article-chat__avatar" loading="lazy">';
                            $out .= '<div class="article-chat__content">';
                        }
                        $out .= '<span class="article-chat__label">' . h($speaker) . '</span>';
                        $out .= '<div class="article-chat__bubble">' . $message . '</div>';
                        if ($avatarUrl) {
                            $out .= '</div>';
                        }
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

            // 連続画像ギャラリー: 空行をまたいでバッファリング、3枚以上で横スクロール化
            if (preg_match('/^!img\[(.+?)\]$/', $trimmed, $imgBufMatch)) {
                $imgBuffer[] = ['url' => $imgBufMatch[1], 'alt' => ''];
                continue;
            }
            if (preg_match('/^!\[(.+?)\]\((.+?)\)$/', $trimmed, $imgBufFigMatch)) {
                $imgBuffer[] = ['url' => $imgBufFigMatch[2], 'alt' => $imgBufFigMatch[1]];
                continue;
            }
            if ($trimmed !== '' && !empty($imgBuffer)) {
                $flushImgBuffer();
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
                $rawText = substr($trimmed, 3);
                $text = h($rawText);
                $id = 'sec-' . str_replace(' ', '-', trim($rawText));
                $toc[] = ['level' => 2, 'text' => $text, 'id' => $id];
                $html .= "<h2 id=\"{$id}\">{$text}</h2>\n";
                continue;
            }

            // H3
            if (str_starts_with($trimmed, '### ')) {
                $closeList();
                $rawText = substr($trimmed, 4);
                $text = h($rawText);
                $id = 'sec-' . str_replace(' ', '-', trim($rawText));
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

            // ブロックレベル画像 ![alt](url) — 連続3枚以上はバッファ経由でカルーセル化（上の処理で対応済み）

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
        $flushImgBuffer();
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

        // CIDの数字プレフィックス有無で画像パスが異なるケースがある
        // (例: 1ebod00944 → ebod00944, ただし 1sdab00330 はそのまま)
        $strippedId = preg_replace('/^\d+/', '', $sourceId);
        $fallbackThumb = 'https://pics.dmm.co.jp/digital/video/' . $sourceId . '/' . $sourceId . 'pl.jpg';
        $altThumb = ($strippedId !== $sourceId)
            ? 'https://pics.dmm.co.jp/digital/video/' . $strippedId . '/' . $strippedId . 'pl.jpg'
            : '';

        $onerror = $altThumb ? ' onerror="this.onerror=null;this.src=\'' . h($altThumb) . '\'"' : '';

        if (!$work) {
            self::$lastWorkUrl = $affiliateUrl;
            $html = '<div class="embed-card embed-card--work">';
            $html .= '<a href="' . h($affiliateUrl) . '" target="_blank" rel="nofollow noopener" class="embed-card__inner embed-card__inner--work">';
            $html .= '<div class="embed-card__image embed-card__image--work"><img src="' . h($fallbackThumb) . '" alt="' . h($linkText ?: $sourceId) . '" loading="lazy"' . $onerror . '></div>';
            $html .= '<p class="embed-card__title embed-card__title--work">' . h($linkText ?: $sourceId) . '</p>';
            $html .= '</a></div>' . "\n";
            return $html;
        }

        $thumb = h($work['thumbnail_url'] ?? '') ?: h($fallbackThumb);
        $title = h($work['title'] ?? '');
        $url = $affiliateUrl;
        self::$lastWorkUrl = $url;

        $html = '<div class="embed-card embed-card--work">';
        $html .= '<a href="' . h($url) . '" target="_blank" rel="nofollow noopener" class="embed-card__inner embed-card__inner--work">';
        $html .= '<div class="embed-card__image embed-card__image--work"><img src="' . $thumb . '" alt="' . $title . '" loading="lazy"' . $onerror . '></div>';
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
        if ($thumb) {
            $html .= '<a href="' . h($pageUrl) . '" class="embed-card__inner">';
            $html .= '<div class="embed-card__image embed-card__image--portrait"><img src="' . $thumb . '" alt="' . $name . '" loading="lazy"></div>';
        } else {
            $html .= '<a href="' . h($pageUrl) . '" class="embed-card__inner embed-card__inner--no-image">';
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
