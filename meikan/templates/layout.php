<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? SITE_TITLE . ' | ' . SITE_NAME) ?></title>
    <meta name="description" content="<?= h($metaDescription ?? SITE_DESCRIPTION) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('favicon-32.png') ?>">
    <link rel="icon" type="image/png" sizes="64x64" href="<?= asset('favicon.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('apple-touch-icon.png') ?>">
    <link rel="canonical" href="<?= h($canonical ?? fullUrl()) ?>">
    <!-- OGP -->
    <meta property="og:title" content="<?= h($pageTitle ?? SITE_TITLE . ' | ' . SITE_NAME) ?>">
    <meta property="og:description" content="<?= h($metaDescription ?? SITE_DESCRIPTION) ?>">
    <meta property="og:url" content="<?= h($canonical ?? fullUrl()) ?>">
    <meta property="og:type" content="<?= h($ogType ?? 'website') ?>">
    <meta property="og:site_name" content="<?= h(SITE_NAME) ?>">
    <?php if (!empty($ogImage)): ?>
    <meta property="og:image" content="<?= h($ogImage) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <?php if (!empty($jsonLd)): ?>
    <?= jsonLd($jsonLd) ?>
    <?php endif; ?>
    <?= jsonLd(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => SITE_NAME, 'url' => fullUrl()]) ?>

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
    <script src="<?= asset('js/app.js') ?>"></script>
    <?php if (!empty($genre)): ?>
    <script src="<?= asset('js/genre.js') ?>"></script>
    <?php endif; ?>
</body>
</html>
