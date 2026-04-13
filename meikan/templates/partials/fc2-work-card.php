<li class="fc2-work-card" data-work-id="<?= (int)$work['id'] ?>">
    <div class="fc2-work-card__rank <?= $rank <= 3 ? 'fc2-work-card__rank--top' : '' ?>">
        <?= $rank ?>
    </div>
    <div class="fc2-work-card__media">
        <?php $fc2Link = !empty($work['affiliate_url']) ? $work['affiliate_url'] : 'https://adult.contents.fc2.com/article/' . h($work['cid']) . '/'; ?>
        <a href="<?= h($fc2Link) ?>" target="_blank" rel="nofollow noopener">
            <?php if (!empty($work['thumbnail_url'])): ?>
                <img src="<?= h($work['thumbnail_url']) ?>" alt="FC2-PPV-<?= h($work['cid']) ?>" loading="lazy">
            <?php else: ?>
                <div class="fc2-work-card__no-image">NO IMAGE</div>
            <?php endif; ?>
        </a>
    </div>
    <div class="fc2-work-card__body">
        <a class="fc2-work-card__cid-num" href="<?= h($fc2Link) ?>" target="_blank" rel="nofollow noopener">
            <?= h($work['cid']) ?>
        </a>
        <div class="fc2-work-card__actions">
            <span class="fc2-work-card__vote-count"><?= (int)$work['vote_count'] ?>票</span>
            <button class="fc2-action-btn fc2-fav-btn" data-cid="<?= h($work['cid']) ?>" aria-label="お気に入り" title="お気に入り">&#x2606;</button>
            <button class="fc2-action-btn fc2-copy-btn" data-cid="<?= h($work['cid']) ?>" aria-label="番号をコピー" title="番号をコピー">&#x1F4CB;</button>
            <button class="fc2-action-btn fc2-vote-btn<?= $voted ? ' is-voted' : '' ?>" data-work-id="<?= (int)$work['id'] ?>" <?= $voted ? 'disabled' : '' ?> aria-label="いいね" title="いいね">&#x1F44D;</button>
        </div>
    </div>
</li>
