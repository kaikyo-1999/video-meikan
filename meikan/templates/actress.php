<?php
$hasProfile = !empty($actress['bust']) || !empty($actress['height']) || !empty($actress['birthday']) || !empty($actress['blood_type']) || !empty($actress['hobby']) || !empty($actress['prefectures']);
?>
<div class="profile-section">
    <div class="profile-section__image">
        <?php if (!empty($actress['thumbnail_url'])): ?>
            <img src="<?= h($actress['thumbnail_url']) ?>" alt="<?= h($actress['name']) ?>" width="300" height="300">
        <?php else: ?>
            <div class="profile-section__placeholder"></div>
        <?php endif; ?>
    </div>
    <div class="profile-section__info">
        <h1 class="profile-section__name"><?= h($actress['name']) ?><?php if (!empty($actress['birthday'])): ?><?php $_age = (new DateTime($actress['birthday']))->diff(new DateTime())->y; ?><span class="profile-section__name-age">（<?= $_age ?>歳）</span><?php endif; ?></h1>
        <p class="profile-section__count">作品数：<?= (int)$actress['work_count'] ?>本</p>
        <?php if ($hasProfile): ?>
        <table class="profile-detail__table">
            <?php if (!empty($actress['birthday'])): ?>
            <?php
                $birthday = new DateTime($actress['birthday']);
                $age = $birthday->diff(new DateTime())->y;
            ?>
            <tr><th>年齢</th><td><?= $age ?>歳<span class="profile-detail__birthday">（<?= $birthday->format('Y年n月j日') ?>生まれ）</span></td></tr>
            <?php endif; ?>
            <?php if (!empty($actress['height'])): ?>
            <tr><th>身長</th><td><?= (int)$actress['height'] ?>cm</td></tr>
            <?php endif; ?>
            <?php if (!empty($actress['bust']) && !empty($actress['waist']) && !empty($actress['hip'])): ?>
            <tr><th>スリーサイズ</th><td>B<?= (int)$actress['bust'] ?> / W<?= (int)$actress['waist'] ?> / H<?= (int)$actress['hip'] ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($actress['blood_type'])): ?>
            <tr><th>血液型</th><td><?= h($actress['blood_type']) ?>型</td></tr>
            <?php endif; ?>
            <?php if (!empty($actress['prefectures'])): ?>
            <tr><th>出身地</th><td><?= h($actress['prefectures']) ?></td></tr>
            <?php endif; ?>
            <?php if (!empty($actress['hobby'])): ?>
            <tr><th>趣味</th><td><?= h($actress['hobby']) ?></td></tr>
            <?php endif; ?>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($actress['comment'])): ?>
<div class="hakase-comment">
    <div class="hakase-comment__icon"><img src="<?= h(url('public/images/author-avatar.png')) ?>" alt="av女優博士" width="48" height="48" loading="lazy"></div>
    <div class="hakase-comment__body">
        <span class="hakase-comment__label">AV博士のコメント</span>
        <div class="hakase-comment__text"><p><?= nl2br(h($actress['comment'])) ?></p></div>
        <button class="hakase-comment__toggle" type="button" onclick="this.parentElement.classList.toggle('is-expanded');this.textContent=this.parentElement.classList.contains('is-expanded')?'閉じる':'続きを読む'">続きを読む</button>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($genres)): ?>
<h2 class="section-title">ジャンル別作品</h2>

<?php $actressSlug = $actress['slug']; ?>
<div class="genre-grid genre-grid--collapsible<?= count($genres) > 6 ? '' : ' is-expanded' ?>">
    <?php foreach ($genres as $genre): ?>
        <?php require TEMPLATE_DIR . '/partials/genre-card.php'; ?>
    <?php endforeach; ?>
</div>
<?php if (count($genres) > 6): ?>
<button class="genre-grid__toggle" type="button">もっと見る</button>
<?php endif; ?>
<?php endif; ?>

