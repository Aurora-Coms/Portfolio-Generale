<?php
/**
 * Projets : listes par section (jeux, projets techniques) et page détail.
 */

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Request;
use App\Models\Category;
use App\Models\Project;

final class ProjectController extends SiteController
{
    /** Réglages d'affichage de chaque section (chemin, textes). */
    private const SECTIONS = [
        'game' => [
            'path'        => '/projets/jeux',
            'title'       => 'Projets de jeux',
            'comment'     => '-- projets/jeux/',
            'lead'        => 'Jeux développés sur Roblox Studio, entièrement scriptés en Lua, objets compris : mécaniques, interfaces, sauvegarde des données et validation côté serveur.',
            'description' => 'Jeux Roblox d\'Alexandre Derbez, entièrement scriptés en Lua : TARDIS, Portail du TVA, Cardverse. Logique client / serveur, sauvegarde et interfaces.',
            'allLabel'    => '*.lua',
            'switch'      => 'jeux/',
        ],
        'tech' => [
            'path'        => '/projets/techniques',
            'title'       => 'Projets techniques',
            'comment'     => '-- projets/techniques/',
            'lead'        => 'Réseaux, systèmes et cybersécurité : projets personnels et travaux pratiques réalisés pendant le BTS CIEL option IR.',
            'description' => 'Projets techniques d\'Alexandre Derbez, étudiant en BTS CIEL option IR : réseaux, systèmes d\'exploitation et cybersécurité.',
            'allLabel'    => '*.*',
            'switch'      => 'techniques/',
        ],
    ];

    /** /projets : la liste par défaut est celle des jeux. */
    public function index(): void
    {
        redirect('/projets/jeux', 301);
    }

    public function games(): void
    {
        $this->listing('game');
    }

    public function tech(): void
    {
        $this->listing('tech');
    }

    public function show(string $slug): void
    {
        $project = Project::findPublishedBySlug($slug);
        if ($project === null) {
            // Projet inconnu ou brouillon : même réponse, rien ne trahit son existence.
            $this->notFound();
        }

        $section = $project['section'] === 'tech' ? 'tech' : 'game';
        $cover   = is_array($project['cover'] ?? null) ? $project['cover'] : null;

        $meta = [
            'title'       => (string) $project['title'],
            'description' => (string) $project['summary'],
            'canonical'   => '/projet/' . $project['slug'],
            'og_type'     => 'article',
        ];
        if ($cover !== null && (string) ($cover['filename'] ?? '') !== '') {
            $meta['og_image']        = upload_url((string) $cover['filename']);
            $meta['og_image_width']  = (int) $cover['width'];
            $meta['og_image_height'] = (int) $cover['height'];
            $meta['og_image_alt']    = trim((string) $cover['alt_text']) !== '' ? (string) $cover['alt_text'] : (string) $project['title'];
        }

        $this->render('site/projects/show', [
            'project'     => $project,
            'section'     => $section,
            'sectionPath' => self::SECTIONS[$section]['path'],
            'sectionName' => self::SECTIONS[$section]['title'],
            'externalUrl' => self::safeUrl($project['external_url'] ?? null),
            'meta'        => $meta,
        ]);
    }

    /**
     * Liste d'une section. Une catégorie inconnue en ?categorie= n'est pas une
     * erreur : la liste complète est affichée, puce « tous » active.
     */
    private function listing(string $section): void
    {
        $config     = self::SECTIONS[$section];
        $categories = Category::forSection($section, true);

        $requested = Request::query('categorie');
        $active    = in_array($requested, array_map(static fn (array $c): string => (string) $c['slug'], $categories), true)
            ? $requested
            : null;

        $other = $section === 'game' ? 'tech' : 'game';

        $this->render('site/projects/index', [
            'section'    => $section,
            'config'     => $config,
            'other'      => self::SECTIONS[$other],
            'sections'   => self::SECTIONS,
            // Liste COMPLÈTE de la section : le filtre actif masque les autres cartes,
            // que le JavaScript peut réafficher sans rechargement.
            'projects'   => Project::published($section),
            'categories' => $categories,
            'active'     => $active,
            'meta'       => [
                'title'       => $config['title'],
                'description' => $config['description'],
                'canonical'   => $config['path'],
            ],
        ]);
    }
}
