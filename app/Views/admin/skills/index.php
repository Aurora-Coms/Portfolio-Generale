<?php
/**
 * Compétences regroupées par thème puis par sous-catégorie, avec leur niveau
 * en 5 segments (ou « Sans niveau »).
 *
 * @var list<array<string, mixed>> $themes  SkillTheme::tree(true) : thèmes > categories > skills
 *                                          (thèmes et sous-catégories vides compris)
 */

declare(strict_types=1);

$totalSkills = 0;
$totalCategories = 0;
foreach ($themes as $theme) {
    $totalCategories += count($theme['categories']);
    foreach ($theme['categories'] as $category) {
        $totalSkills += count($category['skills']);
    }
}
?>
<?= partial('admin/page-header', [
    'comment' => '-- competences/',
    'title'   => 'Compétences',
    'lead'    => 'Rangées par thème puis par sous-catégorie. Niveau de 1 (notions) à 5 (maîtrise), affiché en barres sur la page publique « Compétences » ; sans niveau, la compétence est affichée sans barre (savoir-être, atouts).',
    'actions' => ($totalCategories > 0 ? '<a class="btn btn--primary" href="/admin/competences/nouveau">' . icon('edit', 16) . 'Nouvelle compétence</a>' : '')
        . '<a class="btn btn--secondary" href="/admin/competences/themes">' . icon('book', 16) . 'Thèmes</a>'
        . '<a class="btn btn--secondary" href="/admin/competences/categories">' . icon('code', 16) . 'Sous-catégories</a>'
        . '<a class="btn btn--secondary" href="/competences" target="_blank" rel="noopener noreferrer">' . icon('external', 16) . 'Voir la page<span class="sr-only"> (nouvel onglet)</span></a>',
]) ?>

<?php if ($themes === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 thème',
    'icon'    => 'book',
    'title'   => 'Commencez par créer un thème',
    'text'    => 'Les compétences sont rangées par thème (Informatique & réseaux, Langues…), puis par sous-catégorie (Réseau, Systèmes…).',
    'actions' => '<a class="btn btn--primary btn--sm" href="/admin/competences/themes/nouveau">Créer un thème</a>',
]) ?>
<?php else: ?>
<p class="form__note list-note list-note--top"><?= $totalSkills ?> compétence<?= $totalSkills > 1 ? 's' : '' ?> dans <?= $totalCategories ?> sous-catégorie<?= $totalCategories > 1 ? 's' : '' ?> et <?= count($themes) ?> thème<?= count($themes) > 1 ? 's' : '' ?>.</p>
<nav class="page-toc" aria-label="Thèmes de compétences">
    <ul class="cluster">
<?php foreach ($themes as $theme): ?>
        <li><a class="btn btn--ghost btn--sm" href="#theme-<?= (int) $theme['id'] ?>"><?= icon((string) ($theme['icon'] ?? ''), 16) ?><?= e((string) $theme['name']) ?></a></li>
<?php endforeach; ?>
    </ul>
</nav>
<div class="stack-lg">
<?php foreach ($themes as $theme):
    $themeId    = (int) $theme['id'];
    $categories = $theme['categories'];
    $themeTitle = 'theme-' . $themeId;
?>
    <section class="skill-theme-group" aria-labelledby="<?= e($themeTitle) ?>">
        <div class="list-head">
            <h2 class="section-title skill-group__title skill-theme-group__title" id="<?= e($themeTitle) ?>">
                <span class="icon-box"><?= icon((string) ($theme['icon'] ?? ''), 18) ?></span>
                <span><?= e((string) $theme['name']) ?></span>
            </h2>
            <div class="btn-row">
                <a class="btn btn--secondary btn--sm" href="/admin/competences/categories/nouveau?theme=<?= $themeId ?>"><?= icon('edit', 16) ?>Sous-catégorie<span class="sr-only"> : en ajouter une dans <?= e((string) $theme['name']) ?></span></a>
                <a class="btn btn--ghost btn--sm" href="/admin/competences/themes/<?= $themeId ?>/modifier"><?= icon('settings', 16) ?>Thème<span class="sr-only"> <?= e((string) $theme['name']) ?> : modifier</span></a>
            </div>
        </div>
<?php if ($categories === []): ?>
        <p class="empty-inline">Aucune sous-catégorie dans ce thème : il n'apparaît pas sur la page publique.</p>
<?php endif; ?>
<?php foreach ($categories as $category):
    $catId   = (int) $category['id'];
    $skills  = $category['skills'];
    $titleId = 'categorie-' . $catId;
?>
        <section class="skill-group" aria-labelledby="<?= e($titleId) ?>">
            <div class="list-head">
                <h3 class="subsection-title skill-group__title" id="<?= e($titleId) ?>">
                    <span class="icon-box icon-box--sm"><?= icon((string) ($category['icon'] ?? ''), 16) ?></span>
                    <span><?= e((string) $category['name']) ?></span>
                    <span class="badge badge--plain"><?= count($skills) ?><span class="sr-only"> compétence<?= count($skills) > 1 ? 's' : '' ?></span></span>
                </h3>
                <div class="btn-row">
                    <a class="btn btn--secondary btn--sm" href="/admin/competences/nouveau?categorie=<?= $catId ?>"><?= icon('edit', 16) ?>Ajouter<span class="sr-only"> une compétence dans <?= e((string) $category['name']) ?></span></a>
                    <a class="btn btn--ghost btn--sm" href="/admin/competences/categories/<?= $catId ?>/modifier"><?= icon('settings', 16) ?>Sous-catégorie<span class="sr-only"> <?= e((string) $category['name']) ?> : modifier</span></a>
                </div>
            </div>
<?php if ($skills === []): ?>
            <p class="empty-inline">Aucune compétence dans cette sous-catégorie : elle n'apparaît pas sur la page publique.</p>
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
    $id          = (int) $skill['id'];
    $customLabel = trim((string) ($skill['level_label'] ?? ''));
?>
                        <tr>
                            <td>
                                <a class="table__title" href="/admin/competences/<?= $id ?>/modifier"><?= e((string) $skill['name']) ?></a>
<?php if ((string) ($skill['description'] ?? '') !== ''): ?>
                                <span class="table__sub"><?= e(str_limit((string) $skill['description'], 110)) ?></span>
<?php endif; ?>
                            </td>
                            <td>
<?php if ($skill['level'] === null): ?>
                                <span class="level-cell__label level-cell__label--none">Sans niveau<?= $customLabel !== '' ? ' · ' . e($customLabel) : '' ?></span>
<?php else:
    $level = max(1, min(5, (int) $skill['level']));
?>
                                <span class="level-cell">
                                    <?= partial('site/skill-meter', ['level' => $level, 'label' => $customLabel]) ?>
                                    <span class="level-cell__label" aria-hidden="true"><?= $level ?>/5 · <?= e($customLabel !== '' ? $customLabel : level_label($level)) ?></span>
                                </span>
<?php endif; ?>
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
    </section>
<?php endforeach; ?>
</div>
<?php endif; ?>
