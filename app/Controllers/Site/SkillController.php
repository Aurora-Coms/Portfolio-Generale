<?php
/**
 * Compétences, regroupées par thème puis par sous-catégorie, avec un niveau
 * de 1 à 5 (ou sans niveau pour le savoir-être et les atouts).
 */

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Models\SkillTheme;

final class SkillController extends SiteController
{
    public function index(): void
    {
        $this->render('site/skills', [
            'themes' => SkillTheme::tree(),
            'meta'   => [
                'title'       => 'Compétences',
                'description' => 'Compétences d\'Alexandre Derbez : réseaux, systèmes, cybersécurité, développement Lua et web, électronique, langues et savoir-être, avec un niveau auto-évalué.',
                'canonical'   => '/competences',
            ],
        ]);
    }
}
