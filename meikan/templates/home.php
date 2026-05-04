<?php /* Filmarks 風 TOP — Phase 2 (2026-05-04) */ ?>

<!-- ① ヒーロー: 検索バー (Phase 3 で活性化) + 3タブアンカージャンプ -->
<section class="hero" x-data="{tab: 'work'}">
    <h1 class="hero__title"><?= h(SITE_NAME) ?></h1>
    <p class="hero__subtitle">AV女優<?= number_format((int)($actressCount ?? 0)) ?>人をジャンル別に。話題の作品・新人・人気ジャンルを毎日更新。</p>

    <div class="hero__tabs" role="tablist">
        <a href="#hot-works" :class="{'is-active': tab==='work'}" @click="tab='work'" class="hero__tab is-active" role="tab">
            <span class="hero__tab-icon">🎬</span>作品
        </a>
        <a href="#shinjin-section" :class="{'is-active': tab==='actress'}" @click="tab='actress'" class="hero__tab" role="tab">
            <span class="hero__tab-icon">⭐</span>女優
        </a>
        <a href="#genre-tiles" :class="{'is-active': tab==='genre'}" @click="tab='genre'" class="hero__tab" role="tab">
            <span class="hero__tab-icon">📚</span>ジャンル
        </a>
    </div>

    <?php /* 検索バーは Phase 3 で活性化予定。disabled UI は壊れて見えるので一旦非表示 */ ?>
</section>

<!-- ② 🎬 FC2 注目ランキング (週間 TOP10) -->
<?php if (!empty($fc2Ranking)): ?>
<section class="top-section" id="fc2-rail">
    <h2 class="top-section__title">🎬 FC2 注目ランキング <span class="top-section__title-sub">週間</span></h2>
    <ol class="hot-rail hot-rail--fc2">
        <?php foreach ($fc2Ranking as $i => $work): ?>
            <li class="hot-rail__item">
                <?php $rank = $i + 1; require TEMPLATE_DIR . '/partials/fc2-rail-card.php'; ?>
            </li>
        <?php endforeach; ?>
    </ol>
    <a href="<?= h(url('fc2/')) ?>" class="top-section__more">もっと見る</a>
</section>
<?php endif; ?>

<!-- ③ 🔥 今週ホットな作品 (fanza_click 急上昇) -->
<?php if (!empty($hotWorks)): ?>
<section class="top-section" id="hot-works">
    <h2 class="top-section__title">🔥 今週ホットな作品</h2>
    <ol class="hot-rail hot-rail--works">
        <?php foreach ($hotWorks as $i => $work): ?>
            <li class="hot-rail__item">
                <?php $rank = $i + 1; require TEMPLATE_DIR . '/partials/work-rail-card.php'; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
<?php endif; ?>

<!-- ④ 💰 FANZA セール中 -->
<?php if (!empty($saleWorks)): ?>
<section class="top-section" id="sale-works">
    <h2 class="top-section__title">💰 FANZA セール中</h2>
    <ol class="hot-rail hot-rail--sale">
        <?php foreach ($saleWorks as $work): ?>
            <li class="hot-rail__item">
                <?php require TEMPLATE_DIR . '/partials/sale-rail-card.php'; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
<?php endif; ?>

<!-- ⑤ ⭐ PV急上昇女優 TOP6 -->
<?php if (!empty($hotActresses)): ?>
<section class="top-section" id="hot-actresses">
    <h2 class="top-section__title">⭐ PV急上昇 女優</h2>
    <div class="actress-grid actress-grid--6">
        <?php foreach ($hotActresses as $actress): ?>
            <?php $lazy = true; require TEMPLATE_DIR . '/partials/actress-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ⑥ 🆕 今月デビュー新人 -->
<?php if (!empty($debutActresses)): ?>
<section class="top-section" id="shinjin-section">
    <h2 class="top-section__title">🆕 <?= h($debutMonthLabel) ?>デビューの新人女優</h2>
    <div class="actress-grid actress-grid--6">
        <?php $lazy = true; ?>
        <?php foreach ($debutActresses as $actress): ?>
            <?php require TEMPLATE_DIR . '/partials/actress-card.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php if ($debutArticleSlug): ?>
        <a href="<?= h(url('article/' . $debutArticleSlug . '/')) ?>" class="top-section__more">もっと見る</a>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- ⑦ 📚 ジャンルから探す (12タイル) -->
<?php if (!empty($featuredGenres)): ?>
<section class="top-section" id="genre-tiles">
    <h2 class="top-section__title">📚 ジャンルから探す</h2>
    <div class="genre-tile-grid">
        <?php foreach ($featuredGenres as $genre): ?>
            <?php require TEMPLATE_DIR . '/partials/genre-tile.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ⑧ 📝 最新コラム記事 -->
<?php if (!empty($latestArticles)): ?>
<section class="top-section">
    <h2 class="top-section__title">📝 最新コラム記事</h2>
    <div class="article-list">
        <?php foreach ($latestArticles as $article): ?>
        <a href="<?= h(url('article/' . $article['slug'] . '/')) ?>" class="article-list-card">
            <div class="article-list-card__body">
                <?php if (!empty($article['category'])): ?>
                <span class="article-list-card__category"><?= h($article['category']) ?></span>
                <?php endif; ?>
                <h2 class="article-list-card__title"><?= h($article['title']) ?></h2>
                <p class="article-list-card__desc"><?= h($article['description']) ?></p>
                <time class="article-list-card__date"><?= h($article['published_at']) ?></time>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <a href="<?= h(url('article/')) ?>" class="top-section__more">もっと見る</a>
</section>
<?php endif; ?>
