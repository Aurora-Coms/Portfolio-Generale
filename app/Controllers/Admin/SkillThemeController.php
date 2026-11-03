<?php
/**
 * Administration des thèmes de compétences (Informatique & réseaux,
 * Développement, Langues…) : le niveau le plus haut de la page
 * « Compétences » (thème > sous-catégories > compétences).
 *
 * Routes (Router::resource('/admin/competences/themes')) : index, create,
 * store, edit, update, destroy. Un thème qui contient encore des
 * sous-catégories ne peut pas être supprimé (SkillTheme::delete renvoie false).
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminController;
use App\Core\Slug;
use App\Core\Validator;
use App\Models\SkillTheme;

final class SkillThemeController extends AdminController
{
    /**
     * Icônes proposées (noms du helper icon()) => libellé lisible.
     * Liste blanche partagée avec les sous-catégories (SkillCategoryController::ICONS).
     */
    public const ICONS = [
        'network'  => 'Réseau',
        'server'   => 'Serveur',
        'code'     => 'Code',
        'shield'   => 'Sécurité',
        'tools'    => 'Outils',
        'chip'     => 'Électronique',
        'terminal' => 'Terminal',
        'globe'    => 'Langues',
        'user'     => 'Personne',
        'star'     => 'Étoile',
        'book'     => 'Livre',
        'gamepad'  => 'Jeu vidéo',
        'bike'     => 'Vélo',
        'music'    => 'Musique',
    ];

    /** Longueur maximale du slug (colonne VARCHAR(90)). */
    private const SLUG_MAX = 90;

    public function index(): void
    {
        $this->render('admin/skill-themes/index', [
            'themes' => SkillTheme::all(),
            'meta'   => ['title' => 'Thèmes de compétences'],
        ]);
    }

    public function create(): void
    {
        $this->form(null, ['icon' => 'code', 'position' => '0']);
    }

    public function store(): void
    {
        [$data, $errors] = $this->validate($_POST, null);
        if ($errors !== []) {
            $this->form(null, $_POST, $errors, 422);
            return;
        }
        SkillTheme::create($data);
        $this->success('/admin/competences/themes', 'Thème « ' . $data['name'] . ' » créé.');
    }

    public function edit(string $id): void
    {
        $theme = $this->findOr404($id);
        $this->form($theme, [
            'name'     => (string) $theme['name'],
            'icon'     => (string) ($theme['icon'] ?? ''),
            'position' => (string) (int) $theme['position'],
        ]);
    }

    public function update(string $id): void
    {
        $theme = $this->findOr404($id);
        [$data, $errors] = $this->validate($_POST, (int) $theme['id']);
        if ($errors !== []) {
            $this->form($theme, $_POST, $errors, 422);
            return;
        }
        SkillTheme::update((int) $theme['id'], $data);
        $this->success('/admin/competences/themes', 'Thème « ' . $data['name'] . ' » enregistré.');
    }

    public function destroy(string $id): void
    {
        $theme = $this->findOr404($id);
        if (!SkillTheme::delete((int) $theme['id'])) {
            $this->error(
                '/admin/competences/themes',
                'Impossible de supprimer « ' . $theme['name'] . ' » : ce thème contient encore des sous-catégories. '
                . 'Rangez-les dans un autre thème ou supprimez-les d\'abord.'
            );
        }
        $this->success('/admin/competences/themes', 'Thème « ' . $theme['name'] . ' » supprimé.');
    }

    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function findOr404(string $id): array
    {
        $theme = SkillTheme::find((int) $id);
        if ($theme === null) {
            $this->notFound();
        }
        return $theme;
    }

    /**
     * @param array<string, mixed>|null $theme
     * @param array<string, mixed>      $old
     * @param array<string, string>     $errors
     */
    private function form(?array $theme, array $old, array $errors = [], int $status = 200): void
    {
        $isNew = $theme === null;
        $this->render('admin/skill-themes/form', [
            'theme'  => $theme,
            'old'    => $old,
            'errors' => $errors,
            'icons'  => self::ICONS,
            'action' => $isNew ? '/admin/competences/themes' : '/admin/competences/themes/' . (int) $theme['id'],
            'meta'   => ['title' => $isNew ? 'Nouveau thème de compétences' : 'Modifier un thème de compétences'],
        ], $status);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validate(array $input, ?int $exceptId): array
    {
        $v = new Validator($input);
        $name = $v->string('name', 'Nom', required: true, max: 80);
        $icon = $v->in('icon', 'Icône', array_keys(self::ICONS), required: false);
        if ($icon === null && !isset($v->errors()['icon'])) {
            $v->addError('icon', 'Choisissez une icône.');
        }
        $position = $v->int('position', 'Ordre', required: false, min: 0, max: 9999);

        $slug = null;
        if ($name !== null) {
            $slug = $this->uniqueSlug($name, $exceptId);
            if ($slug === null) {
                $v->addError('name', 'Le nom doit contenir au moins une lettre ou un chiffre.');
            }
        }

        return [[
            'name'     => $name,
            'slug'     => $slug,
            'icon'     => $icon,
            'position' => $position ?? 0,
        ], $v->errors()];
    }

    /** Slug unique construit depuis le nom : « langues », sinon « langues-2 », « langues-3 »… */
    private function uniqueSlug(string $name, ?int $exceptId): ?string
    {
        $base = Slug::make($name, self::SLUG_MAX - 4);
        if ($base === '') {
            return null;
        }
        $slug = $base;
        for ($n = 2; SkillTheme::slugExists($slug, $exceptId); $n++) {
            $slug = $base . '-' . $n;
        }
        return $slug;
    }
}
