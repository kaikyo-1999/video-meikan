<!DOCTYPE html>
<html lang="ja">
<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-XP1BJTKX3S"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-XP1BJTKX3S');
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? SITE_TITLE . ' | ' . SITE_NAME) ?></title>
    <meta name="description" content="<?= h($metaDescription ?? SITE_DESCRIPTION) ?>">
    <?php if (!empty($noindex)): ?><meta name="robots" content="noindex"><?php endif; ?>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('favicon-32.png') ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= asset('favicon.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('apple-touch-icon.png') ?>">
    <link rel="canonical" href="<?= h($canonical ?? currentFullUrl()) ?>">
    <!-- OGP -->
    <meta property="og:title" content="<?= h($pageTitle ?? SITE_TITLE . ' | ' . SITE_NAME) ?>">
    <meta property="og:description" content="<?= h($metaDescription ?? SITE_DESCRIPTION) ?>">
    <meta property="og:url" content="<?= h($canonical ?? currentFullUrl()) ?>">
    <meta property="og:type" content="<?= h($ogType ?? 'website') ?>">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <?php if (!empty($ogImage)): ?>
    <meta property="og:image" content="<?= h($ogImage) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <link rel="preconnect" href="https://pics.dmm.co.jp" crossorigin>
    <link rel="dns-prefetch" href="https://pics.dmm.co.jp">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <?php if (!empty($jsonLd)): ?>
    <?= jsonLd($jsonLd) ?>
    <?php endif; ?>
    <?= jsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => SITE_NAME,
        'url' => fullUrl(),
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => fullUrl('meikan/') . '?q={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ]) ?>

</head>
<body>
    <?php require TEMPLATE_DIR . '/partials/header.php'; ?>

    <main class="main">
        <div class="container">
            <?php if (!empty($breadcrumbs)): ?>
                <?php require TEMPLATE_DIR . '/partials/breadcrumb.php'; ?>
            <?php endif; ?>

            <?= $content ?>
        </div>
    </main>

    <?php require TEMPLATE_DIR . '/partials/footer.php'; ?>

    <script>var BASE_URL = '<?= url() ?>';</script>
    <script src="<?= asset('js/app.js') ?>" defer></script>
    <?php if (!empty($genre) || !empty($works)): ?>
    <script src="<?= asset('js/genre.js') ?>" defer></script>
    <?php endif; ?>
    <script src="<?= asset('js/carousel.js') ?>" defer></script>
    <?php if (!empty($fc2Page)): ?>
    <script src="<?= asset('js/fc2.js') ?>" defer></script>
    <?php endif; ?>
</body>
</html>
