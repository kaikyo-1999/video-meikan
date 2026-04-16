<h1 class="page-title"><?= h($actress['name']) ?>の<?= h($genre['name']) ?>作品一覧</h1>

<!-- 検索バー（全幅） -->
<div class="search-bar">
    <div class="search-bar__inner">
        <input type="text" id="workSearch" class="search-bar__input" placeholder="作品名で検索..." aria-label="キーワード検索">
        <button type="button" id="workSearchBtn" class="search-bar__btn">検索</button>
    </div>
</div>

<div class="page-layout">
    <!-- SP: フィルター開閉ボタン -->
    <button class="page-layout__filter-toggle" id="filterToggle" type="button">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M1 2h14v2H1V2zm2 5h10v2H3V7zm2 5h6v2H5v-2z"/></svg>
        <span>絞り込み</span>
    </button>

    <!-- サイドバー -->
    <aside class="page-layout__sidebar" id="filterSidebar">
        <div class="page-layout__sidebar-header">
            <span class="page-layout__sidebar-title">絞り込み</span>
            <button class="page-layout__sidebar-close" id="filterClose" type="button">&times;</button>
        </div>

        <?php if (!empty($allGenres) && count($allGenres) > 1): ?>
        <div class="sidebar-section">
            <h3 class="sidebar-section__title">ジャンル</h3>
            <ul class="sidebar-section__list sidebar-genre-list">
                <?php foreach ($allGenres as $_i => $g): ?>
                <li<?= $_i >= 8 ? ' class="sidebar-genre-list__extra"' : '' ?>>
                    <a href="<?= h(url($actress['slug'] . '/' . $g['slug'] . '/')) ?>" class="sidebar-section__link<?= $g['slug'] === $genre['slug'] ? ' is-active' : '' ?>">
                        <?= h($g['name']) ?>
                        <span class="sidebar-section__count"><?= (int)$g['work_count'] ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($allGenres) > 8): ?>
            <button class="sidebar-genre-list__toggle" type="button">もっと見る（残り<?= count($allGenres) - 8 ?>件）</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="sidebar-section">
            <h3 class="sidebar-section__title">映像タイプ</h3>
            <div class="sidebar-section__pills" id="workVr" role="radiogroup" aria-label="VRフィルター">
                <button class="work-controls__pill is-active" data-vr="" type="button">すべて</button>
                <button class="work-controls__pill" data-vr="2d" type="button">2D</button>
                <button class="work-controls__pill" data-vr="vr" type="button">VR</button>
            </div>
        </div>

        <div class="sidebar-section">
            <h3 class="sidebar-section__title">出演形態</h3>
            <label class="work-controls__checkbox">
                <input type="checkbox" id="workSingle" checked>
                <span>単体作品のみ</span>
            </label>
        </div>

        <?php if (!empty($similarActresses)): ?>
        <div class="sidebar-section sidebar-similar">
            <h3 class="sidebar-section__title"><?= h($actress['name']) ?>が好きな人にオススメ</h3>
            <ul class="sidebar-similar__list">
                <?php foreach (array_slice($similarActresses, 0, 6) as $rec): ?>
                <li>
                    <a href="<?= h(url($rec['slug'] . '/')) ?>" class="sidebar-similar__item">
                        <div class="sidebar-similar__image">
                            <?php if (!empty($rec['thumbnail_url'])): ?>
                                <img src="<?= h($rec['thumbnail_url']) ?>" alt="<?= h($rec['name']) ?>" width="300" height="300" loading="lazy">
                            <?php else: ?>
                                <div class="sidebar-similar__placeholder"></div>
                            <?php endif; ?>
                        </div>
                        <span class="sidebar-similar__name"><?= h($rec['name']) ?><?php if (!empty($rec['birthday'])): ?><span class="sidebar-similar__age">（<?= (new DateTime($rec['birthday']))->diff(new DateTime())->y ?>歳）</span><?php endif; ?></span>
                        <?php if (!empty($rec['bust']) && !empty($rec['waist']) && !empty($rec['hip'])): ?>
                            <span class="sidebar-similar__meta">B<?= (int)$rec['bust'] ?> W<?= (int)$rec['waist'] ?> H<?= (int)$rec['hip'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </aside>

    <!-- オーバーレイ（SP用） -->
    <div class="page-layout__overlay" id="filterOverlay"></div>

    <!-- メインコンテンツ -->
    <div class="page-layout__content">
        <!-- ソートタブ + 件数 -->
        <div class="sort-header">
            <p class="sort-header__count">対象作品：<strong id="workTotalCount"><?= $totalWorks ?></strong> 件</p>
            <div class="sort-header__tabs" id="workSort" role="radiogroup" aria-label="並び替え">
                <button class="sort-header__tab" data-sort="" type="button">新着順</button>
                <button class="sort-header__tab is-active" data-sort="rank" type="button">人気順</button>
                <button class="sort-header__tab" data-sort="review" type="button">評価順</button>
                <button class="sort-header__tab" data-sort="-date" type="button">古い順</button>
            </div>
        </div>

        <div class="work-list work-list--v2" id="workList" data-page="1" data-total-pages="<?= $pagination['total_pages'] ?>" data-actress-id="<?= (int)$actress['id'] ?>" data-genre-id="<?= (int)$genre['id'] ?>">
            <?php $workIndex = 0; ?>
            <?php foreach ($works as $work): ?>
                <?php require TEMPLATE_DIR . '/partials/work-card-v2.php'; ?>
                <?php $workIndex++; ?>
                <?php if (($workIndex === 6 || $workIndex === 12) && !empty($similarActresses)): ?>
                    <div class="similar-inline <?= $workIndex === 6 ? 'similar-inline--sp' : 'similar-inline--pc' ?>">
                        <p class="similar-inline__title"><?= h($actress['name']) ?>が好きな人にオススメ</p>
                        <div class="similar-inline__scroll">
                            <?php foreach ($similarActresses as $similar): ?>
                                <a href="<?= h(url($similar['slug'] . '/')) ?>" class="similar-inline__item">
                                    <div class="similar-inline__image">
                                        <?php if (!empty($similar['thumbnail_url'])): ?>
                                            <img src="<?= h($similar['thumbnail_url']) ?>" alt="<?= h($similar['name']) ?>" width="300" height="300" loading="lazy">
                                        <?php else: ?>
                                            <div class="similar-inline__placeholder"></div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="similar-inline__name"><?= h($similar['name']) ?><?php if (!empty($similar['birthday'])): ?><span class="similar-inline__age">（<?= (new DateTime($similar['birthday']))->diff(new DateTime())->y ?>歳）</span><?php endif; ?></span>
                                    <?php if (!empty($similar['bust']) && !empty($similar['waist']) && !empty($similar['hip'])): ?>
                                        <span class="similar-inline__size">B<?= (int)$similar['bust'] ?> W<?= (int)$similar['waist'] ?> H<?= (int)$similar['hip'] ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <p id="workNoResults" class="work-controls__no-results" style="display:none;">該当する作品が見つかりませんでした。</p>

        <div id="infiniteLoader" class="infinite-loader" <?php if ($pagination['total_pages'] <= 1): ?>style="display:none;"<?php endif; ?>>
            <div class="infinite-loader__spinner"></div>
            <p class="infinite-loader__text">読み込み中...</p>
        </div>
    </div>

</div>
