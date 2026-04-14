<div class="fc2-ranking">
    <h1 class="page-title">FC2おすすめの7桁｜<?= date('Y') ?>年<?= date('n') ?>月最新ランキング</h1>

    <section class="fc2-about">
        <h2 class="fc2-about__title">FC2 7桁の数字とは？</h2>
        <p class="fc2-about__text">FC2 PPVの動画を識別する<strong>7桁のID</strong>です。SNSや掲示板で作品を共有する際によく使われます。</p>
    </section>

    <section class="fc2-about">
        <h2 class="fc2-about__title">FC2 7桁の数字をどうやって見る？</h2>
        <p class="fc2-about__text">番号をそのままネットで検索するだけで作品を見つけられます。</p>
    </section>

    <h2 class="fc2-ranking__section-title">FC2 7桁の数字ランキング</h2>

    <div class="fc2-ranking__toolbar">
        <div class="fc2-ranking__periods">
            <?php foreach ($periodLabels as $key => $label): ?>
                <a href="<?= url('fc2/') ?>?period=<?= h($key) ?>"
                   class="fc2-ranking__period-btn<?= $period === $key ? ' is-active' : '' ?>">
                    <?= h($label) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <a href="<?= url('fc2/submit/') ?>" class="fc2-ranking__submit-btn">＋ 作品を投稿</a>
    </div>

    <?php if (empty($works)): ?>
        <p class="fc2-ranking__empty">まだ登録された作品がありません。<a href="<?= url('fc2/submit/') ?>">最初の作品を投稿してみましょう</a>。</p>
    <?php else: ?>
        <ol class="fc2-work-list">
            <?php foreach ($works as $i => $work): ?>
                <?php
                $rank  = $rankOffset + $i + 1;
                $voted = isset($votedSet[$work['id']]);
                ?>
                <?php require TEMPLATE_DIR . '/partials/fc2-work-card.php'; ?>
            <?php endforeach; ?>
        </ol>

        <?php if ($pagination['total_pages'] > 1): ?>
        <?php $pageBase = url('fc2/') . '?period=' . urlencode($period) . '&page='; ?>
        <nav class="pagination" aria-label="ページナビゲーション">
            <?php if ($pagination['current_page'] > 1): ?>
                <a href="<?= h($pageBase . ($pagination['current_page'] - 1)) ?>" class="pagination__btn">&larr; 前へ</a>
            <?php else: ?>
                <span class="pagination__btn pagination__btn--disabled">&larr; 前へ</span>
            <?php endif; ?>

            <?php
            $ps = max(1, $pagination['current_page'] - 2);
            $pe = min($pagination['total_pages'], $pagination['current_page'] + 2);
            if ($ps > 1): ?>
                <a href="<?= h($pageBase . 1) ?>" class="pagination__num">1</a>
                <?php if ($ps > 2): ?><span class="pagination__dots">&hellip;</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($pi = $ps; $pi <= $pe; $pi++): ?>
                <?php if ($pi === $pagination['current_page']): ?>
                    <span class="pagination__num pagination__num--active"><?= $pi ?></span>
                <?php else: ?>
                    <a href="<?= h($pageBase . $pi) ?>" class="pagination__num"><?= $pi ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($pe < $pagination['total_pages']): ?>
                <?php if ($pe < $pagination['total_pages'] - 1): ?><span class="pagination__dots">&hellip;</span><?php endif; ?>
                <a href="<?= h($pageBase . $pagination['total_pages']) ?>" class="pagination__num"><?= $pagination['total_pages'] ?></a>
            <?php endif; ?>

            <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                <a href="<?= h($pageBase . ($pagination['current_page'] + 1)) ?>" class="pagination__btn">次へ &rarr;</a>
            <?php else: ?>
                <span class="pagination__btn pagination__btn--disabled">次へ &rarr;</span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
