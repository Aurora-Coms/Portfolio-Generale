<?php
/**
 * Grille de cartes projet, filtrable (avec site/project-filters) et état vide intégré.
 *
 * IMPORTANT : passer la liste COMPLÈTE de la section (Project::published($section)),
 * pas une liste déjà filtrée : le filtre actif masque les autres cartes côté serveur
 * (attribut hidden) et le JavaScript peut alors les réafficher instantanément.
 *
 * Variables :
 *   $projects     list   projets (format Project::published())
 *   $id           string id de la grille, cible des filtres (défaut 'projets')
 *   $active       ?string slug de la catégorie active (null = toutes)
 *   $headingLevel int    niveau des titres des cartes (défaut 3)
 *   $emptyTitle   string (optionnel) titre de l'état vide
 *   $emptyText    string (optionnel) texte de l'état vide
 */

declare(strict_types=1);

$projects     = isset($projects) && is_array($projects) ? $projects : [];
$id           = (string) ($id ?? 'projets');
$active       = isset($active) && is_string($active) && $active !== '' ? $active : null;
$headingLevel = (int) ($headingLevel ?? 3);
$emptyTitle   = (string) ($emptyTitle ?? 'Aucun projet pour le moment');
$emptyText    = (string) ($emptyText ?? 'Les projets de cette catégorie seront bientôt publiés.');

$visible = 0;
?>
<div class="project-list" id="<?= e($id) ?>" data-filter-list>
<?php if ($projects !== []): ?>
    <div class="grid-cards">
<?php foreach ($projects as $project):
    $slugs  = array_map(static fn ($c): string => (string) ($c['slug'] ?? ''), is_array($project['categories'] ?? null) ? $project['categories'] : []);
    $hidden = $active !== null && !in_array($active, $slugs, true);
    $visible += $hidden ? 0 : 1;
?>
<?= partial('site/project-card', ['project' => $project, 'headingLevel' => $headingLevel, 'hidden' => $hidden]) ?>
<?php endforeach; ?>
    </div>
<?php endif; ?>
    <div data-filter-empty<?= $visible > 0 ? ' hidden' : '' ?>>
<?= partial('site/empty-state', ['comment' => '-- 0 résultat', 'title' => $emptyTitle, 'text' => $emptyText, 'icon' => 'folder']) ?>
    </div>
</div>
