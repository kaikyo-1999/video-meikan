<?php
/**
 * 汎用 女優カード インライン挿入
 *
 * 入力:
 *   $inlineTitle  string  セクションタイトル（例: "波多野結衣が好きな人にオススメ"）
 *   $inlineItems  array   表示する女優レコード配列
 *   $inlineCtaUrl ?string 任意。タイトル末尾の「もっと見る」リンク先
 *   $inlineCtaLabel ?string 任意。CTAラベル（デフォルト "もっと見る"）
 */
$inlineCtaUrl = $inlineCtaUrl ?? null;
$inlineCtaLabel = $inlineCtaLabel ?? 'もっと見る';
?>
<div class="similar-inline">
    <p class="similar-inline__title">
        <?= h($inlineTitle) ?>
        <?php if ($inlineCtaUrl): ?>
            <a href="<?= h($inlineCtaUrl) ?>" class="similar-inline__cta"><?= h($inlineCtaLabel) ?> &rsaquo;</a>
        <?php endif; ?>
    </p>
    <div class="similar-inline__scroll">
        <?php foreach ($inlineItems as $item): ?>
            <a href="<?= h(url($item['slug'] . '/')) ?>" class="similar-inline__item">
                <div class="similar-inline__image">
                    <?php if (!empty($item['thumbnail_url'])): ?>
                        <img src="<?= h($item['thumbnail_url']) ?>" alt="<?= h($item['name']) ?>" width="300" height="300" loading="lazy">
                    <?php else: ?>
                        <div class="similar-inline__placeholder"></div>
                    <?php endif; ?>
                </div>
                <span class="similar-inline__name"><?= h($item['name']) ?><?php if (!empty($item['birthday'])): ?><span class="similar-inline__age">（<?= (new DateTime($item['birthday']))->diff(new DateTime())->y ?>歳）</span><?php endif; ?></span>
                <?php if (!empty($item['bust']) && !empty($item['waist']) && !empty($item['hip'])): ?>
                    <span class="similar-inline__size">B<?= (int)$item['bust'] ?> W<?= (int)$item['waist'] ?> H<?= (int)$item['hip'] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
