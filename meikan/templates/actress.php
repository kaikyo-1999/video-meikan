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
<div class="genre-grid">
    <?php foreach ($genres as $genre): ?>
        <?php require TEMPLATE_DIR . '/partials/genre-card.php'; ?>
    <?php endforeach; ?>
</div>
<?php elseif (!empty($works)): ?>
<h2 class="section-title">出演作品</h2>

<div class="work-list">
    <?php foreach ($works as $work): ?>
        <?php require TEMPLATE_DIR . '/partials/work-card-horizontal.php'; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($similarActresses)): ?>
<h2 class="section-title"><?= h($actress['name']) ?>に似ている女優</h2>
<div class="similar-actress-grid">
    <?php foreach ($similarActresses as $similar): ?>
        <a href="<?= h(url($similar['slug'] . '/')) ?>" class="similar-actress-card">
            <div class="similar-actress-card__image">
                <?php if (!empty($similar['thumbnail_url'])): ?>
                    <img src="<?= h($similar['thumbnail_url']) ?>" alt="<?= h($similar['name']) ?>" width="300" height="300" loading="lazy">
                <?php else: ?>
                    <div class="similar-actress-card__placeholder"></div>
                <?php endif; ?>
            </div>
            <p class="similar-actress-card__name"><?= h($similar['name']) ?></p>
        </a>
    <?php endforeach; ?>
</div>
<?php elseif (!empty($relatedActresses)): ?>
<h2 class="section-title"><?= h($actress['name']) ?>の関連女優</h2>
<div class="similar-actress-grid">
    <?php foreach ($relatedActresses as $related): ?>
        <a href="<?= h(url($related['slug'] . '/')) ?>" class="similar-actress-card">
            <div class="similar-actress-card__image">
                <?php if (!empty($related['thumbnail_url'])): ?>
                    <img src="<?= h($related['thumbnail_url']) ?>" alt="<?= h($related['name']) ?>" width="300" height="300" loading="lazy">
                <?php else: ?>
                    <div class="similar-actress-card__placeholder"></div>
                <?php endif; ?>
            </div>
            <p class="similar-actress-card__name"><?= h($related['name']) ?></p>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
