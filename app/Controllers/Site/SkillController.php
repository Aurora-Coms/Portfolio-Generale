<?php
/**
 * Compétences, regroupées par catégorie, avec un niveau de 1 à 5.
 */

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Models\SkillCategory;

final class SkillController extends SiteController
{
    public function index(): void
    {
        $this->render('site/skills', [
            'categories' => SkillCategory::withSkills(),
            'meta'       => [
                'title'       => 'Compétences',
                'description' => 'Compétences d\'Alexandre Derbez : réseaux, systèmes Windows et Linux, cybersécurité, scripting Lua et outils, avec un niveau auto-évalué de 1 à 5.',
                'canonical'   => '/competences',
            ],
        ]);
    }
}
