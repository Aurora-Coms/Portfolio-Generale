<?php
/**
 * Administration des compétences, rangées par thème puis par sous-catégorie.
 * Le niveau (1 à 5) est facultatif : sans niveau, la compétence est affichée
 * sans barre (savoir-être, atouts). Un libellé personnalisé peut remplacer le
 * libellé standard du niveau (ex. « A2 » ou « Langue maternelle »).
 *
 * Routes (Router::resource('/admin/competences')) : index, create
 * (?categorie=ID présélectionne la sous-catégorie), store, edit, update, destroy.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminController;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\SkillTheme;

final class SkillController extends AdminController
{
    /** Longueur maximale du libellé du niveau (colonne VARCHAR(40)). */
    private const LEVEL_LABEL_MAX = 40;

    public function index(): void
    {
        $this->render('admin/skills/index', [
            'themes' => SkillTheme::tree(true),
            'meta'   => ['title' => 'Compétences'],
        ]);
    }

    public function create(): void
    {
        // Sous-catégorie présélectionnée depuis la liste (?categorie=ID), si elle existe.
        $raw    = Request::query('categorie');
        $preset = preg_match('/^[1-9]\d{0,9}$/', $raw) === 1 ? (int) $raw : 0;
        $this->form(null, [
            'category_id' => $preset > 0 && SkillCategory::find($preset) !== null ? (string) $preset : '',
            'level'       => '3',
            'position'    => '0',
        ]);
    }

    public function store(): void
    {
        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            $this->form(null, $_POST, $errors, 422);
            return;
        }
        Skill::create($data);
        $this->success('/admin/competences', 'Compétence « ' . $data['name'] . ' » ajoutée.');
    }

    public function edit(string $id): void
    {
        $skill = $this->findOr404($id);
        $this->form($skill, [
            'category_id' => (string) (int) $skill['category_id'],
            'name'        => (string) $skill['name'],
            // Niveau vide = « Sans niveau (pas de barre) ».
            'level'       => $skill['level'] === null ? '' : (string) (int) $skill['level'],
            'level_label' => (string) ($skill['level_label'] ?? ''),
            'description' => (string) ($skill['description'] ?? ''),
            'position'    => (string) (int) $skill['position'],
        ]);
    }

    public function update(string $id): void
    {
        $skill = $this->findOr404($id);
        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            $this->form($skill, $_POST, $errors, 422);
            return;
        }
        Skill::update((int) $skill['id'], $data);
        $this->success('/admin/competences', 'Compétence « ' . $data['name'] . ' » enregistrée.');
    }

    public function destroy(string $id): void
    {
        $skill = $this->findOr404($id);
        Skill::delete((int) $skill['id']);
        $this->success('/admin/competences', 'Compétence « ' . $skill['name'] . ' » supprimée.');
    }

    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function findOr404(string $id): array
    {
        $skill = Skill::find((int) $id);
        if ($skill === null) {
            $this->notFound();
        }
        return $skill;
    }

    /**
     * @param array<string, mixed>|null $skill
     * @param array<string, mixed>      $old
     * @param array<string, string>     $errors
     */
    private function form(?array $skill, array $old, array $errors = [], int $status = 200): void
    {
        $isNew = $skill === null;
        $this->render('admin/skills/form', [
            'skill'      => $skill,
            'old'        => $old,
            'errors'     => $errors,
            'categories' => SkillCategory::all(),
            'action'     => $isNew ? '/admin/competences' : '/admin/competences/' . (int) $skill['id'],
            'meta'       => ['title' => $isNew ? 'Nouvelle compétence' : 'Modifier une compétence'],
        ], $status);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validate(array $input): array
    {
        $v = new Validator($input);

        // La sous-catégorie doit exister (liste blanche des identifiants en base).
        $categoryIds = array_map(static fn (array $c): string => (string) (int) $c['id'], SkillCategory::all());
        $category    = $v->in('category_id', 'Sous-catégorie', $categoryIds);
        if ($category === null && trim(is_string($input['category_id'] ?? null) ? $input['category_id'] : '') === '') {
            $v->addError('category_id', 'Choisissez une sous-catégorie.');
        }

        $name        = $v->string('name', 'Nom', required: true, max: 80);
        // Niveau facultatif : vide = pas de barre de niveau (NULL en base).
        $level       = $v->int('level', 'Niveau', required: false, min: 1, max: 5);
        $levelLabel  = $v->string('level_label', 'Libellé du niveau', max: self::LEVEL_LABEL_MAX);
        $description = $v->string('description', 'Description', max: 255);
        $position    = $v->int('position', 'Ordre', required: false, min: 0, max: 9999);

        return [[
            'category_id' => $category !== null ? (int) $category : null,
            'name'        => $name,
            'level'       => $level,
            'level_label' => $levelLabel,
            'description' => $description,
            'position'    => $position ?? 0,
        ], $v->errors()];
    }
}
