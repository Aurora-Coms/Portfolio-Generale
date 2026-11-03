<?php
/**
 * Interrupteur (case à cocher avec role="switch"). Un champ caché envoie « 0 »
 * quand l'interrupteur est éteint : le contrôleur reçoit toujours 0 ou 1.
 *
 * Variables :
 *   $name    string  nom du champ (obligatoire), ex. 'is_published'
 *   $label   string  libellé (obligatoire)
 *   $checked bool    état initial
 *   $id      string  (optionnel) id, défaut 'f-{name}'
 *   $hint    string  (optionnel) aide sous le libellé
 *   $value   string  (optionnel) valeur envoyée quand activé, défaut '1'
 */

declare(strict_types=1);

$name    = (string) ($name ?? '');
$label   = (string) ($label ?? '');
$checked = (bool) ($checked ?? false);
$id      = (string) ($id ?? ('f-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name)));
$hint    = (string) ($hint ?? '');
$value   = (string) ($value ?? '1');
?>
<div class="field">
    <input type="hidden" name="<?= e($name) ?>" value="0">
    <label class="switch" for="<?= e($id) ?>">
        <input class="switch__input" type="checkbox" role="switch" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>"<?= $checked ? ' checked' : '' ?><?= $hint !== '' ? ' aria-describedby="' . e($id) . '-aide"' : '' ?>>
        <span class="switch__label"><?= e($label) ?></span>
    </label>
<?php if ($hint !== ''): ?>
    <p class="field__hint" id="<?= e($id) ?>-aide"><?= e($hint) ?></p>
<?php endif; ?>
</div>
