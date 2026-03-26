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

<h2 class="section-title">ジャンル別作品</h2>

<?php $actressSlug = $actress['slug']; ?>
<div class="genre-grid">
    <?php foreach ($genres as $genre): ?>
        <?php require TEMPLATE_DIR . '/partials/genre-card.php'; ?>
    <?php endforeach; ?>
</div>
