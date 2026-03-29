<article class="article">
    <div class="article__header">
        <?php if (!empty($article['category'])): ?>
        <span class="article__category"><?= h($article['category']) ?></span>
        <?php endif; ?>
        <h1 class="article__title"><?= h($article['title']) ?></h1>
        <div class="article__meta">
            <time><?= h($article['published_at']) ?> 公開</time>
            <?php if (!empty($article['updated_at']) && $article['updated_at'] !== $article['published_at']): ?>
            <time>（<?= h($article['updated_at']) ?> 更新）</time>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($article['toc']) && count($article['toc']) >= 3): ?>
    <nav class="article-toc" aria-label="目次">
        <details class="article-toc__details">
            <summary class="article-toc__summary">
                <span class="article-toc__icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <line x1="8" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="8" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="8" y1="18" x2="21" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="3" cy="6" r="1.5" fill="currentColor"/>
                        <circle cx="3" cy="12" r="1.5" fill="currentColor"/>
                        <circle cx="3" cy="18" r="1.5" fill="currentColor"/>
                    </svg>
                </span>
                <span class="article-toc__title-text">目次</span>
                <span class="article-toc__toggle-label"></span>
            </summary>
            <ol class="article-toc__list">
                <?php foreach ($article['toc'] as $item): ?>
                <li class="article-toc__item article-toc__item--h<?= $item['level'] ?>">
                    <a href="#<?= h($item['id']) ?>"><?= $item['text'] ?></a>
                </li>
                <?php endforeach; ?>
            </ol>
        </details>
    </nav>
    <?php endif; ?>

    <div class="article__body">
        <?= $article['body_html'] ?>
    </div>

    <?php require TEMPLATE_DIR . '/partials/author-box.php'; ?>
</article>

<?php if (!empty($related)): ?>
<?php $headingText = '関連記事'; require TEMPLATE_DIR . '/partials/section-heading.php'; ?>
<div class="article-list">
    <?php foreach ($related as $rel): ?>
    <a href="<?= h(url('article/' . $rel['slug'] . '/')) ?>" class="article-list-card">
        <div class="article-list-card__body">
            <?php if (!empty($rel['category'])): ?>
            <span class="article-list-card__category"><?= h($rel['category']) ?></span>
            <?php endif; ?>
            <h2 class="article-list-card__title"><?= h($rel['title']) ?></h2>
            <time class="article-list-card__date"><?= h($rel['published_at']) ?></time>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

