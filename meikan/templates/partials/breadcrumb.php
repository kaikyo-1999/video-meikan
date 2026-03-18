<?php
$bcJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [],
];
?>
<nav class="breadcrumb" aria-label="パンくずリスト">
    <?php foreach ($breadcrumbs as $i => $bc): ?>
        <?php
        $pos = $i + 1;
        $bcJsonLd['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $pos,
            'name' => $bc['label'],
            'item' => $bc['url'] ? fullUrl($bc['url']) : null,
        ];
        ?>
        <?php if ($i > 0): ?><span class="breadcrumb__sep">&rsaquo;</span><?php endif; ?>
        <?php if (!empty($bc['url'])): ?>
            <a href="<?= h(url($bc['url'])) ?>" class="breadcrumb__link"><?= h($bc['label']) ?></a>
        <?php else: ?>
            <span class="breadcrumb__current"><?= h($bc['label']) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
<?= jsonLd($bcJsonLd) ?>
