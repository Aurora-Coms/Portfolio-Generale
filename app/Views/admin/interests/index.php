<?php
/**
 * Liste des centres d'intérêt (par ordre d'affichage).
 *
 * @var list<array<string, mixed>> $interests
 * @var array<string, string>      $icons      nom d'icône => libellé
 */

declare(strict_types=1);
?>
<?= partial('admin/page-header', [
    'comment' => '-- interets/',
    'title'   => 'Centres d\'intérêt',
    'lead'    => 'Les loisirs affichés en cartes sur la page publique « À propos » (VTT, développement de jeux…).',
    'actions' => '<a class="btn btn--primary" href="/admin/interets/nouveau">' . icon('edit', 16) . 'Nouveau centre d\'intérêt</a>'
        . '<a class="btn btn--secondary" href="/a-propos" target="_blank" rel="noopener noreferrer">' . icon('external', 16) . 'Voir la page<span class="sr-only"> (nouvel onglet)</span></a>',
]) ?>

<?php if ($interests === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 élément',
    'icon'    => 'star',
    'title'   => 'Aucun centre d\'intérêt',
    'text'    => 'Quelques loisirs rendent le portfolio plus humain : ajoutez-en un ou deux.',
    'actions' => '<a class="btn btn--primary btn--sm" href="/admin/interets/nouveau">Ajouter un centre d\'intérêt</a>',
]) ?>
<?php else: ?>
<div class="table-wrap" tabindex="0" role="region" aria-label="Centres d'intérêt">
    <table class="table">
        <thead>
            <tr>
                <th scope="col"><span class="sr-only">Icône</span></th>
                <th scope="col">Centre d'intérêt</th>
                <th scope="col" class="table__num">Ordre</th>
                <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($interests as $interest):
    $id = (int) $interest['id'];
?>
            <tr>
                <td class="table__icon"><span class="icon-box"><?= icon((string) ($interest['icon'] ?? ''), 20) ?: icon('star', 20) ?></span></td>
                <td>
                    <a class="table__title" href="/admin/interets/<?= $id ?>/modifier"><?= e((string) $interest['label']) ?></a>
<?php if ((string) ($interest['description'] ?? '') !== ''): ?>
                    <span class="table__sub"><?= e(str_limit((string) $interest['description'], 120)) ?></span>
<?php endif; ?>
                </td>
                <td class="table__num"><?= (int) $interest['position'] ?></td>
                <td class="table__actions">
                    <div class="btn-row">
                        <a class="btn btn--secondary btn--sm btn--icon" href="/admin/interets/<?= $id ?>/modifier" aria-label="Modifier <?= e((string) $interest['label']) ?>" title="Modifier"><?= icon('edit', 16) ?></a>
<?= partial('admin/post-button', [
    'action'   => '/admin/interets/' . $id . '/supprimer',
    'label'    => 'Supprimer ' . $interest['label'],
    'icon'     => 'trash',
    'iconOnly' => true,
    'variant'  => 'danger',
    'confirm'  => 'Supprimer définitivement « ' . $interest['label'] . ' » ?',
]) ?>
                    </div>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
