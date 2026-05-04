<?= '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL ?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($sitemaps as $s): ?>
    <sitemap>
        <loc><?= h($s['loc']) ?></loc>
<?php if (!empty($s['lastmod'])): ?>
        <lastmod><?= h($s['lastmod']) ?></lastmod>
<?php endif; ?>
    </sitemap>
<?php endforeach; ?>
</sitemapindex>
