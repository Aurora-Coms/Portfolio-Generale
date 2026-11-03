<?php
/**
 * Puces de filtre par catégorie (liens ?categorie=slug, améliorés par site.js).
 *
 * Sans JavaScript : chaque puce recharge la page filtrée (aria-current sur l'active).
 * Avec JavaScript : filtrage instantané des cartes de la grille ciblée, URL mise à jour
 * (history.replaceState) et annonce « N projets affichés ».
 *
 * Variables :
 *   $categories list    catégories (Category::forSection($section, true)) : name, slug, project_count (optionnel)
 *   $active     ?string slug actif (null = toutes)
 *   $basePath   string  chemin de la page, ex. '/projets/jeux'
 *   $target     string  id de la grille (partial site/project-grid), défaut 'projets'
 *   $allLabel   string  libellé de la puce « toutes », défaut '*.lua'
 *   $label      string  nom accessible du groupe, défaut 'Filtrer les projets par catégorie'
 *   $showCount  bool    afficher le nombre de projets par puce (défaut false)
 */

declare(strict_types=1);

$categories = isset($categories) && is_array($categories) ? $categories : [];
$active     = isset($active) && is_string($active) && $active !== '' ? $active : null;
$basePath   = (string) ($basePath ?? App\Core\Request::path());
$target     = (string) ($target ?? 'projets');
$allLabel   = (string) ($allLabel ?? '*.lua');
$label      = (string) ($label ?? 'Filtrer les projets par catégorie');
$showCount  = (bool) ($showCount ?? false);

if ($categories === []) {
    return;
}
?>
<nav class="filters" aria-label="<?= e($label) ?>" data-filter-group data-filter-target="<?= e($target) ?>">
    <ul class="filters__list">
        <li><a class="chip" href="<?= e($basePath) ?>" data-filter=""<?= $active === null ? ' aria-current="page"' : '' ?>><span class="sr-only">Toutes les catégories : </span><?= e($allLabel) ?></a></li>
<?php foreach ($categories as $cat):
    $slug = (string) ($cat['slug'] ?? '');
?>
        <li><a class="chip" href="<?= e($basePath . '?categorie=' . rawurlencode($slug)) ?>" data-filter="<?= e($slug) ?>"<?= $active === $slug ? ' aria-current="page"' : '' ?>><span class="sr-only"><?= e((string) ($cat['name'] ?? $slug)) ?> : </span><span aria-hidden="true"><?= e($slug) ?>/</span><?php if ($showCount && isset($cat['project_count'])): ?> <span class="chip__count">(<?= (int) $cat['project_count'] ?>)</span><?php endif; ?></a></li>
<?php endforeach; ?>
    </ul>
    <p class="sr-only" aria-live="polite" data-filter-status></p>
</nav>
