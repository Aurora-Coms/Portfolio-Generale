<?php
/**
 * Administration des centres d'intérêt (affichés sur la page « À propos »).
 *
 * Routes (Router::resource('/admin/interets')) : index, create, store, edit,
 * update, destroy.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminController;
use App\Core\Validator;
use App\Models\Interest;

final class InterestController extends AdminController
{
    /** Icônes proposées (noms du helper icon()) => libellé lisible. */
    public const ICONS = [
        'bike'     => 'Vélo',
        'gamepad'  => 'Jeu vidéo',
        'code'     => 'Code',
        'shield'   => 'Sécurité',
        'network'  => 'Réseau',
        'music'    => 'Musique',
        'book'     => 'Lecture',
        'terminal' => 'Terminal',
        'chip'     => 'Électronique',
        'tools'    => 'Bricolage',
    ];

    public function index(): void
    {
        $this->render('admin/interests/index', [
            'interests' => Interest::all(),
            'icons'     => self::ICONS,
            'meta'      => ['title' => 'Centres d\'intérêt'],
        ]);
    }

    public function create(): void
    {
        $this->form(null, ['icon' => '', 'position' => '0']);
    }

    public function store(): void
    {
        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            $this->form(null, $_POST, $errors, 422);
            return;
        }
        Interest::create($data);
        $this->success('/admin/interets', 'Centre d\'intérêt « ' . $data['label'] . ' » ajouté.');
    }

    public function edit(string $id): void
    {
        $interest = $this->findOr404($id);
        $this->form($interest, [
            'label'       => (string) $interest['label'],
            'description' => (string) ($interest['description'] ?? ''),
            'icon'        => (string) ($interest['icon'] ?? ''),
            'position'    => (string) (int) $interest['position'],
        ]);
    }

    public function update(string $id): void
    {
        $interest = $this->findOr404($id);
        [$data, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            $this->form($interest, $_POST, $errors, 422);
            return;
        }
        Interest::update((int) $interest['id'], $data);
        $this->success('/admin/interets', 'Centre d\'intérêt « ' . $data['label'] . ' » enregistré.');
    }

    public function destroy(string $id): void
    {
        $interest = $this->findOr404($id);
        Interest::delete((int) $interest['id']);
        $this->success('/admin/interets', 'Centre d\'intérêt « ' . $interest['label'] . ' » supprimé.');
    }

    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function findOr404(string $id): array
    {
        $interest = Interest::find((int) $id);
        if ($interest === null) {
            $this->notFound();
        }
        return $interest;
    }

    /**
     * @param array<string, mixed>|null $interest
     * @param array<string, mixed>      $old
     * @param array<string, string>     $errors
     */
    private function form(?array $interest, array $old, array $errors = [], int $status = 200): void
    {
        $isNew = $interest === null;
        $this->render('admin/interests/form', [
            'interest' => $interest,
            'old'      => $old,
            'errors'   => $errors,
            'icons'    => self::ICONS,
            'action'   => $isNew ? '/admin/interets' : '/admin/interets/' . (int) $interest['id'],
            'meta'     => ['title' => $isNew ? 'Nouveau centre d\'intérêt' : 'Modifier un centre d\'intérêt'],
        ], $status);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validate(array $input): array
    {
        $v = new Validator($input);
        $label       = $v->string('label', 'Libellé', required: true, max: 80);
        $description = $v->string('description', 'Description', max: 255);
        $icon        = $v->in('icon', 'Icône', array_keys(self::ICONS), required: false);
        if ($icon === null && !isset($v->errors()['icon'])) {
            $v->addError('icon', 'Choisissez une icône.');
        }
        $position    = $v->int('position', 'Ordre', required: false, min: 0, max: 9999);

        return [[
            'label'       => $label,
            'description' => $description,
            'icon'        => $icon,
            'position'    => $position ?? 0,
        ], $v->errors()];
    }
}
