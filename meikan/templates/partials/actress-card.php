<a href="<?= h(url($actress['slug'] . '/')) ?>" class="actress-card">
    <div class="actress-card__image">
        <?php if (!empty($actress['thumbnail_url'])): ?>
            <img src="<?= h($actress['thumbnail_url']) ?>" alt="<?= h($actress['name']) ?>" width="300" height="300" <?= empty($lazy) ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
        <?php else: ?>
            <div class="actress-card__placeholder"></div>
        <?php endif; ?>
    </div>
    <div class="actress-card__info">
        <h2 class="actress-card__name"><?= h($actress['name']) ?></h2>
        <p class="actress-card__count"><?= (int)$actress['work_count'] ?>作品</p>
    </div>
</a>
