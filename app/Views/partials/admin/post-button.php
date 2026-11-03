<?php
/**
 * Bouton qui envoie une action POST (formulaire en ligne avec jeton CSRF) :
 * suppression, publication, mise en avant, déplacement… Jamais un lien GET.
 *
 * Variables :
 *   $action   string  URL de l'action, ex. '/admin/tags/12/supprimer' (obligatoire)
 *   $label    string  texte du bouton (obligatoire ; nom accessible si $iconOnly)
 *   $variant  string  (optionnel) 'secondary' (défaut) | 'primary' | 'danger' | 'ghost' | 'code'
 *   $size     string  (optionnel) 'sm' (défaut) | 'md'
 *   $icon     string  (optionnel) nom d'icône (helper icon())
 *   $iconOnly bool    (optionnel) bouton icône seule (le libellé devient aria-label), défaut false
 *   $confirm  string  (optionnel) message de confirmation (data-confirm, admin.js)
 *   $fields   array   (optionnel) champs cachés supplémentaires ['nom' => 'valeur']
 *   $pressed  ?bool   (optionnel) état d'un bouton bascule (aria-pressed)
 */

declare(strict_types=1);

$action   = (string) ($action ?? '');
$label    = (string) ($label ?? '');
$variant  = in_array($variant ?? '', ['primary', 'secondary', 'danger', 'ghost', 'code'], true) ? (string) $variant : 'secondary';
$size     = ($size ?? 'sm') === 'md' ? '' : ' btn--sm';
$iconName = (string) ($icon ?? '');
$iconOnly = (bool) ($iconOnly ?? false) && $iconName !== '';
$confirm  = (string) ($confirm ?? '');
$fields   = isset($fields) && is_array($fields) ? $fields : [];
$pressed  = isset($pressed) && is_bool($pressed) ? $pressed : null;

$class = 'btn btn--' . $variant . $size . ($iconOnly ? ' btn--icon' : '');
?>
<form class="inline-form" method="post" action="<?= e($action) ?>"<?= $confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '' ?>>
    <?= csrf_field() ?>
<?php foreach ($fields as $name => $value): ?>
    <input type="hidden" name="<?= e((string) $name) ?>" value="<?= e((string) $value) ?>">
<?php endforeach; ?>
    <button type="submit" class="<?= e($class) ?>"<?= $iconOnly ? ' aria-label="' . e($label) . '" title="' . e($label) . '"' : '' ?><?= $pressed !== null ? ' aria-pressed="' . ($pressed ? 'true' : 'false') . '"' : '' ?>><?= $iconName !== '' ? icon($iconName, 16) : '' ?><?= $iconOnly ? '' : '<span>' . e($label) . '</span>' ?></button>
</form>
