<?php
// SEO3: Dynamic Sitemap
header('Content-Type: application/xml; charset=utf-8');
$domain = 'https://vpbotn.ir';
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Static pages
$pages = [
    ['loc' => '/', 'priority' => '1.0', 'freq' => 'daily'],
    ['loc' => '/contax/promo/', 'priority' => '0.9', 'freq' => 'weekly'],
    ['loc' => '/contax/promo/video.php', 'priority' => '0.7', 'freq' => 'monthly'],
    ['loc' => '/contax/login', 'priority' => '0.5', 'freq' => 'monthly'],
];

foreach ($pages as $p) {
    echo "  <url>\n";
    echo "    <loc>{$domain}{$p['loc']}</loc>\n";
    echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "    <changefreq>{$p['freq']}</changefreq>\n";
    echo "    <priority>{$p['priority']}</priority>\n";
    echo "  </url>\n";
}

// Dynamic: plans as products
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/core/Database.php';
    $pdo = Database::getConnection();
    $stmt = $pdo->query("SELECT id, name, updated_at FROM plans WHERE is_active=1 LIMIT 100");
    $plans = $stmt->fetchAll();
    foreach ($plans as $plan) {
        $slug = urlencode(str_replace(' ', '-', $plan['name']));
        echo "  <url>\n";
        echo "    <loc>{$domain}/#plan-{$plan['id']}</loc>\n";
        echo "    <lastmod>" . date('Y-m-d', strtotime($plan['updated_at'] ?? 'now')) . "</lastmod>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.8</priority>\n";
        echo "  </url>\n";
    }
} catch (Throwable $e) {}

echo '</urlset>';
