<?php /* Filmarks 風 TOP — Phase 2 (2026-05-04) — Hero 撤去済み */ ?>

<!-- ② FC2 注目ランキング (週間 TOP10) -->
<?php if (!empty($fc2Ranking)): ?>
<section class="top-section" id="fc2-rail" aria-labelledby="fc2-rail-title">
    <h2 class="top-section__title" id="fc2-rail-title">FC2 注目ランキング <span class="top-section__title-sub">週間 TOP10</span></h2>
    <div class="rail-wrap" role="region" aria-label="FC2 週間ランキング 横スクロール">
        <ol class="hot-rail hot-rail--fc2">
            <?php foreach ($fc2Ranking as $i => $work): ?>
                <li class="hot-rail__item">
                    <?php $rank = $i + 1; require TEMPLATE_DIR . '/partials/fc2-rail-card.php'; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <a href="<?= h(url('fc2/')) ?>" class="top-section__more">もっと見る</a>
</section>
<?php endif; ?>

<!-- ③ 今週ホットな作品 (fanza_click 急上昇) -->
<?php if (!empty($hotWorks)): ?>
<section class="top-section" id="hot-works" aria-labelledby="hot-works-title">
    <h2 class="top-section__title" id="hot-works-title">
        今週ホットな作品 <span class="top-section__title-sub">急上昇 TOP10</span>
        <?php if (!empty($hotWorksUpdatedAt)): ?>
            <time class="top-section__updated" datetime="<?= h(date('c', strtotime($hotWorksUpdatedAt))) ?>"><?= h(formatRelativeTime($hotWorksUpdatedAt)) ?>更新</time>
        <?php endif; ?>
    </h2>
    <div class="rail-wrap" role="region" aria-label="人気作品 横スクロール">
        <ol class="hot-rail hot-rail--works">
            <?php foreach ($hotWorks as $i => $work): ?>
                <li class="hot-rail__item">
                    <?php $rank = $i + 1; require TEMPLATE_DIR . '/partials/work-rail-card.php'; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
<?php endif; ?>

<!-- ④ FANZA セール中 -->
<?php if (!empty($saleWorks)): ?>
<section class="top-section" id="sale-works" aria-labelledby="sale-works-title">
    <h2 class="top-section__title" id="sale-works-title">FANZA セール中 <span class="top-section__title-sub">期間限定価格</span></h2>
    <div class="rail-wrap" role="region" aria-label="セール作品 横スクロール">
        <ol class="hot-rail hot-rail--sale">
            <?php foreach ($saleWorks as $work): ?>
                <li class="hot-rail__item">
                    <?php require TEMPLATE_DIR . '/partials/sale-rail-card.php'; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <a href="<?= h(url('sale/')) ?>" class="top-section__more">セール作品を全て見る</a>
</section>
<?php endif; ?>

<!-- ⑤ PV急上昇女優 TOP6 -->
<?php if (!empty($hotActresses)): ?>
<section class="top-section" id="hot-actresses" aria-labelledby="hot-actresses-title">
    <h2 class="top-section__title" id="hot-actresses-title">
        PV急上昇 女優 <span class="top-section__title-sub">直近7日</span>
        <?php if (!empty($hotActressesUpdatedAt)): ?>
            <time class="top-section__updated" datetime="<?= h(date('c', strtotime($hotActressesUpdatedAt))) ?>"><?= h(formatRelativeTime($hotActressesUpdatedAt)) ?>更新</time>
        <?php endif; ?>
    </h2>
    <div class="actress-grid actress-grid--6">
        <?php foreach ($hotActresses as $actress): ?>
            <?php $lazy = true; require TEMPLATE_DIR . '/partials/actress-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ⑥ 今月デビュー新人 -->
<?php if (!empty($debutActresses)): ?>
<section class="top-section" id="shinjin-section" aria-labelledby="shinjin-title">
    <h2 class="top-section__title" id="shinjin-title"><?= h($debutMonthLabel) ?>デビューの新人女優 <span class="top-section__title-sub">今月の話題</span></h2>
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

<!-- ⑦ ジャンルから探す (12タイル) -->
<?php if (!empty($featuredGenres)): ?>
<section class="top-section" id="genre-tiles" aria-labelledby="genre-tiles-title">
    <h2 class="top-section__title" id="genre-tiles-title">ジャンルから探す <span class="top-section__title-sub">人気12カテゴリ</span></h2>
    <div class="genre-tile-grid">
        <?php foreach ($featuredGenres as $genre): ?>
            <?php require TEMPLATE_DIR . '/partials/genre-tile.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ⑧ 最新コラム記事 -->
<?php if (!empty($latestArticles)): ?>
<section class="top-section" aria-labelledby="latest-articles-title">
    <h2 class="top-section__title" id="latest-articles-title">最新コラム記事 <span class="top-section__title-sub">編集部ピックアップ</span></h2>
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

<!-- ⑨ AV博士について + 統計ハイライト (A9) -->
<section class="top-section site-intro" aria-labelledby="site-intro-title">
    <h2 class="top-section__title" id="site-intro-title">AV博士について <span class="top-section__title-sub">サイトの楽しみ方</span></h2>
    <div class="site-intro__body">
        <p class="site-intro__lead">
            「AV博士」は、人気AV女優のプロフィール・出演作品をジャンル別に整理した作品データベースです。
            FANZA の最新リリース情報を毎日取り込み、新人デビュー・話題作・セール作品をひとつのページで把握できるようにしました。
            女優ページではコサイン類似度ベースの「似ている女優」、ジャンルページでは作品サムネイル＋発売日順の一覧を提供しています。
        </p>
        <dl class="site-intro__stats" aria-label="サイト統計">
            <div class="site-intro__stat">
                <dt class="site-intro__stat-label">登録女優</dt>
                <dd class="site-intro__stat-value"><?= number_format((int)($actressCount ?? 0)) ?><span class="site-intro__stat-unit">人</span></dd>
            </div>
            <div class="site-intro__stat">
                <dt class="site-intro__stat-label">作品数</dt>
                <dd class="site-intro__stat-value"><?= number_format((int)($workCount ?? 0)) ?><span class="site-intro__stat-unit">本</span></dd>
            </div>
            <div class="site-intro__stat">
                <dt class="site-intro__stat-label">コラム記事</dt>
                <dd class="site-intro__stat-value"><?= number_format((int)($articleCount ?? 0)) ?><span class="site-intro__stat-unit">本</span></dd>
            </div>
        </dl>
    </div>
</section>
