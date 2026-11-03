<?php
/**
 * Administration des projets (jeux Roblox et projets techniques) :
 * liste filtrable, création, modification, suppression, publication et
 * mise en avant.
 *
 * Les images d'un projet sont gérées par ImageController.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminController;
use App\Core\ImageProcessor;
use App\Core\Session;
use App\Core\Slug;
use App\Core\Validator;
use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\Tag;
use PDOException;

final class ProjectController extends AdminController
{
    /** Sections (valeur => libellé). */
    public const SECTIONS = ['game' => 'Jeux (Roblox)', 'tech' => 'Projets techniques'];

    /** Statuts (valeur => libellé). */
    public const STATUSES = ['in_progress' => 'En cours', 'done' => 'Terminé'];

    /**
     * Longueur maximale des textes Markdown. Colonne TEXT = 65 535 octets :
     * 10 000 caractères tiennent même s'ils font tous 4 octets (emoji).
     */
    private const TEXT_MAX = 10000;

    /** Nombre maximal de nouveaux tags créés en une fois. */
    private const NEW_TAGS_MAX = 20;

    /** Champs texte du formulaire (pour réafficher la saisie après une erreur). */
    private const FORM_FIELDS = [
        'section', 'title', 'slug', 'summary', 'context', 'work_done', 'learnings',
        'youtube_id', 'external_url', 'external_label', 'status', 'project_date',
        'position', 'is_published', 'is_featured', 'new_tags',
    ];

    // --- Liste -----------------------------------------------------------------------

    /** GET /admin/projets (?section=game|tech) */
    public function index(): void
    {
        $section = self::section($_GET['section'] ?? null);
        $this->render('admin/projects/index', [
            'projects' => Project::adminList($section),
            'section'  => $section,
            'counts'   => Project::counts(),
            'meta'     => ['title' => 'Projets'],
        ]);
    }

    // --- Création ----------------------------------------------------------------------

    /** GET /admin/projets/nouveau (?section=tech pour présélectionner la section) */
    public function create(): void
    {
        $this->renderForm(null, [
            'section'      => self::section($_GET['section'] ?? null) ?? 'game',
            'status'       => 'in_progress',
            'position'     => '0',
            'is_published' => '0',
            'is_featured'  => '0',
        ], [], [], []);
    }

    /** POST /admin/projets */
    public function store(): void
    {
        $input = $this->validate(null);
        if ($input['errors'] !== []) {
            $this->renderForm(null, $this->oldInput(), $input['errors'], $input['categoryIds'], $input['tagIds'], 422);
            return;
        }

        [$newTagIds, $created, $tagError] = $this->resolveNewTags($input['newTags']);
        if ($tagError !== null) {
            $this->renderForm(null, $this->oldInput(), ['new_tags' => $tagError], $input['categoryIds'], array_merge($input['tagIds'], $newTagIds), 422);
            return;
        }

        $data = $input['data'];
        $data['slug'] = Project::uniqueSlug($input['slugBase']);
        try {
            $id = Project::create($data, $input['categoryIds'], array_merge($input['tagIds'], $newTagIds));
        } catch (PDOException $e) {
            if (!self::isDuplicate($e)) {
                throw $e;
            }
            // Course improbable : l'adresse vient d'être prise par un autre enregistrement.
            $this->renderForm(null, $this->oldInput(), ['slug' => 'Cette adresse vient d\'être utilisée : choisissez-en une autre.'], $input['categoryIds'], array_merge($input['tagIds'], $newTagIds), 422);
            return;
        }

        Session::flash('success', 'Projet « ' . $data['title'] . ' » créé. Ajoutez maintenant ses images.');
        $this->flashSideNotes($input['slugWanted'], $data['slug'], $created);
        redirect('/admin/projets/' . $id . '/images');
    }

    // --- Modification ------------------------------------------------------------------

    /** GET /admin/projets/{id}/modifier */
    public function edit(string $id): void
    {
        $project = $this->findOr404($id);
        $this->renderForm($project, $project, [], $project['category_ids'], $project['tag_ids']);
    }

    /** POST /admin/projets/{id} */
    public function update(string $id): void
    {
        $project = $this->findOr404($id);
        $projectId = (int) $project['id'];

        $input = $this->validate($projectId);
        if ($input['errors'] !== []) {
            $this->renderForm($project, $this->oldInput(), $input['errors'], $input['categoryIds'], $input['tagIds'], 422);
            return;
        }

        [$newTagIds, $created, $tagError] = $this->resolveNewTags($input['newTags']);
        if ($tagError !== null) {
            $this->renderForm($project, $this->oldInput(), ['new_tags' => $tagError], $input['categoryIds'], array_merge($input['tagIds'], $newTagIds), 422);
            return;
        }

        $data = $input['data'];
        $data['slug'] = Project::uniqueSlug($input['slugBase'], $projectId);
        try {
            Project::update($projectId, $data, $input['categoryIds'], array_merge($input['tagIds'], $newTagIds));
        } catch (PDOException $e) {
            if (!self::isDuplicate($e)) {
                throw $e;
            }
            $this->renderForm($project, $this->oldInput(), ['slug' => 'Cette adresse vient d\'être utilisée : choisissez-en une autre.'], $input['categoryIds'], array_merge($input['tagIds'], $newTagIds), 422);
            return;
        }

        Session::flash('success', 'Projet « ' . $data['title'] . ' » enregistré.');
        if ((int) $project['is_published'] === 1 && $data['slug'] !== (string) $project['slug']) {
            Session::flash('warning', 'L\'adresse publique du projet a changé (/projet/' . $data['slug'] . ') : les liens déjà partagés vers l\'ancienne adresse ne fonctionnent plus.');
        }
        $this->flashSideNotes($input['slugWanted'], $data['slug'], $created);
        redirect('/admin/projets');
    }

    // --- Suppression, bascules ---------------------------------------------------------

    /** POST /admin/projets/{id}/supprimer */
    public function destroy(string $id): void
    {
        $project = $this->findOr404($id);
        $images  = Project::delete((int) $project['id']);
        foreach ($images as $image) {
            ImageProcessor::deleteFiles(self::uploadDir(), (string) $image['filename'], (string) $image['thumb_filename']);
        }
        $count = count($images);
        $this->success(
            self::indexPath($_POST['section'] ?? null),
            'Projet « ' . $project['title'] . ' » supprimé' . ($count > 0 ? ' avec ses ' . $count . ' image' . ($count > 1 ? 's' : '') : '') . '.'
        );
    }

    /** POST /admin/projets/{id}/publier */
    public function togglePublished(string $id): void
    {
        $project = $this->findOr404($id);
        $projectId = (int) $project['id'];
        Project::toggle($projectId, 'is_published');

        if ((int) $project['is_published'] === 0) {
            Session::flash('success', 'Projet « ' . $project['title'] . ' » publié : il est visible sur le site.');
            if (ProjectImage::countForProject($projectId) === 0) {
                Session::flash('warning', 'Ce projet n\'a encore aucune image : sa carte affichera « capture à venir ».');
            }
        } else {
            Session::flash('success', 'Projet « ' . $project['title'] . ' » dépublié : il n\'est plus visible sur le site.');
        }
        redirect(self::indexPath($_POST['section'] ?? null));
    }

    /** POST /admin/projets/{id}/vedette */
    public function toggleFeatured(string $id): void
    {
        $project = $this->findOr404($id);
        Project::toggle((int) $project['id'], 'is_featured');

        if ((int) $project['is_featured'] === 0) {
            Session::flash('success', 'Projet « ' . $project['title'] . ' » mis en avant sur l\'accueil.');
            if ((int) $project['is_published'] === 0) {
                Session::flash('info', 'Ce projet est un brouillon : il n\'apparaîtra sur l\'accueil qu\'une fois publié.');
            }
        } else {
            Session::flash('success', 'Projet « ' . $project['title'] . ' » retiré de l\'accueil.');
        }
        redirect(self::indexPath($_POST['section'] ?? null));
    }

    // --- Interne -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function findOr404(string $id): array
    {
        $project = Project::find((int) $id);
        if ($project === null) {
            $this->notFound();
        }
        return $project;
    }

    /**
     * Valide le formulaire.
     *
     * @return array{data: array<string, mixed>, categoryIds: list<int>, tagIds: list<int>, newTags: list<string>,
     *               slugWanted: string, slugBase: string, errors: array<string, string>}
     */
    private function validate(?int $exceptId): array
    {
        $v = new Validator($_POST);

        $section   = $v->in('section', 'Section', array_keys(self::SECTIONS));
        $title     = $v->string('title', 'Titre', required: true, max: 120);
        $slugInput = $v->slug('slug', 'Adresse (slug)', 130);
        $summary   = $v->string('summary', 'Résumé', required: true, max: 300);
        $context   = $v->text('context', 'Contexte', max: self::TEXT_MAX);
        $workDone  = $v->text('work_done', 'Ce que j\'ai fait', max: self::TEXT_MAX);
        $learnings = $v->text('learnings', 'Ce que j\'ai appris', max: self::TEXT_MAX);
        $youtube   = $v->youtubeId('youtube_id', 'Vidéo YouTube');
        $url       = $v->url('external_url', 'Lien externe');
        $label     = $v->string('external_label', 'Libellé du lien', max: 60);
        $status    = $v->in('status', 'Statut', array_keys(self::STATUSES));
        $date      = $v->date('project_date', 'Date');
        $position  = $v->int('position', 'Ordre d\'affichage', required: false, min: 0, max: 999);
        $newTags   = $this->parseNewTags($v->string('new_tags', 'Nouveaux tags', max: 1000), $v);

        $errors = $v->errors();
        if ($label !== null && $url === null && !isset($errors['external_url'])) {
            $v->addError('external_label', 'Renseignez aussi l\'adresse du lien, ou videz ce libellé.');
        }

        return [
            'data' => [
                'section'        => $section,
                'title'          => $title,
                'summary'        => $summary,
                'context'        => $context,
                'work_done'      => $workDone,
                'learnings'      => $learnings,
                'youtube_id'     => $youtube,
                'external_url'   => $url,
                'external_label' => $url !== null ? $label : null,
                'status'         => $status,
                'project_date'   => $date,
                'position'       => $position ?? 0,
                'is_published'   => $v->bool('is_published'),
                'is_featured'    => $v->bool('is_featured'),
            ],
            'categoryIds' => $v->ids('category_ids'),
            'tagIds'      => $v->ids('tag_ids'),
            'newTags'     => $newTags,
            'slugWanted'  => Slug::make($slugInput ?? (string) $title, 130) ?: 'projet',
            'slugBase'    => $slugInput ?? (string) $title,
            'errors'      => $v->errors(),
        ];
    }

    /**
     * « Lua, Roblox Studio , lua » => ['Lua', 'Roblox Studio'] (doublons retirés, casse ignorée).
     *
     * @return list<string>
     */
    private function parseNewTags(?string $raw, Validator $v): array
    {
        if ($raw === null) {
            return [];
        }
        $names = [];
        foreach (explode(',', $raw) as $part) {
            $name = trim((string) preg_replace('/\s+/u', ' ', $part));
            if ($name === '') {
                continue;
            }
            if (mb_strlen($name) > 50) {
                $v->addError('new_tags', 'Le tag « ' . str_limit($name, 30) . ' » dépasse 50 caractères.');
                continue;
            }
            $names[self::tagKey($name)] ??= $name;
        }
        if (count($names) > self::NEW_TAGS_MAX) {
            $v->addError('new_tags', self::NEW_TAGS_MAX . ' nouveaux tags au maximum à la fois.');
        }
        return array_values($names);
    }

    /**
     * Trouve ou crée les tags demandés. Un tag existant de même nom (sans tenir
     * compte de la casse ni des accents, comme la base) est réutilisé.
     *
     * @param list<string> $names
     * @return array{0: list<int>, 1: list<string>, 2: ?string} [identifiants, noms créés, erreur]
     */
    private function resolveNewTags(array $names): array
    {
        if ($names === []) {
            return [[], [], null];
        }
        $known = [];
        foreach (Tag::all() as $tag) {
            $known[self::tagKey((string) $tag['name'])] ??= (int) $tag['id'];
        }

        $ids = [];
        $created = [];
        foreach ($names as $name) {
            $key = self::tagKey($name);
            if (isset($known[$key])) {
                $ids[] = $known[$key];
                continue;
            }
            if (Tag::nameExists($name)) {
                return [$ids, $created, 'Un tag au nom très proche de « ' . $name . ' » existe déjà : cochez-le dans la liste ci-dessus.'];
            }
            try {
                $tagId = Tag::create(['name' => $name, 'slug' => self::uniqueTagSlug($name)]);
            } catch (PDOException $e) {
                if (!self::isDuplicate($e)) {
                    throw $e;
                }
                return [$ids, $created, 'Le tag « ' . $name . ' » n\'a pas pu être créé : un tag similaire existe déjà.'];
            }
            $ids[] = $tagId;
            $known[$key] = $tagId;
            $created[] = $name;
        }
        return [array_values(array_unique($ids)), $created, null];
    }

    /** Clé de comparaison d'un nom de tag : minuscules, sans accents, espaces simples. */
    private static function tagKey(string $name): string
    {
        $key = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
        if (class_exists(\Transliterator::class)) {
            $t = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
            if ($t !== null) {
                $key = (string) $t->transliterate($key);
            }
        }
        return $key;
    }

    private static function uniqueTagSlug(string $name): string
    {
        $base = Slug::make($name, 55);
        if ($base === '') {
            $base = 'tag';
        }
        $slug = $base;
        for ($n = 2; Tag::slugExists($slug); $n++) {
            $slug = $base . '-' . $n;
        }
        return $slug;
    }

    /**
     * Messages complémentaires après un enregistrement réussi.
     *
     * @param list<string> $created
     */
    private function flashSideNotes(string $slugWanted, string $slug, array $created): void
    {
        if ($slugWanted !== $slug) {
            Session::flash('info', 'L\'adresse « ' . $slugWanted . ' » était déjà utilisée : le projet utilise « ' . $slug . ' ».');
        }
        if ($created !== []) {
            $n = count($created);
            Session::flash('info', ($n > 1 ? $n . ' nouveaux tags créés : ' : 'Nouveau tag créé : ') . implode(', ', $created) . '.');
        }
    }

    /**
     * @param array<string, mixed>|null $project
     * @param array<string, mixed>      $old
     * @param array<string, string>     $errors
     * @param list<int>                 $categoryIds
     * @param list<int>                 $tagIds
     */
    private function renderForm(?array $project, array $old, array $errors, array $categoryIds, array $tagIds, int $status = 200): void
    {
        $this->render('admin/projects/form', [
            'project'     => $project,
            'old'         => $old,
            'errors'      => $errors,
            'categoryIds' => $categoryIds,
            'tagIds'      => $tagIds,
            'categories'  => Category::all(),
            'tags'        => Tag::all(),
            'sections'    => self::SECTIONS,
            'statuses'    => self::STATUSES,
            'meta'        => ['title' => $project !== null ? 'Modifier « ' . $project['title'] . ' »' : 'Nouveau projet'],
        ], $status);
    }

    /**
     * Saisie à réafficher après une erreur (chaînes uniquement, champs connus).
     *
     * @return array<string, string>
     */
    private function oldInput(): array
    {
        $old = [];
        foreach (self::FORM_FIELDS as $field) {
            $value = $_POST[$field] ?? '';
            $old[$field] = is_string($value) ? $value : '';
        }
        return $old;
    }

    /** Section valide ou null. */
    private static function section(mixed $value): ?string
    {
        return is_string($value) && isset(self::SECTIONS[$value]) ? $value : null;
    }

    /** Liste des projets, en conservant le filtre de section. */
    private static function indexPath(mixed $section): string
    {
        $section = self::section($section);
        return '/admin/projets' . ($section !== null ? '?section=' . $section : '');
    }

    private static function uploadDir(): string
    {
        return PUBLIC_PATH . '/uploads/projects';
    }

    /** Violation de contrainte d'unicité (SQLSTATE 23000) ? */
    private static function isDuplicate(PDOException $e): bool
    {
        return (string) $e->getCode() === '23000';
    }
}
