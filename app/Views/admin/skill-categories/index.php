<?php
/**
 * Sous-catégories de compétences, regroupées par thème (ordre d'affichage).
 *
 * @var list<array<string, mixed>> $themes  SkillTheme::all(), chacun avec sa clé
 *                                          « categories » (SkillCategory::all() : skill_count)
 */

declare(strict_types=1);

$total = 0;
foreach ($themes as $theme) {
    $total += count($theme['categories']);
}
?>
<?= partial('admin/page-header', [
    'comment' => '-- competences/categories/',
    'title'   => 'Sous-catégories de compétences',
    'lead'    => 'Les cartes de la page « Compétences », rangées par thème. Une sous-catégorie ne peut être supprimée que si elle ne contient plus aucune compétence.',
    'actions' => ($themes !== [] ? '<a class="btn btn--primary" href="/admin/competences/categories/nouveau">' . icon('edit', 16) . 'Nouvelle sous-catégorie</a>' : '')
        . '<a class="btn btn--secondary" href="/admin/competences/themes">' . icon('book', 16) . 'Thèmes</a>'
        . '<a class="btn btn--secondary" href="/admin/competences">' . icon('chip', 16) . 'Compétences</a>',
]) ?>

<?php if ($themes === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 thème',
    'icon'    => 'book',
    'title'   => 'Commencez par créer un thème',
    'text'    => 'Chaque sous-catégorie appartient à un thème (Informatique & réseaux, Développement, Langues…).',
    'actions' => '<a class="btn btn--primary btn--sm" href="/admin/competences/themes/nouveau">Créer un thème</a>',
]) ?>
<?php else: ?>
<p class="form__note list-note list-note--top"><?= $total ?> sous-catégorie<?= $total > 1 ? 's' : '' ?> dans <?= count($themes) ?> thème<?= count($themes) > 1 ? 's' : '' ?>.</p>
<div class="stack-lg">
<?php foreach ($themes as $theme):
    $themeId    = (int) $theme['id'];
    $categories = $theme['categories'];
    $titleId    = 'theme-' . $themeId;
?>
    <section class="stack" aria-labelledby="<?= e($titleId) ?>">
        <div class="list-head">
            <h2 class="section-title skill-group__title" id="<?= e($titleId) ?>">
                <span class="icon-box"><?= icon((string) ($theme['icon'] ?? ''), 18) ?></span>
                <span><?= e((string) $theme['name']) ?></span>
                <span class="badge badge--plain"><?= count($categories) ?><span class="sr-only"> sous-catégorie<?= count($categories) > 1 ? 's' : '' ?></span></span>
            </h2>
            <div class="btn-row">
                <a class="btn btn--secondary btn--sm" href="/admin/competences/categories/nouveau?theme=<?= $themeId ?>"><?= icon('edit', 16) ?>Ajouter<span class="sr-only"> une sous-catégorie dans <?= e((string) $theme['name']) ?></span></a>
                <a class="btn btn--ghost btn--sm" href="/admin/competences/themes/<?= $themeId ?>/modifier"><?= icon('settings', 16) ?>Thème<span class="sr-only"> <?= e((string) $theme['name']) ?> : modifier</span></a>
            </div>
        </div>
<?php if ($categories === []): ?>
        <p class="empty-inline">Aucune sous-catégorie dans ce thème : il n'apparaît pas sur la page publique.</p>
<?php else: ?>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Sous-catégories : <?= e((string) $theme['name']) ?>">
            <table class="table table--compact">
                <thead>
                    <tr>
                        <th scope="col" class="table__icon"><span class="sr-only">Icône</span></th>
                        <th scope="col">Sous-catégorie</th>
                        <th scope="col" class="table__num col-secondary">Compétences</th>
                        <th scope="col" class="table__num col-secondary">Ordre</th>
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
                        <td class="table__num col-secondary"><?= $count ?></td>
                        <td class="table__num col-secondary"><?= (int) $category['position'] ?></td>
                        <td class="table__actions">
                            <div class="btn-row">
                                <a class="btn btn--secondary btn--sm btn--icon" href="/admin/competences/categories/<?= $id ?>/modifier" aria-label="Modifier <?= e((string) $category['name']) ?>" title="Modifier"><?= icon('edit', 16) ?></a>
<?= partial('admin/post-button', [
    'action'   => '/admin/competences/categories/' . $id . '/supprimer',
    'label'    => 'Supprimer ' . $category['name'],
    'icon'     => 'trash',
    'iconOnly' => true,
    'variant'  => 'danger',
    'confirm'  => 'Supprimer définitivement la sous-catégorie « ' . $category['name'] . ' » ?',
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
</div>
<?php endif; ?>
