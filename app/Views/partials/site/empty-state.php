<?php
/**
 * État vide (liste sans résultat). Fonctionne aussi dans l'administration.
 *
 * Variables :
 *   $title   string  titre (obligatoire)
 *   $text    string  (optionnel) explication
 *   $comment string  (optionnel) commentaire mono au-dessus, ex. '-- 0 résultat'
 *   $icon    string  (optionnel) nom d'icône (helper icon()), défaut 'folder'
 *   $actions string  (optionnel) HTML DÉJÀ SÛR (boutons)
 */

declare(strict_types=1);

$title   = (string) ($title ?? '');
$text    = (string) ($text ?? '');
$comment = (string) ($comment ?? '');
$iconName = (string) ($icon ?? 'folder');
$actions = (string) ($actions ?? '');
?>
<div class="empty-state">
    <?= icon($iconName, 32, 'icon empty-state__icon') ?>
<?php if ($comment !== ''): ?>
    <p class="empty-state__comment" aria-hidden="true"><?= e($comment) ?></p>
<?php endif; ?>
    <p class="empty-state__title"><?= e($title) ?></p>
<?php if ($text !== ''): ?>
    <p class="empty-state__text"><?= e($text) ?></p>
<?php endif; ?>
<?php if ($actions !== ''): ?>
    <div class="empty-state__actions cluster"><?= $actions ?></div>
<?php endif; ?>
</div>
