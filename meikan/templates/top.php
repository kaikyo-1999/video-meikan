<h1 class="page-title"><?= h(SITE_TITLE) ?></h1>
<p class="page-description"><?= h(SITE_DESCRIPTION) ?></p>

<div class="actress-grid">
    <?php foreach ($actresses as $i => $actress): ?>
        <?php $lazy = ($i >= 6); ?>
        <?php require TEMPLATE_DIR . '/partials/actress-card.php'; ?>
    <?php endforeach; ?>
</div>
