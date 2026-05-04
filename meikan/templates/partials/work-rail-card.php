<?php
/**
 * TOP「今週ホットな作品」横スクロール rail 用カード。
 * 必須変数: $work (id, title, thumbnail_url, affiliate_url, source_id, lead_actress?)
 *           $rank (1始まり、任意)
 */
$cid = $work['source_id'] ?? '';
$href = !empty($work['affiliate_url']) ? $work['affiliate_url'] : '#';
$thumb = fanzaImg($work['thumbnail_url'] ?? '', 'pl');
?>
<a class="work-rail-card" href="<?= h($href) ?>" target="_blank" rel="nofollow noopener"
   data-fanza-cid="<?= h($cid) ?>" data-fanza-link-type="hot_rail">
    <?php if (!empty($rank)): ?>
        <span class="work-rail-card__rank<?= $rank <= 3 ? ' work-rail-card__rank--top' : '' ?>"><?= (int)$rank ?></span>
    <?php endif; ?>
    <div class="work-rail-card__media">
        <?php if (!empty($thumb)): ?>
            <img src="<?= h($thumb) ?>" alt="<?= h($work['title']) ?>" width="220" height="148" loading="lazy" decoding="async">
        <?php else: ?>
            <div class="work-rail-card__no-image">NO IMAGE</div>
        <?php endif; ?>
    </div>
    <div class="work-rail-card__body">
        <p class="work-rail-card__title"><?= h($work['title']) ?></p>
        <?php if (!empty($work['lead_actress']['name'])): ?>
            <p class="work-rail-card__actress"><?= h($work['lead_actress']['name']) ?></p>
        <?php endif; ?>
    </div>
</a>
