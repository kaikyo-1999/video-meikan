<?php $headingText = '記事一覧'; require TEMPLATE_DIR . '/partials/section-heading.php'; ?>
<p class="page-description">AV女優に関するまとめ記事・コラム</p>

<div class="article-list">
    <?php foreach ($articles as $article): ?>
    <a href="<?= h(url('articles/' . $article['slug'] . '/')) ?>" class="article-list-card">
        <div class="article-list-card__body">
            <?php if (!empty($article['category'])): ?>
            <span class="article-list-card__category"><?= h($article['category']) ?></span>
            <?php endif; ?>
            <h2 class="article-list-card__title"><?= h($article['title']) ?></h2>
            <p class="article-list-card__desc"><?= h($article['description']) ?></p>
            <time class="article-list-card__date"><?= h($article['published_at']) ?></time>
        </div>
    </a>
    <?php endforeach; ?>
</div>
