<?php
/**
 * Carte d'un projet (liste des projets, accueil).
 *
 * Variables :
 *   $project      array  ligne au format de Project::published() / Project::featured() :
 *                        title, slug, summary, section, status, project_date, cover (null ou
 *                        [thumb_filename, filename, alt_text, width, height]), categories[], tags[]
 *   $headingLevel int    (optionnel) niveau du titre de la carte, 3 par défaut
 *   $hidden       bool   (optionnel) carte masquée (filtre actif côté serveur), false par défaut
 *   $reveal       bool   (optionnel) apparition au défilement (data-reveal), true par défaut
 */

declare(strict_types=1);

$project      = isset($project) && is_array($project) ? $project : [];
$headingLevel = max(2, min(4, (int) ($headingLevel ?? 3)));
$hidden       = (bool) ($hidden ?? false);
$reveal       = (bool) ($reveal ?? true);

$title      = (string) ($project['title'] ?? '');
$slug       = (string) ($project['slug'] ?? '');
$section    = (string) ($project['section'] ?? 'game');
$status     = (string) ($project['status'] ?? 'in_progress');
$categories = is_array($project['categories'] ?? null) ? $project['categories'] : [];
$tags       = is_array($project['tags'] ?? null) ? $project['tags'] : [];
$cover      = is_array($project['cover'] ?? null) ? $project['cover'] : null;

// Chemin façon fichier : « roblox/sci-fi/tardis.lua » (jeux) ou « projets/reseau/vlan » (technique).
$catSlugs = array_values(array_filter(array_map(static fn ($c): string => (string) ($c['slug'] ?? ''), $categories)));
$folders  = $catSlugs !== [] ? array_slice($catSlugs, 0, 2) : [$section === 'game' ? 'jeux' : 'projets'];
$path     = implode('/', $folders) . '/' . $slug . ($section === 'game' ? '.lua' : '');

$titleId = 'projet-' . $slug;
?>
<article class="card project-card" data-categories="<?= e(implode(' ', $catSlugs)) ?>"<?= $reveal ? ' data-reveal' : '' ?><?= $hidden ? ' hidden' : '' ?> aria-labelledby="<?= e($titleId) ?>">
    <div class="project-card__path" aria-hidden="true">
        <?= icon('file', 14) ?>
        <span class="project-card__path-text"><?= e($path) ?></span>
    </div>
    <div class="project-card__media">
<?php if ($cover !== null && (string) ($cover['thumb_filename'] ?? '') !== ''): ?>
        <img src="<?= e(upload_url((string) $cover['thumb_filename'])) ?>" alt="<?= e((string) ($cover['alt_text'] ?? '')) ?>"<?= (int) ($cover['width'] ?? 0) > 0 ? ' width="' . (int) $cover['width'] . '" height="' . (int) $cover['height'] . '"' : '' ?> loading="lazy" decoding="async">
<?php else: ?>
        <div class="media-placeholder" aria-hidden="true">-- capture à venir</div>
<?php endif; ?>
    </div>
    <div class="project-card__body">
        <h<?= $headingLevel ?> class="project-card__title" id="<?= e($titleId) ?>"><a class="project-card__link" href="/projet/<?= e($slug) ?>"><?= e($title) ?></a></h<?= $headingLevel ?>>
        <p class="project-card__summary"><?= e((string) ($project['summary'] ?? '')) ?></p>
        <div class="project-card__meta">
            <span class="badge badge--<?= status_badge($status) ?>"><?= e(status_label($status)) ?></span>
<?php if ($tags !== []): ?>
            <ul class="tags" aria-label="Technologies">
<?php foreach ($tags as $tag): ?>
                <li class="tag"><?= e((string) ($tag['name'] ?? '')) ?></li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
        </div>
    </div>
</article>
