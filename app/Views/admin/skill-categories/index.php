<?php
/**
 * Liste des catégories de compétences (par ordre d'affichage).
 *
 * @var list<array<string, mixed>> $categories  SkillCategory::all() (avec skill_count)
 */

declare(strict_types=1);
?>
<?= partial('admin/page-header', [
    'comment' => '-- competences/categories/',
    'title'   => 'Catégories de compétences',
    'lead'    => 'Les blocs de la page « Compétences ». Une catégorie ne peut être supprimée que si elle ne contient plus aucune compétence.',
    'actions' => '<a class="btn btn--primary" href="/admin/competences/categories/nouveau">' . icon('edit', 16) . 'Nouvelle catégorie</a>'
        . '<a class="btn btn--secondary" href="/admin/competences">' . icon('chip', 16) . 'Compétences</a>',
]) ?>

<?php if ($categories === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 catégorie',
    'icon'    => 'code',
    'title'   => 'Aucune catégorie',
    'text'    => 'Créez une catégorie (Réseau, Systèmes…) avant d\'ajouter des compétences.',
    'actions' => '<a class="btn btn--primary btn--sm" href="/admin/competences/categories/nouveau">Créer une catégorie</a>',
]) ?>
<?php else: ?>
<div class="table-wrap" tabindex="0" role="region" aria-label="Catégories de compétences">
    <table class="table">
        <thead>
            <tr>
                <th scope="col"><span class="sr-only">Icône</span></th>
                <th scope="col">Catégorie</th>
                <th scope="col" class="table__num">Compétences</th>
                <th scope="col" class="table__num">Ordre</th>
                <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($categories as $category):
    $id    = (int) $category['id'];
    $count = (int) $category['skill_count'];
?>
            <tr>
                <td class="table__icon"><span class="icon-box"><?= icon((string) ($category['icon'] ?? ''), 20) ?></span></td>
                <td>
                    <a class="table__title" href="/admin/competences/categories/<?= $id ?>/modifier"><?= e((string) $category['name']) ?></a>
                    <span class="table__sub mono"><?= e((string) $category['slug']) ?></span>
                </td>
                <td class="table__num"><?= $count ?></td>
                <td class="table__num"><?= (int) $category['position'] ?></td>
                <td class="table__actions">
                    <div class="btn-row">
                        <a class="btn btn--secondary btn--sm btn--icon" href="/admin/competences/categories/<?= $id ?>/modifier" aria-label="Modifier <?= e((string) $category['name']) ?>" title="Modifier"><?= icon('edit', 16) ?></a>
<?= partial('admin/post-button', [
    'action'   => '/admin/competences/categories/' . $id . '/supprimer',
    'label'    => 'Supprimer ' . $category['name'],
    'icon'     => 'trash',
    'iconOnly' => true,
    'variant'  => 'danger',
    'confirm'  => 'Supprimer définitivement la catégorie « ' . $category['name'] . ' » ?',
]) ?>
                    </div>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
