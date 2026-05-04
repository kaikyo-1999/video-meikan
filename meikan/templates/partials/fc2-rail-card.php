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
<a class="fc2-rail-card" href="<?= h($fc2Link) ?>" target="_blank" rel="nofollow noopener" data-fc2-cid="<?= h($work['cid']) ?>">
    <div class="fc2-rail-card__rank<?= $rank <= 3 ? ' fc2-rail-card__rank--top' : '' ?>"><?= (int)$rank ?></div>
    <div class="fc2-rail-card__media">
        <?php if (!empty($work['thumbnail_url'])): ?>
            <img src="<?= h($work['thumbnail_url']) ?>" alt="FC2-PPV-<?= h($work['cid']) ?>" width="220" height="124" loading="lazy" decoding="async">
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
