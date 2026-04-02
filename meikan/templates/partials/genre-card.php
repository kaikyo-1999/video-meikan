<a href="<?= h(url($actressSlug . '/' . $genre['slug'] . '/')) ?>" class="genre-card">
    <span class="genre-card__name"><?= h($genre['name']) ?></span>
    <span class="genre-card__count"><?= (int)$genre['work_count'] ?>作品</span>
    <span class="genre-card__arrow">&rarr;</span>
</a>
