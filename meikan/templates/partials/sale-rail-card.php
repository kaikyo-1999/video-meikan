<?php
/**
 * TOP「FANZA セール中」横スクロール rail 用カード。
 * 必須変数: $work (id, title, thumbnail_url, affiliate_url, source_id, price, list_price, sale_end_at, discount_pct, lead_actress?)
 */
$cid = $work['source_id'] ?? '';
$href = !empty($work['affiliate_url']) ? $work['affiliate_url'] : '#';
// 2 枚目のサンプル画像優先・無ければパッケージ画像にフォールバック
$thumb = !empty($work['secondary_image'])
    ? $work['secondary_image']
    : fanzaImg($work['thumbnail_url'] ?? '', 'pl');

// 残り時間を「あとX日」または「あとX時間」で表示
$daysLeft = null; $hoursLeft = null;
if (!empty($work['sale_end_at'])) {
    $diff = strtotime($work['sale_end_at']) - time();
    if ($diff > 0) {
        $daysLeft = (int)floor($diff / 86400);
        if ($daysLeft < 1) {
            $hoursLeft = (int)floor($diff / 3600);
        }
    }
}
?>
<a class="sale-rail-card" href="<?= h($href) ?>" target="_blank" rel="nofollow noopener"
   data-fanza-cid="<?= h($cid) ?>" data-fanza-link-type="sale_rail">
    <?php if (!empty($work['discount_pct']) && $work['discount_pct'] >= 10): ?>
        <span class="sale-rail-card__badge"><?= (int)$work['discount_pct'] ?>% OFF</span>
    <?php endif; ?>
    <div class="sale-rail-card__media">
        <?php if (!empty($thumb)): ?>
            <img src="<?= h($thumb) ?>" alt="<?= h($work['title']) ?>" width="220" height="148" loading="lazy" decoding="async">
        <?php else: ?>
            <div class="sale-rail-card__no-image">NO IMAGE</div>
        <?php endif; ?>
    </div>
    <div class="sale-rail-card__body">
        <p class="sale-rail-card__title"><?= h($work['title']) ?></p>
        <div class="sale-rail-card__prices">
            <?php if (!empty($work['list_price']) && $work['list_price'] > ($work['price'] ?? 0)): ?>
                <span class="sale-rail-card__list-price">¥<?= number_format((int)$work['list_price']) ?></span>
            <?php endif; ?>
            <?php if (!empty($work['price'])): ?>
                <span class="sale-rail-card__sale-price">¥<?= number_format((int)$work['price']) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($daysLeft !== null): ?>
            <p class="sale-rail-card__deadline">
                <?php if ($daysLeft >= 1): ?>
                    あと<?= (int)$daysLeft ?>日で終了
                <?php elseif ($hoursLeft !== null && $hoursLeft >= 1): ?>
                    あと<?= (int)$hoursLeft ?>時間で終了
                <?php else: ?>
                    まもなく終了
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</a>
