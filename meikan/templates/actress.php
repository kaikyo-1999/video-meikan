<div class="profile-section">
    <div class="profile-section__image">
        <?php if (!empty($actress['thumbnail_url'])): ?>
            <img src="<?= h($actress['thumbnail_url']) ?>" alt="<?= h($actress['name']) ?>" width="300" height="300">
        <?php else: ?>
            <div class="profile-section__placeholder"></div>
        <?php endif; ?>
    </div>
    <div class="profile-section__info">
        <h1 class="profile-section__name"><?= h($actress['name']) ?></h1>
        <p class="profile-section__count">作品数：<?= (int)$actress['work_count'] ?>本</p>
    </div>
</div>

<?php if (!empty($genres)): ?>
<h2 class="section-title">ジャンル別作品</h2>

<?php $actressSlug = $actress['slug']; ?>
<div class="genre-grid">
    <?php foreach ($genres as $genre): ?>
        <?php require TEMPLATE_DIR . '/partials/genre-card.php'; ?>
    <?php endforeach; ?>
</div>
<?php elseif (!empty($works)): ?>
<h2 class="section-title">出演作品</h2>

<div class="work-list">
    <?php foreach ($works as $work): ?>
        <?php require TEMPLATE_DIR . '/partials/work-card-horizontal.php'; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($similarActresses)): ?>
<h2 class="section-title"><?= h($actress['name']) ?>に似ている女優</h2>
<div class="similar-actress-grid">
    <?php foreach ($similarActresses as $similar): ?>
        <a href="<?= h(url($similar['slug'] . '/')) ?>" class="similar-actress-card">
            <div class="similar-actress-card__image">
                <?php if (!empty($similar['thumbnail_url'])): ?>
                    <img src="<?= h($similar['thumbnail_url']) ?>" alt="<?= h($similar['name']) ?>" width="300" height="300" loading="lazy">
                <?php else: ?>
                    <div class="similar-actress-card__placeholder"></div>
                <?php endif; ?>
            </div>
            <p class="similar-actress-card__name"><?= h($similar['name']) ?></p>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
