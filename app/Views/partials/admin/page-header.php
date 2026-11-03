<?php
/**
 * En-tête d'une page d'administration : h1 unique + zone d'actions.
 *
 * Variables :
 *   $title   string  titre h1 (obligatoire)
 *   $comment string  (optionnel) commentaire mono au-dessus, ex. '-- projets/'
 *   $lead    string  (optionnel) phrase d'explication sous le titre (texte simple)
 *   $back    array   (optionnel) lien de retour ['href' => '/admin/projets', 'label' => 'Projets']
 *   $actions string  (optionnel) HTML DÉJÀ SÛR : boutons (liens .btn ou partial('admin/post-button'))
 */

declare(strict_types=1);

$title   = (string) ($title ?? '');
$comment = (string) ($comment ?? '');
$lead    = (string) ($lead ?? '');
$back    = isset($back) && is_array($back) && isset($back['href']) ? $back : null;
$actions = (string) ($actions ?? '');
?>
<header class="page-header">
    <div class="page-header__main">
<?php if ($back !== null): ?>
        <a class="page-header__back" href="<?= e((string) $back['href']) ?>"><?= icon('arrow-left', 16) ?><?= e((string) ($back['label'] ?? 'Retour')) ?></a>
<?php endif; ?>
<?php if ($comment !== ''): ?>
        <p class="page-header__comment" aria-hidden="true"><?= e($comment) ?></p>
<?php endif; ?>
        <h1 class="page-header__title"><?= e($title) ?></h1>
<?php if ($lead !== ''): ?>
        <p class="page-header__lead"><?= e($lead) ?></p>
<?php endif; ?>
    </div>
<?php if ($actions !== ''): ?>
    <div class="page-header__actions"><?= $actions ?></div>
<?php endif; ?>
</header>
