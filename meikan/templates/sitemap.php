<?= '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $u): ?>
    <url>
        <loc><?= h($u['loc']) ?></loc>
<?php if (!empty($u['lastmod'])): ?>
        <lastmod><?= h($u['lastmod']) ?></lastmod>
<?php endif; ?>
        <changefreq><?= h($u['changefreq']) ?></changefreq>
        <priority><?= h($u['priority']) ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
