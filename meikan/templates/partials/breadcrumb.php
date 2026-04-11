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
        $element = [
            '@type' => 'ListItem',
            'position' => $pos,
            'name' => $bc['label'],
        ];
        $isLast = ($i === count($breadcrumbs) - 1);
        if (!empty($bc['url'])) {
            $element['item'] = fullUrl($bc['url']);
        } elseif ($isLast) {
            $element['item'] = fullUrl(strtok($_SERVER['REQUEST_URI'], '?'));
        } else {
            $element['item'] = fullUrl('');
        }
        $bcJsonLd['itemListElement'][] = $element;
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
