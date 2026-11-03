<?php
/**
 * Liste des tags (technologies) avec le nombre de projets liés.
 *
 * Variables :
 *   $tags list  Tag::all() (+ project_count, brouillons compris)
 */

declare(strict_types=1);

$tags = isset($tags) && is_array($tags) ? $tags : [];
?>
<?= partial('admin/page-header', [
    'comment' => '-- tags/',
    'title'   => 'Tags',
    'lead'    => 'Technologies affichées sur les projets (« lua », « cisco »…). Communes aux jeux et aux projets techniques.',
    'actions' => '<a class="btn btn--primary" href="/admin/tags/nouveau">' . icon('tag', 16) . 'Nouveau tag</a>',
]) ?>

<?php if ($tags === []): ?>
<?= partial('site/empty-state', [
    'icon'    => 'tag',
    'comment' => '-- 0 tag',
    'title'   => 'Aucun tag pour le moment',
    'text'    => 'Vous pouvez aussi en créer directement depuis le formulaire d\'un projet.',
    'actions' => '<a class="btn btn--primary btn--sm" href="/admin/tags/nouveau">' . icon('tag', 16) . 'Nouveau tag</a>',
]) ?>
<?php else: ?>
<div class="stack-sm">
<div class="table-wrap" tabindex="0" role="region" aria-label="Liste des tags">
    <table class="table table--compact">
        <thead>
            <tr>
                <th scope="col">Nom</th>
                <th scope="col" class="col-secondary">Adresse (slug)</th>
                <th scope="col" class="table__num">Projets</th>
                <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($tags as $t):
    $tid   = (int) $t['id'];
    $name  = (string) $t['name'];
    $count = (int) $t['project_count'];
?>
            <tr>
                <th scope="row"><a class="table__title" href="/admin/tags/<?= $tid ?>/modifier"><?= e($name) ?></a></th>
                <td class="col-secondary"><span class="mono small text-soft"><?= e((string) $t['slug']) ?></span></td>
                <td class="table__num"><?= $count ?></td>
                <td class="table__actions">
                    <div class="btn-row">
                        <a class="btn btn--secondary btn--sm btn--icon" href="/admin/tags/<?= $tid ?>/modifier" aria-label="Modifier « <?= e($name) ?> »" title="Modifier"><?= icon('edit', 16) ?></a>
                        <?= partial('admin/post-button', [
                            'action'   => '/admin/tags/' . $tid . '/supprimer',
                            'label'    => 'Supprimer « ' . $name . ' »',
                            'icon'     => 'trash',
                            'iconOnly' => true,
                            'variant'  => 'danger',
                            'confirm'  => 'Supprimer le tag « ' . $name . ' » ?' . ($count > 0 ? ' Il sera retiré des ' . $count . ' projet(s) qui l\'utilisent.' : ''),
                        ]) ?>
                    </div>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="form__note"><?= count($tags) ?> tag(s). « Projets » compte tous les projets liés, brouillons compris.</p>
</div>
<?php endif; ?>
