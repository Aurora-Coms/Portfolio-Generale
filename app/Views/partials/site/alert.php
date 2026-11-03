<?php
/**
 * Alerte (message d'information, de succès, d'avertissement ou d'erreur).
 *
 * Variables :
 *   $type    string  'success' | 'error' | 'warning' | 'info' (défaut 'info')
 *   $message string  texte simple (échappé ici)
 *   $title   string  (optionnel) titre en gras au-dessus du message
 *   $html    string  (optionnel) contenu HTML DÉJÀ SÛR (md(), partial()…) affiché après le message
 *   $role    string  (optionnel) 'status' | 'alert' | '' (par défaut : alert pour error/warning, status sinon ;
 *                    passer '' pour une alerte statique non annoncée)
 */

declare(strict_types=1);

$type    = isset($type) && in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info';
$message = (string) ($message ?? '');
$title   = (string) ($title ?? '');
$html    = (string) ($html ?? '');
$role    = isset($role) ? (string) $role : (in_array($type, ['error', 'warning'], true) ? 'alert' : 'status');

$iconSvg = match ($type) {
    'success' => icon('check', 20),
    'error'   => icon('close', 20),
    'warning' => '<svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3 2 20h20z"/><path d="M12 10v4"/><path d="M12 17h.01"/></svg>',
    default   => icon('info', 20),
};
$prefix = ['success' => 'Succès', 'error' => 'Erreur', 'warning' => 'Attention', 'info' => 'Information'][$type];
?>
<div class="alert alert--<?= e($type) ?>"<?= $role !== '' ? ' role="' . e($role) . '"' : '' ?>>
    <?= $iconSvg ?>
    <div class="alert__body">
<?php if ($title !== ''): ?>
        <strong class="alert__title"><?= e($title) ?></strong>
<?php endif; ?>
<?php if ($message !== ''): ?>
        <p><?php if ($title === ''): ?><span class="sr-only"><?= e($prefix) ?> : </span><?php endif; ?><?= e($message) ?></p>
<?php endif; ?>
<?= $html ?>
    </div>
</div>
