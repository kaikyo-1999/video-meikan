<h1 class="page-title"><?= h(SITE_NAME) ?></h1>
<p class="page-description"><?= h(SITE_DESCRIPTION) ?></p>

<?php if (!empty($debutActresses)): ?>
<section class="top-section">
    <h2 class="top-section__title"><?= h($debutMonthLabel) ?>デビューの新人女優</h2>
    <div class="actress-grid actress-grid--6">
        <?php $lazy = false; ?>
        <?php foreach ($debutActresses as $actress): ?>
            <?php require TEMPLATE_DIR . '/partials/actress-card.php'; ?>
        <?php endforeach; ?>
        <?php $lazy = true; ?>
    </div>
    <?php if ($debutArticleSlug): ?>
        <a href="<?= h(url('article/' . $debutArticleSlug . '/')) ?>" class="top-section__more">もっと見る</a>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php foreach ($genreSections as $section): ?>
<?php if (!empty($section['actresses'])): ?>
<section class="top-section">
    <h2 class="top-section__title"><?= h($section['title']) ?></h2>
    <div class="actress-grid actress-grid--6">
        <?php foreach ($section['actresses'] as $actress): ?>
            <?php require TEMPLATE_DIR . '/partials/actress-card.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($section['article_slug'])): ?>
        <a href="<?= h(url('article/' . $section['article_slug'] . '/')) ?>" class="top-section__more">もっと見る</a>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php endforeach; ?>

<?php $headingText = '最新記事'; require TEMPLATE_DIR . '/partials/section-heading.php'; ?>
<div class="article-list">
    <?php foreach ($latestArticles as $article): ?>
    <a href="<?= h(url('article/' . $article['slug'] . '/')) ?>" class="article-list-card">
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
<p class="home-more"><a href="<?= url('article/') ?>" class="home-more__link">記事一覧をもっと見る &rarr;</a></p>
