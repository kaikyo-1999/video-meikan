<h1 class="page-title"><?= h($actress['name']) ?>の<?= h($genre['name']) ?>作品一覧</h1>
<span class="badge"><?= $totalWorks ?>作品</span>

<div class="work-list">
    <?php foreach ($works as $work): ?>
        <?php require TEMPLATE_DIR . '/partials/work-card-horizontal.php'; ?>
    <?php endforeach; ?>
</div>

<?php require TEMPLATE_DIR . '/partials/pagination.php'; ?>
