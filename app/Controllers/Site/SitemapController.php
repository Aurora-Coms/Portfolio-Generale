<?php
/**
 * Plan du site (sitemap.xml) et consignes aux robots (robots.txt).
 */

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Models\Project;

final class SitemapController extends SiteController
{
    /** Pages fixes du site public. */
    private const PAGES = [
        '/', '/projets/jeux', '/projets/techniques', '/parcours',
        '/competences', '/a-propos', '/contact', '/mentions-legales',
    ];

    public function index(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (self::PAGES as $path) {
            $xml .= '  <url><loc>' . self::xml(abs_url($path)) . '</loc></url>' . "\n";
        }
        foreach (['game', 'tech'] as $section) {
            foreach (Project::published($section) as $project) {
                $xml .= '  <url><loc>' . self::xml(abs_url('/projet/' . $project['slug'])) . '</loc>';
                $updated = date_create_immutable((string) ($project['updated_at'] ?? ''));
                if ($updated !== false && (string) ($project['updated_at'] ?? '') !== '') {
                    $xml .= '<lastmod>' . $updated->format('Y-m-d') . '</lastmod>';
                }
                $xml .= '</url>' . "\n";
            }
        }
        $xml .= '</urlset>' . "\n";

        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo $xml;
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo "User-agent: *\n"
           . "Disallow: /admin\n"
           . "Disallow: /setup-admin.php\n"
           . "\n"
           . 'Sitemap: ' . abs_url('/sitemap.xml') . "\n";
    }

    /** Échappement pour un contenu XML. */
    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
