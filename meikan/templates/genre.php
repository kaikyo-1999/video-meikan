<h1 class="page-title"><?= h($actress['name']) ?>の<?= h($genre['name']) ?>作品一覧</h1>
<span class="badge"><?= $totalWorks ?>作品</span>

<div class="work-controls" id="workControls">
    <div class="work-controls__search">
        <input type="text" id="workSearch" class="work-controls__input" placeholder="キーワードで絞り込み..." aria-label="キーワード検索">
        <button type="button" id="workSearchBtn" class="work-controls__search-btn">検索</button>
    </div>
    <div class="work-controls__sort" id="workSort" role="radiogroup" aria-label="並び替え">
        <button class="work-controls__pill is-active" data-sort="" type="button">新着順</button>
        <button class="work-controls__pill" data-sort="rank" type="button">人気順</button>
        <button class="work-controls__pill" data-sort="review" type="button">評価順</button>
        <button class="work-controls__pill" data-sort="-date" type="button">古い順</button>
    </div>
    <div class="work-controls__filters">
        <div class="work-controls__vr" id="workVr" role="radiogroup" aria-label="VRフィルター">
            <button class="work-controls__pill is-active" data-vr="" type="button">すべて</button>
            <button class="work-controls__pill" data-vr="2d" type="button">2D</button>
            <button class="work-controls__pill" data-vr="vr" type="button">VR</button>
        </div>
        <label class="work-controls__checkbox">
            <input type="checkbox" id="workSingle">
            <span>単体作品のみ</span>
        </label>
    </div>
</div>

<div class="genre-layout">
    <div class="genre-layout__main">
        <div class="work-list" id="workList" data-page="1" data-total-pages="<?= $pagination['total_pages'] ?>" data-actress-id="<?= (int)$actress['id'] ?>" data-genre-id="<?= (int)$genre['id'] ?>">
            <?php $workIndex = 0; ?>
            <?php foreach ($works as $work): ?>
                <?php require TEMPLATE_DIR . '/partials/work-card-horizontal.php'; ?>
                <?php $workIndex++; ?>
                <?php if ($workIndex === 6 && !empty($similarActresses)): ?>
                    <div class="similar-inline" id="similarInline">
                        <p class="similar-inline__title">似ている女優</p>
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
                                    <span class="similar-inline__name"><?= h($similar['name']) ?></span>
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

    <?php if (!empty($similarActresses)): ?>
    <aside class="genre-layout__sidebar" id="similarSidebar">
        <div class="similar-sidebar">
            <p class="similar-sidebar__title">似ている女優</p>
            <?php foreach ($similarActresses as $similar): ?>
                <a href="<?= h(url($similar['slug'] . '/')) ?>" class="similar-sidebar__item">
                    <div class="similar-sidebar__image">
                        <?php if (!empty($similar['thumbnail_url'])): ?>
                            <img src="<?= h($similar['thumbnail_url']) ?>" alt="<?= h($similar['name']) ?>" width="300" height="300" loading="lazy">
                        <?php else: ?>
                            <div class="similar-sidebar__placeholder"></div>
                        <?php endif; ?>
                    </div>
                    <span class="similar-sidebar__name"><?= h($similar['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>
    <?php endif; ?>
</div>
