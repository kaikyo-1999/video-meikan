<h1 class="page-title"><?= h($actress['name']) ?>の<?= h($genre['name']) ?>作品一覧</h1>
<span class="badge"><?= $totalWorks ?>作品</span>

<div class="work-list">
    <?php $count = 0; ?>
    <?php foreach ($works as $work): ?>
        <?php require TEMPLATE_DIR . '/partials/work-card-horizontal.php'; ?>
        <?php
        $count++;
        if ($count % 4 === 0):
            $adSize = '300x250';
            require TEMPLATE_DIR . '/partials/ad-slot.php';
        endif;
        ?>
    <?php endforeach; ?>
</div>

<?php require TEMPLATE_DIR . '/partials/pagination.php'; ?>

<?php $adSize = '728x90'; require TEMPLATE_DIR . '/partials/ad-slot.php'; ?>
