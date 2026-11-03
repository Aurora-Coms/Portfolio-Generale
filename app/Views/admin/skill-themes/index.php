<?php
/**
 * Liste des thèmes de compétences (par ordre d'affichage).
 *
 * @var list<array<string, mixed>> $themes  SkillTheme::all() (avec category_count)
 */

declare(strict_types=1);
?>
<?= partial('admin/page-header', [
    'comment' => '-- competences/themes/',
    'title'   => 'Thèmes de compétences',
    'lead'    => 'Les grandes parties de la page « Compétences » (Informatique & réseaux, Développement, Langues…). Chaque thème regroupe des sous-catégories ; un thème ne peut être supprimé que s\'il n\'en contient plus aucune.',
    'actions' => '<a class="btn btn--primary" href="/admin/competences/themes/nouveau">' . icon('edit', 16) . 'Nouveau thème</a>'
        . '<a class="btn btn--secondary" href="/admin/competences/categories">' . icon('code', 16) . 'Sous-catégories</a>'
        . '<a class="btn btn--secondary" href="/admin/competences">' . icon('chip', 16) . 'Compétences</a>',
]) ?>

<?php if ($themes === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 thème',
    'icon'    => 'book',
    'title'   => 'Aucun thème',
    'text'    => 'Créez un thème (Informatique & réseaux, Langues…) avant d\'ajouter des sous-catégories et des compétences.',
    'actions' => '<a class="btn btn--primary btn--sm" href="/admin/competences/themes/nouveau">Créer un thème</a>',
]) ?>
<?php else: ?>
<div class="table-wrap" tabindex="0" role="region" aria-label="Thèmes de compétences">
    <table class="table table--compact">
        <thead>
            <tr>
                <th scope="col" class="table__icon"><span class="sr-only">Icône</span></th>
                <th scope="col">Thème</th>
                <th scope="col" class="table__num col-secondary">Ordre</th>
                <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($themes as $theme):
    $id    = (int) $theme['id'];
    $count = (int) $theme['category_count'];
?>
            <tr>
                <td class="table__icon"><span class="icon-box"><?= icon((string) ($theme['icon'] ?? ''), 20) ?></span></td>
                <td>
                    <a class="table__title" href="/admin/competences/themes/<?= $id ?>/modifier"><?= e((string) $theme['name']) ?></a>
                    <span class="table__sub"><?= $count === 0 ? 'Aucune sous-catégorie : absent de la page publique' : $count . ' sous-catégorie' . ($count > 1 ? 's' : '') ?></span>
                </td>
                <td class="table__num col-secondary"><?= (int) $theme['position'] ?></td>
                <td class="table__actions">
                    <div class="btn-row">
                        <a class="btn btn--secondary btn--sm btn--icon" href="/admin/competences/themes/<?= $id ?>/modifier" aria-label="Modifier <?= e((string) $theme['name']) ?>" title="Modifier"><?= icon('edit', 16) ?></a>
<?= partial('admin/post-button', [
    'action'   => '/admin/competences/themes/' . $id . '/supprimer',
    'label'    => 'Supprimer ' . $theme['name'],
    'icon'     => 'trash',
    'iconOnly' => true,
    'variant'  => 'danger',
    'confirm'  => 'Supprimer définitivement le thème « ' . $theme['name'] . ' » ?',
]) ?>
                    </div>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
