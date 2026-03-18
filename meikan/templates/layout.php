<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle ?? SITE_TITLE . ' | ' . SITE_NAME) ?></title>
    <meta name="description" content="<?= h($metaDescription ?? SITE_DESCRIPTION) ?>">
    <link rel="canonical" href="<?= h($canonical ?? fullUrl()) ?>">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <?php if (!empty($jsonLd)): ?>
    <?= jsonLd($jsonLd) ?>
    <?php endif; ?>
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

    <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
