<?php if (empty($work['title']) && empty($work['thumbnail_url'])) return; ?>
<?php
$workId = $work['id'] ?? 0;
$sampleImages = $workSampleImages[$workId] ?? [];

// サンプル画像がある場合はサンプル画像のみ、なければサムネイルを使用
$allImages = [];
if (!empty($sampleImages)) {
    foreach ($sampleImages as $img) {
        $allImages[] = $img;
    }
} elseif (!empty($work['thumbnail_url'])) {
    $allImages[] = $work['thumbnail_url'];
}

// 直接プレイヤーURL構築
$directMovieUrl = '';
if (!empty($work['sample_movie_url']) && preg_match('#/cid=([^/]+)/#', $work['sample_movie_url'], $m)) {
    $movieCid = $m[1];
    $movieAffi = '';
    if (preg_match('#/affi_id=([^/]+)/#', $work['sample_movie_url'], $a)) {
        $movieAffi = $a[1];
    }
    $directMovieUrl = 'https://www.dmm.co.jp/service/digitalapi/-/html5_player/=/cid=' . $movieCid . '/mtype=AhRVShI_/service=litevideo/mode=part/width=720/height=480/affi_id=' . $movieAffi . '/';
}
$hasMovie = $directMovieUrl !== '';
$totalSlides = count($allImages) + ($hasMovie ? 1 : 0);
$hasCarousel = $totalSlides > 1;
?>
<div class="work-card-v2">
    <div class="work-card-v2__media">
        <?php if ($hasCarousel): ?>
        <div class="work-card-v2__carousel" data-carousel>
            <div class="work-card-v2__slides">
                <?php foreach ($allImages as $i => $imgUrl): ?>
                <div class="work-card-v2__slide">
                    <img src="<?= h($imgUrl) ?>" alt="<?= h($work['title']) ?>" loading="lazy">
                </div>
                <?php endforeach; ?>
                <?php if ($hasMovie): ?>
                <div class="work-card-v2__slide work-card-v2__slide--video">
                    <iframe src="<?= h($directMovieUrl) ?>" class="work-card-v2__video-iframe" loading="lazy" allowfullscreen allow="autoplay" scrolling="no"></iframe>
                </div>
                <?php endif; ?>
            </div>
            <button class="work-card-v2__arrow work-card-v2__arrow--prev" type="button" data-carousel-prev aria-label="前の画像">&lsaquo;</button>
            <button class="work-card-v2__arrow work-card-v2__arrow--next" type="button" data-carousel-next aria-label="次の画像">&rsaquo;</button>
            <div class="work-card-v2__dots">
                <?php for ($i = 0; $i < $totalSlides; $i++): ?>
                <span class="work-card-v2__dot<?= $i === 0 ? ' is-active' : '' ?><?= ($hasMovie && $i === $totalSlides - 1) ? ' work-card-v2__dot--video' : '' ?>" data-carousel-dot="<?= $i ?>"></span>
                <?php endfor; ?>
            </div>
        </div>
        <?php elseif (!empty($allImages)): ?>
        <div class="work-card-v2__single-image">
            <img src="<?= h($allImages[0]) ?>" alt="<?= h($work['title']) ?>" loading="lazy">
        </div>
        <?php else: ?>
        <div class="work-card-v2__placeholder"></div>
        <?php endif; ?>
    </div>
    <div class="work-card-v2__info">
        <h3 class="work-card-v2__title"><?= h($work['title']) ?></h3>
        <?php if (!empty($work['label'])): ?>
            <p class="work-card-v2__description"><?= h($work['label']) ?></p>
        <?php endif; ?>
        <div class="work-card-v2__meta">
            <?php if (!empty($work['review_average']) && !empty($work['review_count'])): ?>
            <div class="work-card-v2__rating">
                <span class="work-card-v2__stars" style="--rating: <?= number_format((float)$work['review_average'], 1) ?>;">★★★★★</span>
                <span class="work-card-v2__review-score"><?= number_format((float)$work['review_average'], 2) ?></span>
                <span class="work-card-v2__review-count">(<?= (int)$work['review_count'] ?>件)</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($work['release_date'])): ?>
                <p class="work-card-v2__date"><?= h($work['release_date']) ?></p>
            <?php endif; ?>
        </div>
        <?php if (!empty($work['price']) && !empty($work['list_price']) && (int)$work['price'] < (int)$work['list_price']): ?>
        <div class="work-card-v2__sale">
            <?php $discountRate = round((1 - (int)$work['price'] / (int)$work['list_price']) * 100); ?>
            <?php
                $saleLabel = 'セール中 ' . $discountRate . '%OFF';
                if (!empty($work['sale_end_at'])) {
                    $saleLabel .= ' ' . date('n/j', strtotime($work['sale_end_at'])) . 'まで！';
                }
            ?>
            <span class="work-card-v2__sale-badge"><?= h($saleLabel) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($work['affiliate_url'])): ?>
        <a class="work-card-v2__cta" href="<?= h($work['affiliate_url']) ?>" target="_blank" rel="nofollow noopener">続きを見る &rarr;</a>
        <?php endif; ?>
    </div>
</div>
