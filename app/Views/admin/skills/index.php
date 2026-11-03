<?php
/**
 * Compétences regroupées par catégorie, avec leur niveau en 5 segments.
 *
 * @var list<array<string, mixed>> $categories  SkillCategory::withSkills()
 */

declare(strict_types=1);

$total = 0;
foreach ($categories as $category) {
    $total += count($category['skills']);
}
?>
<?= partial('admin/page-header', [
    'comment' => '-- competences/',
    'title'   => 'Compétences',
    'lead'    => 'Niveau de 1 (notions) à 5 (maîtrise), affiché en barres sur la page publique « Compétences ».',
    'actions' => ($categories !== [] ? '<a class="btn btn--primary" href="/admin/competences/nouveau">' . icon('edit', 16) . 'Nouvelle compétence</a>' : '')
        . '<a class="btn btn--secondary" href="/admin/competences/categories">' . icon('code', 16) . 'Catégories</a>'
        . '<a class="btn btn--secondary" href="/competences" target="_blank" rel="noopener noreferrer">' . icon('external', 16) . 'Voir la page<span class="sr-only"> (nouvel onglet)</span></a>',
]) ?>

<?php if ($categories === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 catégorie',
    'icon'    => 'chip',
    'title'   => 'Commencez par créer une catégorie',
    'text'    => 'Chaque compétence appartient à une catégorie (Réseau, Systèmes, Scripting…).',
    'actions' => '<a class="btn btn--primary btn--sm" href="/admin/competences/categories/nouveau">Créer une catégorie</a>',
]) ?>
<?php else: ?>
<p class="form__note list-note list-note--top"><?= $total ?> compétence<?= $total > 1 ? 's' : '' ?> dans <?= count($categories) ?> catégorie<?= count($categories) > 1 ? 's' : '' ?>.</p>
<div class="stack-lg">
<?php foreach ($categories as $category):
    $catId   = (int) $category['id'];
    $skills  = $category['skills'];
    $titleId = 'categorie-' . $catId;
?>
    <section class="skill-group" aria-labelledby="<?= e($titleId) ?>">
        <div class="list-head">
            <h2 class="section-title skill-group__title" id="<?= e($titleId) ?>">
                <span class="icon-box"><?= icon((string) ($category['icon'] ?? ''), 18) ?></span>
                <span><?= e((string) $category['name']) ?></span>
                <span class="badge badge--plain"><?= count($skills) ?><span class="sr-only"> compétence<?= count($skills) > 1 ? 's' : '' ?></span></span>
            </h2>
            <div class="btn-row">
                <a class="btn btn--secondary btn--sm" href="/admin/competences/nouveau?categorie=<?= $catId ?>"><?= icon('edit', 16) ?>Ajouter<span class="sr-only"> une compétence dans <?= e((string) $category['name']) ?></span></a>
                <a class="btn btn--ghost btn--sm" href="/admin/competences/categories/<?= $catId ?>/modifier"><?= icon('settings', 16) ?>Catégorie<span class="sr-only"> <?= e((string) $category['name']) ?> : modifier</span></a>
            </div>
        </div>
<?php if ($skills === []): ?>
        <p class="empty-inline">Aucune compétence dans cette catégorie pour le moment.</p>
<?php else: ?>
        <div class="table-wrap" tabindex="0" role="region" aria-label="Compétences : <?= e((string) $category['name']) ?>">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Compétence</th>
                        <th scope="col">Niveau</th>
                        <th scope="col" class="table__num">Ordre</th>
                        <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($skills as $skill):
    $id    = (int) $skill['id'];
    $level = max(1, min(5, (int) $skill['level']));
?>
                    <tr>
                        <td>
                            <a class="table__title" href="/admin/competences/<?= $id ?>/modifier"><?= e((string) $skill['name']) ?></a>
<?php if ((string) ($skill['description'] ?? '') !== ''): ?>
                            <span class="table__sub"><?= e(str_limit((string) $skill['description'], 110)) ?></span>
<?php endif; ?>
                        </td>
                        <td>
                            <span class="level-cell">
                                <?= partial('site/skill-meter', ['level' => $level]) ?>
                                <span class="level-cell__label" aria-hidden="true"><?= $level ?>/5 · <?= e(level_label($level)) ?></span>
                            </span>
                        </td>
                        <td class="table__num"><?= (int) $skill['position'] ?></td>
                        <td class="table__actions">
                            <div class="btn-row">
                                <a class="btn btn--secondary btn--sm btn--icon" href="/admin/competences/<?= $id ?>/modifier" aria-label="Modifier <?= e((string) $skill['name']) ?>" title="Modifier"><?= icon('edit', 16) ?></a>
<?= partial('admin/post-button', [
    'action'   => '/admin/competences/' . $id . '/supprimer',
    'label'    => 'Supprimer ' . $skill['name'],
    'icon'     => 'trash',
    'iconOnly' => true,
    'variant'  => 'danger',
    'confirm'  => 'Supprimer définitivement la compétence « ' . $skill['name'] . ' » ?',
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
