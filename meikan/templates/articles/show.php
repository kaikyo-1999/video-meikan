<?php $headingText = $article['category'] ?: '記事'; require TEMPLATE_DIR . '/partials/section-heading.php'; ?>

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

    <div class="article__body">
        <?= $article['body_html'] ?>
    </div>

    <?php require TEMPLATE_DIR . '/partials/author-box.php'; ?>
</article>

<?php if (!empty($related)): ?>
<?php $headingText = '関連記事'; require TEMPLATE_DIR . '/partials/section-heading.php'; ?>
<div class="article-list">
    <?php foreach ($related as $rel): ?>
    <a href="<?= h(url('articles/' . $rel['slug'] . '/')) ?>" class="article-list-card">
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

<?php $adSize = '728x90'; require TEMPLATE_DIR . '/partials/ad-slot.php'; ?>
