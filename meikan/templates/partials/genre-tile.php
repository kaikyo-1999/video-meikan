<?php
/**
 * 「ジャンルから探す」タイル
 * 必須変数: $genre (slug, name, work_count, cover_image)
 */
?>
<a href="<?= h(url('genre/' . $genre['slug'] . '/')) ?>" class="genre-tile">
    <div class="genre-tile__image">
        <?php if (!empty($genre['cover_image'])): ?>
            <img src="<?= h(fanzaImg($genre['cover_image'], 'ps')) ?>" alt="<?= h($genre['name']) ?>" width="160" height="160" loading="lazy" decoding="async">
        <?php else: ?>
            <div class="genre-tile__placeholder"></div>
        <?php endif; ?>
        <span class="genre-tile__overlay"></span>
    </div>
    <div class="genre-tile__body">
        <span class="genre-tile__name"><?= h($genre['name']) ?></span>
        <?php if (!empty($genre['work_count'])): ?>
            <span class="genre-tile__count"><?= (int)$genre['work_count'] ?>作品</span>
        <?php endif; ?>
    </div>
</a>
