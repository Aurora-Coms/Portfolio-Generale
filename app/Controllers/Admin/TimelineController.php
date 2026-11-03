<?php
/**
 * Administration du parcours : études, stages et autres expériences.
 *
 * Routes (Router::resource('/admin/parcours')) :
 *   GET  /admin/parcours                 index    liste triée par date
 *   GET  /admin/parcours/nouveau         create
 *   POST /admin/parcours                 store
 *   GET  /admin/parcours/{id}/modifier   edit
 *   POST /admin/parcours/{id}            update
 *   POST /admin/parcours/{id}/supprimer  destroy
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminController;
use App\Core\Validator;
use App\Models\TimelineEntry;

final class TimelineController extends AdminController
{
    /** Types d'entrée autorisés (colonne ENUM). */
    public const TYPES = ['education', 'internship', 'other'];

    /** Précisions d'affichage des dates (colonne ENUM). */
    public const PRECISIONS = ['day', 'month', 'year'];

    public function index(): void
    {
        $this->render('admin/timeline/index', [
            'entries' => TimelineEntry::all(),
            'meta'    => ['title' => 'Parcours'],
        ]);
    }

    public function create(): void
    {
        $this->form(null, [
            'type'           => 'education',
            'date_precision' => 'month',
            'is_published'   => '1',
            'position'       => '0',
        ]);
    }

    public function store(): void
    {
        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            $this->form(null, $_POST, $errors, 422);
            return;
        }
        TimelineEntry::create($data);
        $this->success('/admin/parcours', 'Entrée « ' . $data['title'] . ' » ajoutée au parcours.');
    }

    public function edit(string $id): void
    {
        $entry = $this->findOr404($id);
        $this->form($entry, self::toForm($entry));
    }

    public function update(string $id): void
    {
        $entry = $this->findOr404($id);
        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            $this->form($entry, $_POST, $errors, 422);
            return;
        }
        TimelineEntry::update((int) $entry['id'], $data);
        $this->success('/admin/parcours', 'Entrée « ' . $data['title'] . ' » enregistrée.');
    }

    public function destroy(string $id): void
    {
        $entry = $this->findOr404($id);
        TimelineEntry::delete((int) $entry['id']);
        $this->success('/admin/parcours', 'Entrée « ' . $entry['title'] . ' » supprimée.');
    }

    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function findOr404(string $id): array
    {
        $entry = TimelineEntry::find((int) $id);
        if ($entry === null) {
            $this->notFound();
        }
        return $entry;
    }

    /**
     * @param array<string, mixed>|null $entry  entrée modifiée (null = création)
     * @param array<string, mixed>      $old    valeurs du formulaire
     * @param array<string, string>     $errors
     */
    private function form(?array $entry, array $old, array $errors = [], int $status = 200): void
    {
        $isNew = $entry === null;
        $this->render('admin/timeline/form', [
            'entry'  => $entry,
            'old'    => $old,
            'errors' => $errors,
            'action' => $isNew ? '/admin/parcours' : '/admin/parcours/' . (int) $entry['id'],
            'meta'   => ['title' => $isNew ? 'Nouvelle entrée du parcours' : 'Modifier une entrée du parcours'],
        ], $status);
    }

    /**
     * Valide le formulaire.
     *
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validate(array $input): array
    {
        $v = new Validator($input);

        $type         = $v->in('type', 'Type', self::TYPES);
        $title        = $v->string('title', 'Titre', required: true, max: 150);
        $organization = $v->string('organization', 'Établissement ou entreprise', required: true, max: 150);
        $location     = $v->string('location', 'Lieu', max: 100);
        $start        = $v->date('start_date', 'Date de début', required: true);
        $end          = $v->date('end_date', 'Date de fin');
        $precision    = $v->in('date_precision', 'Précision d\'affichage', self::PRECISIONS);
        $description  = $v->text('description', 'Description', max: 5000);
        $position     = $v->int('position', 'Ordre', required: false, min: 0, max: 9999);

        // Dates au format AAAA-MM-JJ : la comparaison de chaînes suffit.
        if ($start !== null && $end !== null && $end < $start) {
            $v->addError('end_date', 'La date de fin ne peut pas être avant la date de début.');
        }

        $data = [
            'type'           => $type,
            'title'          => $title,
            'organization'   => $organization,
            'location'       => $location,
            'start_date'     => $start,
            'end_date'       => $end,
            'date_precision' => $precision,
            'description'    => $description,
            'is_published'   => $v->bool('is_published'),
            'position'       => $position ?? 0,
        ];
        return [$data, $v->errors()];
    }

    /**
     * Ligne de la base => valeurs du formulaire (chaînes).
     *
     * @param array<string, mixed> $entry
     * @return array<string, string>
     */
    private static function toForm(array $entry): array
    {
        return [
            'type'           => (string) $entry['type'],
            'title'          => (string) $entry['title'],
            'organization'   => (string) $entry['organization'],
            'location'       => (string) ($entry['location'] ?? ''),
            'start_date'     => (string) $entry['start_date'],
            'end_date'       => (string) ($entry['end_date'] ?? ''),
            'date_precision' => (string) $entry['date_precision'],
            'description'    => (string) ($entry['description'] ?? ''),
            'is_published'   => (string) (int) $entry['is_published'],
            'position'       => (string) (int) $entry['position'],
        ];
    }
}
