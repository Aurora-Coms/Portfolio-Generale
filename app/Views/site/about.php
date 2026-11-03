<?php
/**
 * À propos.
 *
 * Variables (AboutController::index) :
 *   $aboutText   string  réglage about_text (Markdown restreint)
 *   $interests   list    Interest::all()
 *   $facts       list    bloc « en bref » : [['label' => …, 'value' => …], …]
 *   $internship  string  « du 18 mai au 26 juin 2026 » si un stage est recherché, sinon ''
 *   $cvAvailable bool    CV (PDF) disponible
 */

declare(strict_types=1);

$aboutText   = (string) ($aboutText ?? '');
$interests   = isset($interests) && is_array($interests) ? $interests : [];
$facts       = isset($facts) && is_array($facts) ? $facts : [];
$internship  = (string) ($internship ?? '');
$cvAvailable = (bool) ($cvAvailable ?? false);
?>
<?= partial('site/page-header', [
    'comment' => '-- a-propos.md',
    'title'   => 'À propos',
    'lead'    => setting('hero_title'),
]) ?>

<div class="detail-layout">
    <div class="detail-main">
<?php if (trim($aboutText) !== ''): ?>
        <div class="prose"><?= md($aboutText) ?></div>
<?php else: ?>
        <p class="text-soft">Présentation en cours de rédaction.</p>
<?php endif; ?>
    </div>
    <div class="detail-aside stack">
<?= partial('site/facts', ['items' => $facts]) ?>
<?php if ($cvAvailable): ?>
        <a class="btn btn--secondary btn--block" href="/cv"><?= icon('download') ?>Télécharger mon CV (PDF)</a>
<?php endif; ?>
    </div>
</div>

<?php if ($interests !== []): ?>
<section class="section" aria-labelledby="titre-interets">
<?= partial('site/section-head', [
    'comment' => '-- interets/',
    'title'   => 'Centres d\'intérêt',
    'id'      => 'titre-interets',
]) ?>
    <div class="grid grid--2">
<?php foreach ($interests as $interest): ?>
<?= partial('site/interest-card', ['interest' => $interest, 'headingLevel' => 3]) ?>
<?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<aside class="card cta" aria-labelledby="titre-contact">
    <div class="cta__body">
        <p class="cta__comment" aria-hidden="true">-- contact.sh</p>
        <h2 class="cta__title" id="titre-contact"><?= $internship !== '' ? 'Un stage à me proposer ?' : 'Envie d\'échanger ?' ?></h2>
        <p class="cta__text"><?= $internship !== ''
            ? e('Je recherche un stage en développement informatique ou en réseaux ' . $internship . '. Écrivez-moi, je vous répondrai dès que possible.')
            : e('Une question sur un projet ou sur mon parcours : écrivez-moi via le formulaire de contact.') ?></p>
    </div>
    <div class="cta__actions btn-row">
<?php if ($internship !== ''): ?>
        <a class="btn btn--primary" href="/contact?sujet=stage"><?= icon('mail') ?>Proposer un stage</a>
<?php else: ?>
        <a class="btn btn--primary" href="/contact"><?= icon('mail') ?>Me contacter</a>
<?php endif; ?>
<?php if ($cvAvailable): ?>
        <a class="btn btn--secondary" href="/cv"><?= icon('download') ?>CV (PDF)</a>
<?php endif; ?>
    </div>
</aside>
