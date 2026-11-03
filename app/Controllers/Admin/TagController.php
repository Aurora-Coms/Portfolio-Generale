<?php
/**
 * Tags (technologies : Lua, Cisco, Linux…), communs aux deux sections.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminController;
use App\Core\Session;
use App\Core\Slug;
use App\Core\Validator;
use App\Models\Tag;
use PDOException;

final class TagController extends AdminController
{
    private const FORM_FIELDS = ['name', 'slug'];

    /** GET /admin/tags */
    public function index(): void
    {
        $this->render('admin/tags/index', [
            'tags' => Tag::all(),
            'meta' => ['title' => 'Tags'],
        ]);
    }

    /** GET /admin/tags/nouveau */
    public function create(): void
    {
        $this->renderForm(null, [], []);
    }

    /** POST /admin/tags */
    public function store(): void
    {
        $input = $this->validate(null);
        if ($input['errors'] !== []) {
            $this->renderForm(null, $this->oldInput(), $input['errors'], 422);
            return;
        }
        $data = ['name' => $input['name'], 'slug' => $this->uniqueSlug($input['slugBase'], null)];
        try {
            Tag::create($data);
        } catch (PDOException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
            $this->renderForm(null, $this->oldInput(), ['name' => 'Ce tag vient d\'être créé par ailleurs : rechargez la liste.'], 422);
            return;
        }
        Session::flash('success', 'Tag « ' . $data['name'] . ' » créé.');
        $this->slugNote($input['slugWanted'], $data['slug']);
        redirect('/admin/tags');
    }

    /** GET /admin/tags/{id}/modifier */
    public function edit(string $id): void
    {
        $tag = $this->findOr404($id);
        $this->renderForm($tag, $tag, []);
    }

    /** POST /admin/tags/{id} */
    public function update(string $id): void
    {
        $tag   = $this->findOr404($id);
        $tagId = (int) $tag['id'];

        $input = $this->validate($tagId);
        if ($input['errors'] !== []) {
            $this->renderForm($tag, $this->oldInput(), $input['errors'], 422);
            return;
        }
        $data = ['name' => $input['name'], 'slug' => $this->uniqueSlug($input['slugBase'], $tagId)];
        try {
            Tag::update($tagId, $data);
        } catch (PDOException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
            $this->renderForm($tag, $this->oldInput(), ['name' => 'Ce nom ou cette adresse vient d\'être utilisé : choisissez-en un autre.'], 422);
            return;
        }
        Session::flash('success', 'Tag « ' . $data['name'] . ' » enregistré.');
        $this->slugNote($input['slugWanted'], $data['slug']);
        redirect('/admin/tags');
    }

    /** POST /admin/tags/{id}/supprimer */
    public function destroy(string $id): void
    {
        $tag   = $this->findOr404($id);
        $count = (int) $tag['project_count'];
        Tag::delete((int) $tag['id']);
        $this->success('/admin/tags', 'Tag « ' . $tag['name'] . ' » supprimé'
            . ($count > 0 ? ' (retiré de ' . $count . ' projet' . ($count > 1 ? 's' : '') . ')' : '') . '.');
    }

    // --- Interne -------------------------------------------------------------------------

    /**
     * Tag + project_count.
     *
     * @return array<string, mixed>
     */
    private function findOr404(string $id): array
    {
        $tag = Tag::find((int) $id);
        if ($tag === null) {
            $this->notFound();
        }
        $tag['project_count'] = 0;
        foreach (Tag::all() as $row) {
            if ((int) $row['id'] === (int) $tag['id']) {
                $tag['project_count'] = (int) $row['project_count'];
                break;
            }
        }
        return $tag;
    }

    /**
     * @return array{name: ?string, slugWanted: string, slugBase: string, errors: array<string, string>}
     */
    private function validate(?int $exceptId): array
    {
        $v = new Validator($_POST);
        $name      = $v->string('name', 'Nom', required: true, max: 50);
        $slugInput = $v->slug('slug', 'Adresse (slug)', 55);

        // Unicité selon la base (sans tenir compte de la casse ni des accents).
        if ($name !== null && Tag::nameExists($name, $exceptId)) {
            $v->addError('name', 'Un tag porte déjà ce nom (ou un nom qui ne diffère que par les majuscules ou les accents).');
        }

        return [
            'name'      => $name,
            'slugWanted' => Slug::make($slugInput ?? (string) $name, 55) ?: 'tag',
            'slugBase'   => $slugInput ?? (string) $name,
            'errors'    => $v->errors(),
        ];
    }

    /** Slug libre : « lua », puis « lua-2 »… */
    private function uniqueSlug(string $base, ?int $exceptId): string
    {
        $slug = Slug::make($base, 55);
        if ($slug === '') {
            $slug = 'tag';
        }
        $candidate = $slug;
        for ($n = 2; Tag::slugExists($candidate, $exceptId); $n++) {
            $candidate = $slug . '-' . $n;
        }
        return $candidate;
    }

    private function slugNote(string $slugWanted, string $slug): void
    {
        if ($slugWanted !== $slug) {
            Session::flash('info', 'L\'adresse « ' . $slugWanted . ' » était déjà utilisée : le tag utilise « ' . $slug . ' ».');
        }
    }

    /**
     * @param array<string, mixed>|null $tag
     * @param array<string, mixed>      $old
     * @param array<string, string>     $errors
     */
    private function renderForm(?array $tag, array $old, array $errors, int $status = 200): void
    {
        $this->render('admin/tags/form', [
            'tag'    => $tag,
            'old'    => $old,
            'errors' => $errors,
            'meta'   => ['title' => $tag !== null ? 'Modifier « ' . $tag['name'] . ' »' : 'Nouveau tag'],
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
