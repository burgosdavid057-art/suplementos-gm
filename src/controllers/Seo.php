<?php
declare(strict_types=1);

class Seo {
    public static function sitemap(): void {
        header('Content-Type: application/xml; charset=utf-8');
        $base = rtrim(env('APP_URL', 'http://localhost') ?? '', '/');

        $urls = [
            ['loc' => $base . '/',           'priority' => '1.0', 'change' => 'weekly'],
            ['loc' => $base . '/productos',  'priority' => '0.9', 'change' => 'daily'],
        ];
        foreach (Category::all() as $c) {
            $urls[] = [
                'loc'      => $base . '/productos?categoria=' . $c['slug'],
                'priority' => '0.8',
                'change'   => 'weekly',
                'lastmod'  => substr($c['updated_at'], 0, 10),
            ];
        }
        $stmt = db()->query("SELECT slug, updated_at FROM products WHERE active = 1");
        foreach ($stmt as $p) {
            $urls[] = [
                'loc'      => $base . '/productos/' . $p['slug'],
                'priority' => '0.7',
                'change'   => 'weekly',
                'lastmod'  => substr($p['updated_at'], 0, 10),
            ];
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars($u['loc']) . "</loc>\n";
            if (!empty($u['lastmod'])) echo "    <lastmod>{$u['lastmod']}</lastmod>\n";
            echo "    <changefreq>{$u['change']}</changefreq>\n";
            echo "    <priority>{$u['priority']}</priority>\n";
            echo "  </url>\n";
        }
        echo '</urlset>';
    }

    public static function robots(): void {
        header('Content-Type: text/plain; charset=utf-8');
        $base = rtrim(env('APP_URL', 'http://localhost') ?? '', '/');
        echo "User-Agent: *\n";
        echo "Allow: /\n";
        echo "Allow: /productos\n";
        echo "Allow: /productos/*\n";
        echo "Disallow: /admin\n";
        echo "Disallow: /admin/*\n";
        echo "Disallow: /carrito\n";
        echo "Disallow: /checkout\n";
        echo "\n";
        echo "Sitemap: $base/sitemap.xml\n";
    }
}
