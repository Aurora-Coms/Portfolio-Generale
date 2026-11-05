<?php
/**
 * Page détail d'un projet publié.
 *
 * Variables (ProjectController::show) :
 *   $project     array   Project::findPublishedBySlug() : title, slug, summary, section, status,
 *                        project_date, context, work_done, learnings, youtube_id, external_url,
 *                        external_label, cover, categories[], tags[], images[]
 *   $section     string  'game' | 'tech'
 *   $sectionPath string  chemin de la liste de la section, ex. '/projets/jeux'
 *   $sectionName string  nom de la section, ex. 'Projets de jeux'
 *   $externalUrl string  lien externe validé (http/https) ou ''
 */

declare(strict_types=1);

$project     = isset($project) && is_array($project) ? $project : [];
$section     = (string) ($section ?? 'game');
$sectionPath = (string) ($sectionPath ?? '/projets/jeux');
$sectionName = (string) ($sectionName ?? 'Projets');
$externalUrl = (string) ($externalUrl ?? '');

$title      = (string) $project['title'];
$status     = (string) $project['status'];
$categories = is_array($project['categories'] ?? null) ? $project['categories'] : [];
$tags       = is_array($project['tags'] ?? null) ? $project['tags'] : [];
$images     = is_array($project['images'] ?? null) ? $project['images'] : [];
$date       = date_fr($project['project_date'] ?? null, 'month');
$extLabel   = trim((string) ($project['external_label'] ?? ''));
$extLabel   = $extLabel !== '' ? $extLabel : 'Voir le projet';

// Chemin façon fichier, comme sur les cartes : « roblox/sci-fi-temporel/tardis.lua ».
$catSlugs = array_values(array_filter(array_map(static fn ($c): string => (string) ($c['slug'] ?? ''), $categories)));
$folders  = $catSlugs !== [] ? array_slice($catSlugs, 0, 2) : [$section === 'game' ? 'jeux' : 'projets'];
$filePath = implode('/', $folders) . '/' . $project['slug'] . ($section === 'game' ? '.lua' : '');

// Blocs de contenu (Markdown) affichés seulement s'ils sont remplis.
$blocks = [
    ['id' => 'titre-contexte',       'title' => 'Contexte',          'text' => (string) ($project['context'] ?? '')],
    ['id' => 'titre-realisation',    'title' => 'Ce que j\'ai fait',  'text' => (string) ($project['work_done'] ?? '')],
    ['id' => 'titre-apprentissages', 'title' => 'Ce que j\'ai appris', 'text' => (string) ($project['learnings'] ?? '')],
];
$blocks = array_values(array_filter($blocks, static fn (array $b): bool => trim($b['text']) !== ''));
$badge  = '<span class="badge badge--' . status_badge($status) . '">' . e(status_label($status)) . '</span>';
?>
<article aria-labelledby="titre-projet">
    <header class="project-head">
<?= partial('site/breadcrumb', ['items' => [
    ['label' => 'Accueil', 'href' => '/'],
    ['label' => $sectionName, 'href' => $sectionPath],
    ['label' => $title],
]]) ?>
        <p class="comment project-head__path" aria-hidden="true">-- <?= e($filePath) ?></p>
        <h1 class="project-head__title" id="titre-projet"><?= e($title) ?></h1>
        <div class="project-head__meta">
            <?= $badge ?>
<?php if ($date !== ''): ?>
            <span class="meta-item"><?= icon('calendar', 16) ?><span class="sr-only">Date : </span><?= e($date) ?></span>
<?php endif; ?>
<?php if ($categories !== []): ?>
            <span class="meta-item"><?= icon('folder', 16) ?><span class="sr-only">Catégories : </span><?= e(implode(', ', array_map(static fn ($c): string => (string) $c['name'], $categories))) ?></span>
<?php endif; ?>
        </div>
        <p class="project-head__summary"><?= e((string) $project['summary']) ?></p>
    </header>

    <div class="detail-layout">
        <div class="detail-main">
<?= partial('site/gallery', ['images' => $images, 'title' => $title]) ?>
<?= partial('site/youtube', ['id' => (string) ($project['youtube_id'] ?? ''), 'title' => $title]) ?>
<?php foreach ($blocks as $block): ?>
            <section aria-labelledby="<?= e($block['id']) ?>">
                <h2 class="content-block__title" id="<?= e($block['id']) ?>"><?= e($block['title']) ?></h2>
                <div class="prose"><?= md($block['text']) ?></div>
            </section>
<?php endforeach; ?>
        </div>

        <aside class="detail-aside card card--pad stack" aria-labelledby="titre-infos">
            <h2 class="card__title" id="titre-infos">Informations</h2>
            <dl class="info-list">
                <div class="info-list__row"><dt>statut</dt><dd><?= $badge ?></dd></div>
<?php if ($date !== ''): ?>
                <div class="info-list__row"><dt>date</dt><dd><?= e($date) ?></dd></div>
<?php endif; ?>
                <div class="info-list__row"><dt>section</dt><dd><a href="<?= e($sectionPath) ?>"><?= e($sectionName) ?></a></dd></div>
<?php if ($categories !== []): ?>
                <div class="info-list__row">
                    <dt>catégories</dt>
                    <dd>
                        <ul class="inline-list">
<?php foreach ($categories as $cat): ?>
                            <li><a href="<?= e($sectionPath . '?categorie=' . rawurlencode((string) $cat['slug'])) ?>"><?= e((string) $cat['name']) ?></a></li>
<?php endforeach; ?>
                        </ul>
                    </dd>
                </div>
<?php endif; ?>
<?php if ($tags !== []): ?>
                <div class="info-list__row">
                    <dt>technologies</dt>
                    <dd>
                        <ul class="tags">
<?php foreach ($tags as $tag): ?>
                            <li class="tag"><?= e((string) $tag['name']) ?></li>
<?php endforeach; ?>
                        </ul>
                    </dd>
                </div>
<?php endif; ?>
            </dl>
            <div class="detail-aside__actions">
<?php if ($externalUrl !== ''): ?>
                <a class="btn btn--primary btn--block" href="<?= e($externalUrl) ?>" rel="noopener noreferrer"><?= icon('external') ?><?= e($extLabel) ?></a>
<?php endif; ?>
                <a class="btn btn--secondary btn--block" href="<?= e($sectionPath) ?>"><?= icon('arrow-left') ?>Retour aux <?= e(mb_strtolower($sectionName)) ?></a>
            </div>
        </aside>
    </div>
</article>

<aside class="card cta" aria-labelledby="titre-contact-projet">
    <div class="cta__body">
        <p class="cta__comment" aria-hidden="true">-- contact.sh</p>
        <h2 class="cta__title" id="titre-contact-projet">Une question sur ce projet ?</h2>
        <p class="cta__text">Je serai ravi d'en parler plus en détail, ou d'échanger sur une proposition de stage.</p>
    </div>
    <div class="cta__actions">
        <a class="btn btn--primary" href="/contact"><?= icon('mail') ?>Me contacter</a>
    </div>
</aside>
