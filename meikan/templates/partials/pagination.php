<?php if ($pagination['total_pages'] > 1): ?>
<nav class="pagination" aria-label="ページナビゲーション">
    <?php if ($pagination['current_page'] > 1): ?>
        <a href="?page=<?= $pagination['current_page'] - 1 ?>" class="pagination__btn">&larr; 前へ</a>
    <?php else: ?>
        <span class="pagination__btn pagination__btn--disabled">&larr; 前へ</span>
    <?php endif; ?>

    <?php
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
    if ($start > 1): ?>
        <a href="?page=1" class="pagination__num">1</a>
        <?php if ($start > 2): ?><span class="pagination__dots">&hellip;</span><?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $start; $i <= $end; $i++): ?>
        <?php if ($i === $pagination['current_page']): ?>
            <span class="pagination__num pagination__num--active"><?= $i ?></span>
        <?php else: ?>
            <a href="?page=<?= $i ?>" class="pagination__num"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($end < $pagination['total_pages']): ?>
        <?php if ($end < $pagination['total_pages'] - 1): ?><span class="pagination__dots">&hellip;</span><?php endif; ?>
        <a href="?page=<?= $pagination['total_pages'] ?>" class="pagination__num"><?= $pagination['total_pages'] ?></a>
    <?php endif; ?>

    <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
        <a href="?page=<?= $pagination['current_page'] + 1 ?>" class="pagination__btn">次へ &rarr;</a>
    <?php else: ?>
        <span class="pagination__btn pagination__btn--disabled">次へ &rarr;</span>
    <?php endif; ?>
</nav>
<?php endif; ?>
