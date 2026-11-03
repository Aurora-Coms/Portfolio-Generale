<?php
/**
 * Liste des projets (brouillons compris), filtrable par section.
 *
 * Variables :
 *   $projects list     Project::adminList($section) : + image_count, categories
 *   $section  ?string  'game' | 'tech' | null (toutes)
 *   $counts   array    Project::counts()
 */

declare(strict_types=1);

$projects = isset($projects) && is_array($projects) ? $projects : [];
$section  = isset($section) && is_string($section) ? $section : null;
$counts   = isset($counts) && is_array($counts) ? $counts : [];

$filters = [
    ''     => ['Tous', (int) ($counts['game'] ?? 0) + (int) ($counts['tech'] ?? 0)],
    'game' => ['Jeux', (int) ($counts['game'] ?? 0)],
    'tech' => ['Techniques', (int) ($counts['tech'] ?? 0)],
];
// Champ caché transmis aux actions en ligne : on revient sur le même filtre.
$keep = $section !== null ? ['section' => $section] : [];
$newHref = '/admin/projets/nouveau' . ($section !== null ? '?section=' . $section : '');
?>
<?= partial('admin/page-header', [
    'comment' => '-- projets/',
    'title'   => 'Projets',
    'lead'    => 'Jeux et projets techniques, brouillons compris. Seuls les projets publiés sont visibles sur le site.',
    'actions' => '<a class="btn btn--primary" href="' . e($newHref) . '">' . icon('edit', 16) . 'Nouveau projet</a>',
]) ?>

<nav class="filter-bar" aria-label="Filtrer par section">
<?php foreach ($filters as $value => [$label, $count]):
    $active = ($value === '' && $section === null) || $value === $section;
    $href   = '/admin/projets' . ($value !== '' ? '?section=' . $value : '');
?>
    <a class="btn btn--secondary btn--sm" href="<?= e($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($label) ?> <span class="filter-bar__count"><?= $count ?></span></a>
<?php endforeach; ?>
</nav>

<?php if ($projects === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 projet',
    'title'   => $section === null ? 'Aucun projet pour le moment' : 'Aucun projet dans cette section',
    'text'    => 'Créez un projet : il restera en brouillon tant que vous ne le publiez pas.',
    'actions' => '<a class="btn btn--primary btn--sm" href="' . e($newHref) . '">' . icon('edit', 16) . 'Nouveau projet</a>',
]) ?>
<?php else: ?>
<div class="stack-sm">
<div class="table-wrap" tabindex="0" role="region" aria-label="Liste des projets">
    <table class="table">
        <thead>
            <tr>
                <th scope="col">Titre</th>
                <th scope="col">Section</th>
                <th scope="col">Catégories</th>
                <th scope="col">Statut</th>
                <th scope="col">Visibilité</th>
                <th scope="col" class="table__num">Images</th>
                <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($projects as $p):
    $id        = (int) $p['id'];
    $title     = (string) $p['title'];
    $published = (int) $p['is_published'] === 1;
    $featured  = (int) $p['is_featured'] === 1;
    $catNames  = array_map(static fn (array $c): string => (string) $c['name'], $p['categories'] ?? []);
?>
            <tr>
                <th scope="row">
                    <a class="table__title" href="/admin/projets/<?= $id ?>/modifier"><?= e($title) ?></a>
<?php if ($published): ?>
                    <a class="table__sub table__link mono" href="/projet/<?= e((string) $p['slug']) ?>" target="_blank" rel="noopener noreferrer">/projet/<?= e((string) $p['slug']) ?><span class="sr-only"> : voir sur le site (nouvel onglet)</span></a>
<?php else: ?>
                    <span class="table__sub mono">/projet/<?= e((string) $p['slug']) ?></span>
<?php endif; ?>
                </th>
                <td class="nowrap"><?= e((string) $p['section'] === 'game' ? 'Jeux' : 'Technique') ?></td>
                <td class="small text-soft"><?= $catNames !== [] ? e(implode(', ', $catNames)) : '<span class="text-muted">Aucune</span>' ?></td>
                <td><span class="badge badge--<?= (string) $p['status'] === 'done' ? 'done' : 'progress' ?>"><?= e(status_label((string) $p['status'])) ?></span></td>
                <td>
                    <div class="cluster">
                        <span class="badge badge--<?= $published ? 'published' : 'draft' ?>"><?= $published ? 'Publié' : 'Brouillon' ?></span>
<?php if ($featured): ?>
                        <span class="badge badge--featured">En avant</span>
<?php endif; ?>
                    </div>
                </td>
                <td class="table__num"><?= (int) $p['image_count'] ?></td>
                <td class="table__actions">
                    <div class="btn-row btn-row--even">
                        <a class="btn btn--secondary btn--sm btn--icon" href="/admin/projets/<?= $id ?>/modifier" aria-label="Modifier « <?= e($title) ?> »" title="Modifier"><?= icon('edit', 16) ?></a>
                        <a class="btn btn--secondary btn--sm btn--icon" href="/admin/projets/<?= $id ?>/images" aria-label="Images de « <?= e($title) ?> »" title="Images"><?= icon('image', 16) ?></a>
                        <?= partial('admin/post-button', [
                            'action' => '/admin/projets/' . $id . '/publier',
                            'label'  => $published ? 'Masquer' : 'Publier',
                            'icon'   => 'eye',
                            'fields' => $keep,
                        ]) ?>
                        <?= partial('admin/post-button', [
                            'action' => '/admin/projets/' . $id . '/vedette',
                            'label'   => 'En avant',
                            'icon'    => 'star',
                            'pressed' => $featured,
                            'fields'  => $keep,
                        ]) ?>
                        <?= partial('admin/post-button', [
                            'action'   => '/admin/projets/' . $id . '/supprimer',
                            'label'    => 'Supprimer « ' . $title . ' »',
                            'icon'     => 'trash',
                            'iconOnly' => true,
                            'variant'  => 'danger',
                            'fields'   => $keep,
                            'confirm'  => 'Supprimer définitivement le projet « ' . $title . ' » et ses ' . (int) $p['image_count'] . ' image(s) ? Cette action est irréversible.',
                        ]) ?>
                    </div>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="form__note">Tri : ordre d'affichage, puis date (le plus récent d'abord). « Masquer » repasse un projet publié en brouillon. Le bouton « En avant » enfoncé signifie que le projet est affiché sur l'accueil (s'il est publié).</p>
</div>
<?php endif; ?>
