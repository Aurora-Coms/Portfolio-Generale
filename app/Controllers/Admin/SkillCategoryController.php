<?php
/**
 * Administration des sous-catégories de compétences (Réseau, Systèmes,
 * Scripting / Lua…), rangées chacune dans un thème (SkillThemeController).
 *
 * Routes (Router::resource('/admin/competences/categories')) : index, create
 * (?theme=ID présélectionne le thème), store, edit, update, destroy. Une
 * sous-catégorie qui contient encore des compétences ne peut pas être
 * supprimée (SkillCategory::delete renvoie false).
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminController;
use App\Core\Request;
use App\Core\Slug;
use App\Core\Validator;
use App\Models\SkillCategory;
use App\Models\SkillTheme;

final class SkillCategoryController extends AdminController
{
    /** Icônes proposées : même liste blanche que les thèmes (nom du helper icon() => libellé). */
    public const ICONS = SkillThemeController::ICONS;

    /** Longueur maximale du slug (colonne VARCHAR(90)). */
    private const SLUG_MAX = 90;

    public function index(): void
    {
        // Sous-catégories regroupées par thème (thèmes vides compris).
        $byTheme = [];
        foreach (SkillCategory::all() as $category) {
            $byTheme[(int) $category['theme_id']][] = $category;
        }
        $themes = SkillTheme::all();
        foreach ($themes as &$theme) {
            $theme['categories'] = $byTheme[(int) $theme['id']] ?? [];
        }
        unset($theme);

        $this->render('admin/skill-categories/index', [
            'themes' => $themes,
            'meta'   => ['title' => 'Sous-catégories de compétences'],
        ]);
    }

    public function create(): void
    {
        // Thème présélectionné depuis la liste (?theme=ID), s'il existe.
        $raw    = Request::query('theme');
        $preset = preg_match('/^[1-9]\d{0,9}$/', $raw) === 1 ? (int) $raw : 0;
        $this->form(null, [
            'theme_id' => $preset > 0 && SkillTheme::find($preset) !== null ? (string) $preset : '',
            'icon'     => 'code',
            'position' => '0',
        ]);
    }

    public function store(): void
    {
        [$data, $errors] = $this->validate($_POST, null);
        if ($errors !== []) {
            $this->form(null, $_POST, $errors, 422);
            return;
        }
        SkillCategory::create($data);
        $this->success('/admin/competences/categories', 'Sous-catégorie « ' . $data['name'] . ' » créée.');
    }

    public function edit(string $id): void
    {
        $category = $this->findOr404($id);
        $this->form($category, [
            'theme_id' => (string) (int) $category['theme_id'],
            'name'     => (string) $category['name'],
            'icon'     => (string) ($category['icon'] ?? ''),
            'position' => (string) (int) $category['position'],
        ]);
    }

    public function update(string $id): void
    {
        $category = $this->findOr404($id);
        [$data, $errors] = $this->validate($_POST, (int) $category['id']);
        if ($errors !== []) {
            $this->form($category, $_POST, $errors, 422);
            return;
        }
        SkillCategory::update((int) $category['id'], $data);
        $this->success('/admin/competences/categories', 'Sous-catégorie « ' . $data['name'] . ' » enregistrée.');
    }

    public function destroy(string $id): void
    {
        $category = $this->findOr404($id);
        if (!SkillCategory::delete((int) $category['id'])) {
            $this->error(
                '/admin/competences/categories',
                'Impossible de supprimer « ' . $category['name'] . ' » : cette sous-catégorie contient encore des compétences. '
                . 'Déplacez-les dans une autre sous-catégorie ou supprimez-les d\'abord.'
            );
        }
        $this->success('/admin/competences/categories', 'Sous-catégorie « ' . $category['name'] . ' » supprimée.');
    }

    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function findOr404(string $id): array
    {
        $category = SkillCategory::find((int) $id);
        if ($category === null) {
            $this->notFound();
        }
        return $category;
    }

    /**
     * @param array<string, mixed>|null $category
     * @param array<string, mixed>      $old
     * @param array<string, string>     $errors
     */
    private function form(?array $category, array $old, array $errors = [], int $status = 200): void
    {
        $isNew = $category === null;
        $this->render('admin/skill-categories/form', [
            'category' => $category,
            'old'      => $old,
            'errors'   => $errors,
            'themes'   => SkillTheme::all(),
            'icons'    => self::ICONS,
            'action'   => $isNew ? '/admin/competences/categories' : '/admin/competences/categories/' . (int) $category['id'],
            'meta'     => ['title' => $isNew ? 'Nouvelle sous-catégorie de compétences' : 'Modifier une sous-catégorie de compétences'],
        ], $status);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validate(array $input, ?int $exceptId): array
    {
        $v = new Validator($input);

        // Le thème doit exister (liste blanche des identifiants en base).
        $themeIds = array_map(static fn (array $t): string => (string) (int) $t['id'], SkillTheme::all());
        $theme    = $v->in('theme_id', 'Thème', $themeIds);
        if ($theme === null && trim(is_string($input['theme_id'] ?? null) ? $input['theme_id'] : '') === '') {
            $v->addError('theme_id', 'Choisissez un thème.');
        }

        $name     = $v->string('name', 'Nom', required: true, max: 80);
        $icon     = $v->in('icon', 'Icône', array_keys(self::ICONS), required: false);
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
            'theme_id' => $theme !== null ? (int) $theme : null,
            'name'     => $name,
            'slug'     => $slug,
            'icon'     => $icon,
            'position' => $position ?? 0,
        ], $v->errors()];
    }

    /** Slug unique construit depuis le nom : « reseau », sinon « reseau-2 », « reseau-3 »… */
    private function uniqueSlug(string $name, ?int $exceptId): ?string
    {
        $base = Slug::make($name, self::SLUG_MAX - 4);
        if ($base === '') {
            return null;
        }
        $slug = $base;
        for ($n = 2; SkillCategory::slugExists($slug, $exceptId); $n++) {
            $slug = $base . '-' . $n;
        }
        return $slug;
    }
}
