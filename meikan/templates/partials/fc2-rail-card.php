<?php
/**
 * TOP の FC2 横スクロール rail 用コンパクトカード。
 * 必須変数: $work (id, cid, thumbnail_url, vote_count, affiliate_url)
 *           $rank (1始まり)
 */
$fc2Link = !empty($work['affiliate_url'])
    ? $work['affiliate_url']
    : 'https://adult.contents.fc2.com/article/' . h($work['cid']) . '/';
?>
<a class="fc2-rail-card" href="<?= h($fc2Link) ?>" target="_blank" rel="nofollow noopener"
   data-fc2-cid="<?= h($work['cid']) ?>" data-fc2-link-type="fc2_rail">
    <div class="fc2-rail-card__rank<?= $rank <= 3 ? ' fc2-rail-card__rank--top' : '' ?>"><?= (int)$rank ?></div>
    <div class="fc2-rail-card__media">
        <?php if (!empty($work['thumbnail_url'])): ?>
            <?php
            // LCP 最適化: rank 1 は最優先取得 + lazy 解除、rank 2-3 は lazy 解除のみ。
            // 横スクロール rail で初期表示に入る範囲のみ eager にする。
            $imgLoading = $rank <= 3 ? 'eager' : 'lazy';
            $imgFetchPriority = $rank === 1 ? ' fetchpriority="high"' : '';
            ?>
            <img src="<?= h($work['thumbnail_url']) ?>" alt="FC2-PPV-<?= h($work['cid']) ?>" width="220" height="220" loading="<?= $imgLoading ?>" decoding="async"<?= $imgFetchPriority ?>>
        <?php else: ?>
            <div class="fc2-rail-card__no-image">NO IMAGE</div>
        <?php endif; ?>
    </div>
    <div class="fc2-rail-card__body">
        <span class="fc2-rail-card__cid"><?= h($work['cid']) ?></span>
        <?php $voteCount = (int)($work['vote_count'] ?? 0); ?>
        <?php if ($voteCount > 0): ?>
            <span class="fc2-rail-card__votes">👍 <?= $voteCount ?>票</span>
        <?php endif; ?>
    </div>
</a>
