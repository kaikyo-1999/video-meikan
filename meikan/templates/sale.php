<?php
// 現在のクエリパラメータを保持してページ/ソート/検索遷移する補助
$currentParams = array_filter([
    'q' => $query ?? '',
    'sort' => $sort ?? '',
]);

function saleUrl(array $params): string {
    $merged = array_merge($GLOBALS['__saleParams'] ?? [], $params);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return $merged ? '?' . http_build_query($merged) : '';
}
$GLOBALS['__saleParams'] = $currentParams;

$worksOffset = $pagination['offset'] ?? 0;
$insertionMode = 'sale';
$workIndex = 0;

$sortLabels = [
    '' => 'おすすめ順',
    'discount' => '割引率順',
    'ending_soon' => '終了が近い順',
    'price_low' => '価格安い順',
    'price_high' => '価格高い順',
    'newest' => '新着順',
];
?>

<header class="sale-header">
    <h1 class="sale-header__title">FANZA セール中の作品一覧</h1>
    <p class="sale-header__lead">FANZA で現在セール中（割引価格・期間限定）の作品を一覧で表示しています。タイトルで検索したり、割引率や終了日でソートしてお目当ての一本を見つけてください。</p>
</header>

<!-- 検索バー -->
<form method="get" action="<?= h(url('sale/')) ?>" class="search-bar" role="search">
    <div class="search-bar__inner">
        <input type="text" name="q" class="search-bar__input"
               value="<?= h($query) ?>" placeholder="セール作品をタイトルで検索..." aria-label="タイトル検索">
        <?php if ($sort !== ''): ?>
            <input type="hidden" name="sort" value="<?= h($sort) ?>">
        <?php endif; ?>
        <button type="submit" class="search-bar__btn">検索</button>
    </div>
</form>

<div class="sort-header">
    <p class="sort-header__count">対象作品：<strong><?= number_format($totalWorks) ?></strong> 件
        <?php if ($query !== ''): ?>
            <span class="sort-header__query">「<?= h($query) ?>」</span>
            <a href="<?= h(url('sale/')) ?>" class="sort-header__clear">クリア</a>
        <?php endif; ?>
    </p>
    <div class="sort-header__tabs" role="radiogroup" aria-label="並び替え">
        <?php foreach ($sortLabels as $key => $label): ?>
            <a href="<?= h(saleUrl(['sort' => $key, 'page' => null])) ?>"
               class="sort-header__tab<?= $sort === $key ? ' is-active' : '' ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($works)): ?>
    <p class="work-controls__no-results">
        <?= $query !== '' ? '「' . h($query) . '」に該当するセール作品が見つかりませんでした。' : '現在セール中の作品はありません。' ?>
    </p>
<?php else: ?>
    <div class="work-list work-list--v2">
        <?php foreach ($works as $work): ?>
            <?php require TEMPLATE_DIR . '/partials/work-card-v2.php'; ?>
        <?php endforeach; ?>
    </div>

    <?php // ページネーション (sort, q を保持) ?>
    <?php if ($pagination['total_pages'] > 1): ?>
    <nav class="pagination" aria-label="ページナビゲーション">
        <?php if ($pagination['current_page'] > 1): ?>
            <a href="<?= h(saleUrl(['page' => $pagination['current_page'] - 1])) ?>" class="pagination__btn">&larr; 前へ</a>
        <?php else: ?>
            <span class="pagination__btn pagination__btn--disabled">&larr; 前へ</span>
        <?php endif; ?>

        <?php
        $start = max(1, $pagination['current_page'] - 2);
        $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
        if ($start > 1): ?>
            <a href="<?= h(saleUrl(['page' => 1])) ?>" class="pagination__num">1</a>
            <?php if ($start > 2): ?><span class="pagination__dots">&hellip;</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
            <?php if ($i === $pagination['current_page']): ?>
                <span class="pagination__num pagination__num--active"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= h(saleUrl(['page' => $i])) ?>" class="pagination__num"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($end < $pagination['total_pages']): ?>
            <?php if ($end < $pagination['total_pages'] - 1): ?><span class="pagination__dots">&hellip;</span><?php endif; ?>
            <a href="<?= h(saleUrl(['page' => $pagination['total_pages']])) ?>" class="pagination__num"><?= $pagination['total_pages'] ?></a>
        <?php endif; ?>

        <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
            <a href="<?= h(saleUrl(['page' => $pagination['current_page'] + 1])) ?>" class="pagination__btn">次へ &rarr;</a>
        <?php else: ?>
            <span class="pagination__btn pagination__btn--disabled">次へ &rarr;</span>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
<?php endif; ?>
