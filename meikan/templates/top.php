<h1 class="page-title"><?= h(SITE_TITLE) ?></h1>
<p class="page-description"><?= h(SITE_DESCRIPTION) ?></p>

<div class="actress-grid">
    <?php foreach ($actresses as $actress): ?>
        <?php require TEMPLATE_DIR . '/partials/actress-card.php'; ?>
    <?php endforeach; ?>
</div>
