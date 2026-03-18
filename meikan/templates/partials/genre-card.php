<a href="<?= h(url($actressSlug . '/' . $genre['slug'] . '/')) ?>" class="genre-card">
    <h3 class="genre-card__name"><?= h($genre['name']) ?></h3>
    <p class="genre-card__count"><?= (int)$genre['work_count'] ?>作品</p>
    <span class="genre-card__arrow">&rarr;</span>
</a>
