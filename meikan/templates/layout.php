<!DOCTYPE html>
<html lang="ja">
<head>
    <!-- visitor_id 同期初期化（GA4より前に走らせる。お気に入り機能と共有） -->
    <script>
      (function() {
        var KEY = 'av-hakase:user';
        var SCHEMA_VERSION = 1;
        var data = null;
        try { data = JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) {}
        if (!data || typeof data !== 'object') data = {};
        if (data.v !== SCHEMA_VERSION) data.v = SCHEMA_VERSION;
        if (!data.visitor_id) {
          data.visitor_id = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
          });
        }
        if (!data.first_visit_at) data.first_visit_at = new Date().toISOString();
        if (!data.favorites || typeof data.favorites !== 'object') {
          data.favorites = { actresses: [], works: [] };
        }
        try { localStorage.setItem(KEY, JSON.stringify(data)); } catch (e) {}
        window.__AV_HAKASE_VISITOR_ID = data.visitor_id;
      })();
    </script>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-XP1BJTKX3S"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('set', 'user_properties', { visitor_id: window.__AV_HAKASE_VISITOR_ID });
      gtag('config', 'G-XP1BJTKX3S');
    </script>
    <script>
      document.addEventListener('click', function(e) {
        var link = e.target.closest('a[data-fanza-cid]');
        if (!link || typeof gtag === 'undefined') return;
        gtag('event', 'fanza_click', {
          fanza_cid: link.dataset.fanzaCid,
          link_type: link.dataset.fanzaLinkType,
          visitor_id: window.__AV_HAKASE_VISITOR_ID
        });
      });
    </script>
    <script type="module">
      import {onLCP, onCLS, onINP, onFCP, onTTFB} from 'https://unpkg.com/web-vitals@4?module';
      var send = function(metric) {
        if (typeof gtag === 'undefined') return;
        gtag('event', metric.name, {
          value: Math.round(metric.name === 'CLS' ? metric.value * 1000 : metric.value),
          metric_id: metric.id,
          metric_rating: metric.rating,
          page_path: location.pathname
        });
      };
      onLCP(send); onCLS(send); onINP(send); onFCP(send); onTTFB(send);
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
    <link rel="dns-prefetch" href="https://unpkg.com">
    <!-- HTMX (partial swap) + Alpine.js (UI state) — Phase 0.5 -->
    <script defer src="https://unpkg.com/htmx.org@2.0.4"></script>
    <script defer src="https://unpkg.com/alpinejs@3.14.3/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none !important}</style>
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
    <script src="<?= asset('js/favorites.js') ?>" defer></script>
    <script src="<?= asset('js/app.js') ?>" defer></script>
    <script src="<?= asset('js/ads.js') ?>" defer></script>
    <?php if (!empty($genre) || !empty($works)): ?>
    <script src="<?= asset('js/genre.js') ?>" defer></script>
    <?php endif; ?>
    <script src="<?= asset('js/carousel.js') ?>" defer></script>
    <?php if (!empty($fc2Page)): ?>
    <script src="<?= asset('js/fc2.js') ?>" defer></script>
    <?php endif; ?>

</body>
</html>
