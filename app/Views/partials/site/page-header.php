<?php
/**
 * En-tête d'une page intérieure (h1 unique de la page).
 *
 * Variables :
 *   $title      string  titre h1 (obligatoire)
 *   $comment    string  (optionnel) commentaire au-dessus, ex. '-- parcours.md'
 *   $lead       string  (optionnel) paragraphe d'introduction (texte simple)
 *   $breadcrumb list    (optionnel) éléments du fil d'Ariane (voir partial site/breadcrumb)
 */

declare(strict_types=1);

$title      = (string) ($title ?? '');
$comment    = (string) ($comment ?? '');
$lead       = (string) ($lead ?? '');
$breadcrumb = isset($breadcrumb) && is_array($breadcrumb) ? $breadcrumb : [];
?>
<header class="page-head">
<?php if ($breadcrumb !== []): ?>
<?= partial('site/breadcrumb', ['items' => $breadcrumb]) ?>
<?php endif; ?>
<?php if ($comment !== ''): ?>
    <p class="page-head__comment" aria-hidden="true"><?= e($comment) ?></p>
<?php endif; ?>
    <h1 class="page-head__title"><?= e($title) ?></h1>
<?php if ($lead !== ''): ?>
    <p class="page-head__lead"><?= e($lead) ?></p>
<?php endif; ?>
</header>
