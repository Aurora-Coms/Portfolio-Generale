<?php
/**
 * Page d'accueil : présentation, fenêtre de code « profil.lua »,
 * projets mis en avant (filtrables) et aperçu du parcours.
 */

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Request;
use App\Models\Project;
use App\Models\TimelineEntry;

final class HomeController extends SiteController
{
    /** Nombre de projets mis en avant affichés. */
    private const FEATURED = 4;

    /** Nombre d'entrées du parcours affichées en aperçu. */
    private const TIMELINE = 3;

    public function index(): void
    {
        $featured = Project::featured(self::FEATURED);

        // Puces de filtre : catégories des projets affichés, sans doublon.
        $categories = [];
        foreach ($featured as $project) {
            foreach ($project['categories'] as $cat) {
                $slug = (string) $cat['slug'];
                if (!isset($categories[$slug])) {
                    $categories[$slug] = ['name' => (string) $cat['name'], 'slug' => $slug, 'project_count' => 0];
                }
                $categories[$slug]['project_count']++;
            }
        }
        $categories = array_values($categories);

        // ?categorie= (sans JavaScript) : seul un slug présent dans la liste est retenu.
        $requested = Request::query('categorie');
        $active = in_array($requested, array_column($categories, 'slug'), true) ? $requested : null;

        $this->render('site/home', [
            'featured'     => $featured,
            'categories'   => count($categories) > 1 ? $categories : [],
            'active'       => count($categories) > 1 ? $active : null,
            'timeline'     => array_slice(TimelineEntry::published(), 0, self::TIMELINE),
            'cvAvailable'  => $this->cvAvailable(),
            'meta'         => [
                'description' => setting('meta_description'),
                'canonical'   => '/',
            ],
        ]);
    }
}