<?php if (!empty($works)): ?>
<h2 class="section-title">出演作品</h2>

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

        <?php if (!empty($genres)): ?>
        <div class="sidebar-section">
            <h3 class="sidebar-section__title">ジャンル</h3>
            <ul class="sidebar-section__list sidebar-genre-list">
                <?php foreach ($genres as $_i => $g): ?>
                <li<?= $_i >= 8 ? ' class="sidebar-genre-list__extra"' : '' ?>>
                    <a href="<?= h(url($actress['slug'] . '/' . $g['slug'] . '/')) ?>" class="sidebar-section__link">
                        <?= h($g['name']) ?>
                        <span class="sidebar-section__count"><?= (int)$g['work_count'] ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($genres) > 8): ?>
            <button class="sidebar-genre-list__toggle" type="button">もっと見る（残り<?= count($genres) - 8 ?>件）</button>
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
                <input type="checkbox" id="workSingle">
                <span>単体作品のみ</span>
            </label>
        </div>
    </aside>

    <!-- オーバーレイ（SP用） -->
    <div class="page-layout__overlay" id="filterOverlay"></div>

    <!-- メインコンテンツ -->
    <div class="page-layout__content">
        <!-- ソートタブ + 件数 -->
        <div class="sort-header">
            <p class="sort-header__count">対象作品：<strong id="workTotalCount"><?= $totalWorks ?></strong> 件</p>
            <div class="sort-header__tabs" id="workSort" role="radiogroup" aria-label="並び替え">
                <button class="sort-header__tab is-active" data-sort="" type="button">新着順</button>
                <button class="sort-header__tab" data-sort="rank" type="button">人気順</button>
                <button class="sort-header__tab" data-sort="review" type="button">評価順</button>
                <button class="sort-header__tab" data-sort="-date" type="button">古い順</button>
            </div>
        </div>

        <div class="work-list work-list--v2" id="workList" data-page="1" data-total-pages="<?= $worksPagination['total_pages'] ?>" data-actress-id="<?= (int)$actress['id'] ?>">
            <?php foreach ($works as $work): ?>
                <?php require TEMPLATE_DIR . '/partials/work-card-v2.php'; ?>
            <?php endforeach; ?>
        </div>

        <p id="workNoResults" class="work-controls__no-results" style="display:none;">該当する作品が見つかりませんでした。</p>

        <?php if ($worksPagination['total_pages'] > 1): ?>
        <div id="infiniteLoader" class="infinite-loader">
            <div class="infinite-loader__spinner"></div>
            <p class="infinite-loader__text">読み込み中...</p>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
$recommendActresses = !empty($similarActresses) ? $similarActresses : (!empty($relatedActresses) ? $relatedActresses : []);
?>
<?php if (!empty($recommendActresses)): ?>
<div class="similar-inline">
    <p class="similar-inline__title"><?= h($actress['name']) ?>が好きな人にはオススメの女優</p>
    <div class="similar-inline__scroll">
        <?php foreach ($recommendActresses as $rec): ?>
            <a href="<?= h(url($rec['slug'] . '/')) ?>" class="similar-inline__item">
                <div class="similar-inline__image">
                    <?php if (!empty($rec['thumbnail_url'])): ?>
                        <img src="<?= h($rec['thumbnail_url']) ?>" alt="<?= h($rec['name']) ?>" width="300" height="300" loading="lazy">
                    <?php else: ?>
                        <div class="similar-inline__placeholder"></div>
                    <?php endif; ?>
                </div>
                <span class="similar-inline__name"><?= h($rec['name']) ?><?php if (!empty($rec['birthday'])): ?><span class="similar-inline__age">（<?= (new DateTime($rec['birthday']))->diff(new DateTime())->y ?>歳）</span><?php endif; ?></span>
                <?php if (!empty($rec['bust']) && !empty($rec['waist']) && !empty($rec['hip'])): ?>
                    <span class="similar-inline__size">B<?= (int)$rec['bust'] ?> W<?= (int)$rec['waist'] ?> H<?= (int)$rec['hip'] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
