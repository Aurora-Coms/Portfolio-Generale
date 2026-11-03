<?php
/**
 * Liste des projets d'une section (jeux ou projets techniques), filtrable par catégorie.
 *
 * Variables (ProjectController::listing) :
 *   $section    string  'game' | 'tech'
 *   $config     array   textes de la section : path, title, comment, lead, allLabel, switch
 *   $other      array   même format, pour l'autre section (lien croisé)
 *   $sections   array   les deux sections (sélecteur jeux / techniques)
 *   $projects   list    Project::published($section) : liste COMPLÈTE de la section
 *   $categories list    Category::forSection($section, true)
 *   $active     ?string slug de la catégorie active (null = toutes)
 */

declare(strict_types=1);

$section    = (string) ($section ?? 'game');
$config     = isset($config) && is_array($config) ? $config : [];
$other      = isset($other) && is_array($other) ? $other : [];
$sections   = isset($sections) && is_array($sections) ? $sections : [];
$projects   = isset($projects) && is_array($projects) ? $projects : [];
$categories = isset($categories) && is_array($categories) ? $categories : [];
$active     = isset($active) && is_string($active) ? $active : null;
?>
<?= partial('site/page-header', [
    'comment' => (string) $config['comment'],
    'title'   => (string) $config['title'],
    'lead'    => (string) $config['lead'],
]) ?>

<div class="projects-toolbar">
    <nav class="section-switch" aria-label="Sections de projets">
        <ul class="section-switch__list">
<?php foreach ($sections as $key => $s): ?>
            <li><a class="section-switch__link" href="<?= e((string) $s['path']) ?>"<?= $key === $section ? ' aria-current="page"' : '' ?>><span class="sr-only"><?= e((string) $s['title']) ?></span><span aria-hidden="true"><?= e((string) $s['switch']) ?></span></a></li>
<?php endforeach; ?>
        </ul>
    </nav>
<?= partial('site/project-filters', [
    'categories' => $categories,
    'active'     => $active,
    'basePath'   => (string) $config['path'],
    'allLabel'   => (string) $config['allLabel'],
    'showCount'  => true,
]) ?>
</div>

<?php if ($projects === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 projet publié',
    'title'   => $config['title'] . ' en cours de rédaction',
    'text'    => $section === 'tech'
        ? 'Mes projets réseaux, systèmes et cybersécurité sont en cours de rédaction et seront publiés prochainement. En attendant, découvrez mes projets de jeux.'
        : 'Mes projets de jeux sont en cours de rédaction et seront publiés prochainement.',
    'icon'    => $section === 'tech' ? 'terminal' : 'gamepad',
    'actions' => '<a class="btn btn--secondary btn--sm" href="' . e((string) $other['path']) . '">' . e('Voir les ' . mb_strtolower((string) $other['title'])) . icon('arrow', 16) . '</a>',
]) ?>
<?php else: ?>
<?= partial('site/project-grid', [
    'projects'     => $projects,
    'active'       => $active,
    'headingLevel' => 2,
    'emptyTitle'   => 'Aucun projet dans cette catégorie',
    'emptyText'    => 'Choisissez une autre catégorie ou affichez tous les projets.',
]) ?>
<?php endif; ?>

<?php if ($projects !== []): ?>
<aside class="card cta" aria-labelledby="titre-voir-aussi">
    <div class="cta__body">
        <p class="cta__comment" aria-hidden="true">-- voir aussi</p>
        <h2 class="cta__title" id="titre-voir-aussi"><?= e((string) $other['title']) ?></h2>
        <p class="cta__text"><?= e((string) $other['lead']) ?></p>
    </div>
    <div class="cta__actions">
        <a class="btn btn--secondary" href="<?= e((string) $other['path']) ?>"><?= e('Voir les ' . mb_strtolower((string) $other['title'])) ?><?= icon('arrow') ?></a>
    </div>
</aside>
<?php endif; ?>
