<?php
/**
 * Liste des entrées du parcours (brouillons compris), de la plus récente à la plus ancienne.
 *
 * @var list<array<string, mixed>> $entries
 */

declare(strict_types=1);

$typeBadge = static fn (string $type): string => match ($type) {
    'education'  => 'badge--accent',
    'internship' => 'badge--progress',
    default      => '',
};
?>
<?= partial('admin/page-header', [
    'comment' => '-- parcours.md',
    'title'   => 'Parcours',
    'lead'    => 'Études, stages et autres expériences affichés sur la page publique « Parcours », triés par date de début.',
    'actions' => '<a class="btn btn--primary" href="/admin/parcours/nouveau">' . icon('edit', 16) . 'Nouvelle entrée</a>'
        . '<a class="btn btn--secondary" href="/parcours" target="_blank" rel="noopener noreferrer">' . icon('external', 16) . 'Voir la page<span class="sr-only"> (nouvel onglet)</span></a>',
]) ?>

<?php if ($entries === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 entrée',
    'icon'    => 'calendar',
    'title'   => 'Le parcours est vide',
    'text'    => 'Ajoutez votre formation, vos stages et vos expériences.',
    'actions' => '<a class="btn btn--primary btn--sm" href="/admin/parcours/nouveau">Ajouter une entrée</a>',
]) ?>
<?php else: ?>
<div class="table-wrap" tabindex="0" role="region" aria-label="Entrées du parcours">
    <table class="table">
        <thead>
            <tr>
                <th scope="col">Période</th>
                <th scope="col">Entrée</th>
                <th scope="col">Type</th>
                <th scope="col">Statut</th>
                <th scope="col" class="table__num">Ordre</th>
                <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($entries as $entry):
    $id    = (int) $entry['id'];
    $place = trim((string) $entry['organization'] . ((string) ($entry['location'] ?? '') !== '' ? ' · ' . $entry['location'] : ''));
?>
            <tr>
                <td class="mono nowrap"><?= e(period_fr((string) $entry['start_date'], $entry['end_date'] !== null ? (string) $entry['end_date'] : null, (string) $entry['date_precision'])) ?></td>
                <td>
                    <a class="table__title" href="/admin/parcours/<?= $id ?>/modifier"><?= e((string) $entry['title']) ?></a>
                    <span class="table__sub"><?= e($place) ?></span>
                </td>
                <td><span class="badge <?= e($typeBadge((string) $entry['type'])) ?>"><?= e(timeline_type_label((string) $entry['type'])) ?></span></td>
                <td><span class="badge badge--<?= (int) $entry['is_published'] === 1 ? 'published' : 'draft' ?>"><?= (int) $entry['is_published'] === 1 ? 'Publié' : 'Brouillon' ?></span></td>
                <td class="table__num"><?= (int) $entry['position'] ?></td>
                <td class="table__actions">
                    <div class="btn-row">
                        <a class="btn btn--secondary btn--sm btn--icon" href="/admin/parcours/<?= $id ?>/modifier" aria-label="Modifier <?= e((string) $entry['title']) ?>" title="Modifier"><?= icon('edit', 16) ?></a>
<?= partial('admin/post-button', [
    'action'   => '/admin/parcours/' . $id . '/supprimer',
    'label'    => 'Supprimer ' . $entry['title'],
    'icon'     => 'trash',
    'iconOnly' => true,
    'variant'  => 'danger',
    'confirm'  => 'Supprimer définitivement l\'entrée « ' . $entry['title'] . ' » ?',
]) ?>
                    </div>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="form__note list-note"><?= count($entries) ?> entrée<?= count($entries) > 1 ? 's' : '' ?>. Les brouillons ne sont pas visibles sur le site.</p>
<?php endif; ?>
