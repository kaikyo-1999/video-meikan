<li class="fc2-work-card" data-work-id="<?= (int)$work['id'] ?>">
    <div class="fc2-work-card__rank <?= $rank <= 3 ? 'fc2-work-card__rank--top' : '' ?>">
        <?= $rank ?>
    </div>
    <div class="fc2-work-card__media">
        <?php $fc2Link = !empty($work['affiliate_url']) ? $work['affiliate_url'] : 'https://adult.contents.fc2.com/article/' . h($work['cid']) . '/'; ?>
        <a href="<?= h($fc2Link) ?>" target="_blank" rel="nofollow noopener">
            <?php if (!empty($work['thumbnail_url'])): ?>
                <img src="<?= h($work['thumbnail_url']) ?>" alt="<?= h($work['title']) ?>" loading="lazy">
            <?php else: ?>
                <div class="fc2-work-card__no-image">NO IMAGE</div>
            <?php endif; ?>
        </a>
    </div>
    <div class="fc2-work-card__info">
        <a href="<?= h($fc2Link) ?>" target="_blank" rel="nofollow noopener" class="fc2-work-card__title">
            <?= h($work['title']) ?>
        </a>
        <div class="fc2-work-card__meta">
            <?php if (!empty($work['duration'])): ?>
                <span class="fc2-work-card__duration"><?= (int)$work['duration'] ?>分</span>
            <?php endif; ?>
            <?php if (!empty($work['price'])): ?>
                <span class="fc2-work-card__price">¥<?= number_format((int)$work['price']) ?></span>
            <?php endif; ?>
            <span class="fc2-work-card__cid">CID: <?= h($work['cid']) ?></span>
        </div>
    </div>
    <div class="fc2-work-card__vote">
        <button
            class="fc2-vote-btn<?= $voted ? ' is-voted' : '' ?>"
            data-work-id="<?= (int)$work['id'] ?>"
            <?= $voted ? 'disabled' : '' ?>
            aria-label="投票する"
        >
            <span class="fc2-vote-btn__icon">&#x2665;</span>
            <span class="fc2-vote-btn__count"><?= (int)$work['vote_count'] ?></span>
        </button>
        <?php if ($voted): ?>
            <span class="fc2-vote-btn__label">投票済み</span>
        <?php endif; ?>
    </div>
</li>
