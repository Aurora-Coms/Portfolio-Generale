<?php
/**
 * Catégories de projets (propres à une section : jeux ou technique).
 * Elles servent de filtres sur les pages publiques (?categorie=slug).
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminController;
use App\Core\Database;
use App\Core\Session;
use App\Core\Slug;
use App\Core\Validator;
use App\Models\Category;
use PDOException;

final class CategoryController extends AdminController
{
    /** Sections (valeur => libellé). */
    private const SECTIONS = ['game' => 'Jeux (Roblox)', 'tech' => 'Projets techniques'];

    private const FORM_FIELDS = ['section', 'name', 'slug', 'description', 'position'];

    /** GET /admin/categories */
    public function index(): void
    {
        $grouped = ['game' => [], 'tech' => []];
        foreach (Category::all() as $category) {
            $grouped[(string) $category['section']][] = $category;
        }
        $this->render('admin/categories/index', [
            'grouped'  => $grouped,
            'sections' => self::SECTIONS,
            'meta'     => ['title' => 'Catégories'],
        ]);
    }

    /** GET /admin/categories/nouveau (?section=tech) */
    public function create(): void
    {
        $section = is_string($_GET['section'] ?? null) && isset(self::SECTIONS[$_GET['section']]) ? $_GET['section'] : 'game';
        $this->renderForm(null, ['section' => $section, 'position' => '0'], []);
    }

    /** POST /admin/categories */
    public function store(): void
    {
        $input = $this->validate(null);
        if ($input['errors'] !== []) {
            $this->renderForm(null, $this->oldInput(), $input['errors'], 422);
            return;
        }
        $data = $input['data'];
        $data['slug'] = $this->uniqueSlug((string) $data['section'], $input['slugBase'], null);
        try {
            Category::create($data);
        } catch (PDOException $e) {
            if (!Database::isDuplicateKey($e)) {
                throw $e;
            }
            $this->renderForm(null, $this->oldInput(), ['slug' => 'Cette adresse vient d\'être utilisée : choisissez-en une autre.'], 422);
            return;
        }
        Session::flash('success', 'Catégorie « ' . $data['name'] . ' » créée.');
        $this->slugNote($input['slugWanted'], (string) $data['slug']);
        redirect('/admin/categories');
    }

    /** GET /admin/categories/{id}/modifier */
    public function edit(string $id): void
    {
        $category = $this->findOr404($id);
        $this->renderForm($category, $category, []);
    }

    /** POST /admin/categories/{id} */
    public function update(string $id): void
    {
        $category = $this->findOr404($id);
        $catId    = (int) $category['id'];

        $input = $this->validate($category);
        if ($input['errors'] !== []) {
            $this->renderForm($category, $this->oldInput(), $input['errors'], 422);
            return;
        }
        $data = $input['data'];
        $data['slug'] = $this->uniqueSlug((string) $data['section'], $input['slugBase'], $catId);
        try {
            Category::update($catId, $data);
        } catch (PDOException $e) {
            if (!Database::isDuplicateKey($e)) {
                throw $e;
            }
            $this->renderForm($category, $this->oldInput(), ['slug' => 'Cette adresse vient d\'être utilisée : choisissez-en une autre.'], 422);
            return;
        }
        Session::flash('success', 'Catégorie « ' . $data['name'] . ' » enregistrée.');
        $this->slugNote($input['slugWanted'], (string) $data['slug']);
        redirect('/admin/categories');
    }

    /** POST /admin/categories/{id}/supprimer */
    public function destroy(string $id): void
    {
        $category = $this->findOr404($id);
        $count    = (int) $category['project_count'];
        Category::delete((int) $category['id']);
        $this->success('/admin/categories', 'Catégorie « ' . $category['name'] . ' » supprimée'
            . ($count > 0 ? ' (retirée de ' . $count . ' projet' . ($count > 1 ? 's' : '') . ')' : '') . '.');
    }

    // --- Interne -------------------------------------------------------------------------

    /**
     * Catégorie + project_count (projets liés, brouillons compris).
     *
     * @return array<string, mixed>
     */
    private function findOr404(string $id): array
    {
        $category = Category::find((int) $id);
        if ($category === null) {
            $this->notFound();
        }
        $category['project_count'] = 0;
        foreach (Category::all() as $row) {
            if ((int) $row['id'] === (int) $category['id']) {
                $category['project_count'] = (int) $row['project_count'];
                break;
            }
        }
        return $category;
    }

    /**
     * @param array<string, mixed>|null $current catégorie modifiée (null en création)
     * @return array{data: array<string, mixed>, slugWanted: string, slugBase: string, errors: array<string, string>}
     */
    private function validate(?array $current): array
    {
        $v = new Validator($_POST);
        $section     = $v->in('section', 'Section', array_keys(self::SECTIONS));
        $name        = $v->string('name', 'Nom', required: true, max: 80);
        $slugInput   = $v->slug('slug', 'Adresse (slug)', 80);
        $description = $v->string('description', 'Description', max: 255);
        $position    = $v->int('position', 'Ordre d\'affichage', required: false, min: 0, max: 999);

        // Changer de section romprait les liaisons existantes (catégorie d'une autre section que ses projets).
        if ($current !== null && $section !== null && $section !== (string) $current['section'] && (int) $current['project_count'] > 0) {
            $v->addError('section', 'La section ne peut pas changer tant que des projets utilisent cette catégorie : retirez-la d\'abord de ces projets.');
        }

        return [
            'data' => [
                'section'     => $section,
                'name'        => $name,
                'description' => $description,
                'position'    => $position ?? 0,
            ],
            'slugWanted' => Slug::make($slugInput ?? (string) $name, 80) ?: 'categorie',
            'slugBase'   => $slugInput ?? (string) $name,
            'errors'    => $v->errors(),
        ];
    }

    /** Slug libre dans la section : « reseau », puis « reseau-2 »… */
    private function uniqueSlug(string $section, string $base, ?int $exceptId): string
    {
        $slug = Slug::make($base, 80);
        if ($slug === '') {
            $slug = 'categorie';
        }
        $candidate = $slug;
        for ($n = 2; Category::slugExists($section, $candidate, $exceptId); $n++) {
            $candidate = $slug . '-' . $n;
        }
        return $candidate;
    }

    private function slugNote(string $slugWanted, string $slug): void
    {
        if ($slugWanted !== $slug) {
            Session::flash('info', 'L\'adresse « ' . $slugWanted . ' » était déjà utilisée dans cette section : la catégorie utilise « ' . $slug . ' ».');
        }
    }

    /**
     * @param array<string, mixed>|null $category
     * @param array<string, mixed>      $old
     * @param array<string, string>     $errors
     */
    private function renderForm(?array $category, array $old, array $errors, int $status = 200): void
    {
        $this->render('admin/categories/form', [
            'category' => $category,
            'old'      => $old,
            'errors'   => $errors,
            'sections' => self::SECTIONS,
            'meta'     => ['title' => $category !== null ? 'Modifier « ' . $category['name'] . ' »' : 'Nouvelle catégorie'],
        ], $status);
    }

    /** @return array<string, string> */
    private function oldInput(): array
    {
        $old = [];
        foreach (self::FORM_FIELDS as $field) {
            $value = $_POST[$field] ?? '';
            $old[$field] = is_string($value) ? $value : '';
        }
        return $old;
    }
}
