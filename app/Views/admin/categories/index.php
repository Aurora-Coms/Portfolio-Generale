<?php
/**
 * Liste des catégories de projets, groupées par section.
 *
 * Variables :
 *   $grouped  array  ['game' => list, 'tech' => list] (lignes de Category::all(), + project_count)
 *   $sections array  valeur => libellé
 */

declare(strict_types=1);

$grouped  = isset($grouped) && is_array($grouped) ? $grouped : [];
$sections = isset($sections) && is_array($sections) ? $sections : [];
?>
<?= partial('admin/page-header', [
    'comment' => '-- categories/',
    'title'   => 'Catégories',
    'lead'    => 'Les catégories servent de filtres sur les pages Projets. Chacune appartient à une section.',
    'actions' => '<a class="btn btn--primary" href="/admin/categories/nouveau">' . icon('edit', 16) . 'Nouvelle catégorie</a>',
]) ?>

<div class="stack-lg">
<?php foreach ($sections as $section => $sectionLabel):
    $rows = $grouped[$section] ?? [];
    $headingId = 'titre-' . $section;
?>
<section class="stack-sm" aria-labelledby="<?= e($headingId) ?>">
    <div class="list-head">
        <h2 class="section-title" id="<?= e($headingId) ?>"><?= e($sectionLabel) ?> <span class="text-muted">(<?= count($rows) ?>)</span></h2>
        <a class="btn btn--ghost btn--sm" href="/admin/categories/nouveau?section=<?= e((string) $section) ?>"><?= icon('edit', 16) ?>Ajouter<span class="sr-only"> une catégorie (<?= e($sectionLabel) ?>)</span></a>
    </div>
<?php if ($rows === []): ?>
    <?= partial('site/empty-state', ['comment' => '-- 0 catégorie', 'title' => 'Aucune catégorie dans cette section']) ?>
<?php else: ?>
    <div class="table-wrap" tabindex="0" role="region" aria-label="Catégories : <?= e($sectionLabel) ?>">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Nom</th>
                    <th scope="col">Description</th>
                    <th scope="col" class="table__num">Ordre</th>
                    <th scope="col" class="table__num">Projets</th>
                    <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody>
<?php foreach ($rows as $c):
    $cid   = (int) $c['id'];
    $name  = (string) $c['name'];
    $count = (int) $c['project_count'];
    $confirm = 'Supprimer la catégorie « ' . $name . ' » ?'
        . ($count > 0 ? ' Elle sera retirée des ' . $count . ' projet(s) qui l\'utilisent (les projets eux-mêmes ne sont pas supprimés).' : '');
?>
                <tr>
                    <th scope="row">
                        <a class="table__title" href="/admin/categories/<?= $cid ?>/modifier"><?= e($name) ?></a>
                        <span class="table__sub mono">?categorie=<?= e((string) $c['slug']) ?></span>
                    </th>
                    <td class="small text-soft"><?= (string) ($c['description'] ?? '') !== '' ? e(str_limit((string) $c['description'], 90)) : '<span class="text-muted">Aucune</span>' ?></td>
                    <td class="table__num"><?= (int) $c['position'] ?></td>
                    <td class="table__num"><?= $count ?></td>
                    <td class="table__actions">
                        <div class="btn-row">
                            <a class="btn btn--secondary btn--sm btn--icon" href="/admin/categories/<?= $cid ?>/modifier" aria-label="Modifier « <?= e($name) ?> »" title="Modifier"><?= icon('edit', 16) ?></a>
                            <?= partial('admin/post-button', [
                                'action'   => '/admin/categories/' . $cid . '/supprimer',
                                'label'    => 'Supprimer « ' . $name . ' »',
                                'icon'     => 'trash',
                                'iconOnly' => true,
                                'variant'  => 'danger',
                                'confirm'  => $confirm,
                            ]) ?>
                        </div>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</section>
<?php endforeach; ?>
<p class="form__note">« Projets » compte tous les projets liés, brouillons compris. Sur le site, une catégorie sans projet publié n'apparaît pas dans les filtres.</p>
</div>
