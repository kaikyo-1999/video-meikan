<div class="work-card">
    <div class="work-card__image">
        <?php if (!empty($work['thumbnail_url'])): ?>
            <img src="<?= h($work['thumbnail_url']) ?>" alt="<?= h($work['title']) ?>" loading="lazy">
        <?php else: ?>
            <div class="work-card__placeholder"></div>
        <?php endif; ?>
    </div>
    <div class="work-card__info">
        <h3 class="work-card__title"><?= h($work['title']) ?></h3>
        <p class="work-card__meta">
            <?php if (!empty($work['release_date'])): ?>
                <span>発売日：<?= h($work['release_date']) ?></span>
            <?php endif; ?>
            <?php if (!empty($work['label'])): ?>
                <span>レーベル：<?= h($work['label']) ?></span>
            <?php endif; ?>
        </p>
        <?php if (!empty($work['affiliate_url'])): ?>
            <a href="<?= h($work['affiliate_url']) ?>" class="work-card__cta" target="_blank" rel="nofollow noopener">作品を見る &rarr;</a>
        <?php endif; ?>
    </div>
</div>
