<?php $cardTag = !empty($work['affiliate_url']) ? 'a' : 'div'; ?>
<<?= $cardTag ?> class="work-card"<?php if (!empty($work['affiliate_url'])): ?> href="<?= h($work['affiliate_url']) ?>" target="_blank" rel="nofollow noopener"<?php endif; ?>>
    <div class="work-card__image">
        <?php if (!empty($work['thumbnail_url'])): ?>
            <img src="<?= h($work['thumbnail_url']) ?>" alt="<?= h($work['title']) ?>" width="147" height="200" loading="lazy">
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
            <span class="work-card__cta">無料でサンプル動画を見る &rarr;</span>
        <?php endif; ?>
    </div>
</<?= $cardTag ?>>
