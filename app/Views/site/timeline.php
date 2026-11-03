<?php
/**
 * Parcours : timeline complète.
 *
 * Variables (TimelineController::index) :
 *   $entries     list  TimelineEntry::published()
 *   $facts       list  bloc « en bref » (partial site/facts)
 *   $cvAvailable bool  CV (PDF) disponible
 */

declare(strict_types=1);

$entries     = isset($entries) && is_array($entries) ? $entries : [];
$facts       = isset($facts) && is_array($facts) ? $facts : [];
$cvAvailable = (bool) ($cvAvailable ?? false);
?>
<?= partial('site/page-header', [
    'comment' => '-- parcours.md',
    'title'   => 'Parcours',
    'lead'    => 'Formation, stages et expériences, du plus récent au plus ancien.',
]) ?>

<div class="detail-layout">
    <div class="detail-main">
<?php if ($entries === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 entrée',
    'title'   => 'Parcours en cours de rédaction',
    'text'    => 'Les étapes de mon parcours seront bientôt détaillées ici.',
    'icon'    => 'book',
]) ?>
<?php else: ?>
<?= partial('site/timeline', ['entries' => $entries, 'headingLevel' => 2]) ?>
<?php endif; ?>
    </div>
    <div class="detail-aside stack">
<?= partial('site/facts', ['items' => $facts]) ?>
<?php if ($cvAvailable): ?>
        <a class="btn btn--secondary btn--block" href="/cv"><?= icon('download') ?>Télécharger mon CV (PDF)</a>
<?php endif; ?>
    </div>
</div>

<aside class="card cta" aria-labelledby="titre-suite">
    <div class="cta__body">
        <p class="cta__comment" aria-hidden="true">-- et ensuite ?</p>
        <h2 class="cta__title" id="titre-suite">Compétences et projets</h2>
        <p class="cta__text">Ce que j'ai appris en formation et en stage se retrouve dans mes compétences et dans mes projets.</p>
    </div>
    <div class="cta__actions btn-row">
        <a class="btn btn--secondary" href="/competences">Mes compétences<?= icon('arrow') ?></a>
        <a class="btn btn--primary" href="/contact"><?= icon('mail') ?>Me contacter</a>
    </div>
</aside>
