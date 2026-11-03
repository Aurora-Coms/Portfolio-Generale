<?php
/**
 * Choix d'une icône parmi une liste fixe : boutons radio dont chaque libellé
 * montre l'aperçu réel de l'icône (helper icon()). Aucun JavaScript nécessaire.
 * Utilisé par les catégories de compétences et les centres d'intérêt.
 *
 * Variables :
 *   $name     string  nom du champ (obligatoire), ex. 'icon'
 *   $legend   string  titre du groupe (obligatoire)
 *   $options  array   ['network' => 'Réseau', …] : nom d'icône => libellé lisible
 *   $value    string  icône choisie (ex. old($old, 'icon'))
 *   $error    string  (optionnel) message d'erreur
 *   $hint     string  (optionnel) aide
 *   $required bool    (optionnel) choix obligatoire (astérisque)
 *   $id       string  (optionnel) id du fieldset, défaut 'f-{name}' ; radios 'f-{name}-{icône}'
 */

declare(strict_types=1);

$name     = (string) ($name ?? 'icon');
$legend   = (string) ($legend ?? 'Icône');
$options  = isset($options) && is_array($options) ? $options : [];
$value    = (string) ($value ?? '');
$error    = (string) ($error ?? '');
$hint     = (string) ($hint ?? '');
$required = (bool) ($required ?? false);
$id       = (string) ($id ?? ('f-' . trim((string) preg_replace('/[^a-z0-9_-]+/i', '-', $name), '-')));

$describedBy = trim(($hint !== '' ? $id . '-aide ' : '') . ($error !== '' ? $id . '-erreur' : ''));
?>
<fieldset class="field icon-picker" id="<?= e($id) ?>"<?= $describedBy !== '' ? ' aria-describedby="' . e($describedBy) . '"' : '' ?>>
    <legend><?= e($legend) ?><?= $required ? '<span class="field__req" aria-hidden="true">*</span>' : '' ?></legend>
<?php if ($hint !== ''): ?>
    <p class="field__hint" id="<?= e($id) ?>-aide"><?= e($hint) ?></p>
<?php endif; ?>
    <ul class="icon-picker__list">
<?php foreach ($options as $iconName => $iconLabel):
    $iconName = (string) $iconName;
    $optId    = $id . '-' . $iconName;
?>
        <li class="icon-picker__item">
            <input class="icon-picker__input" type="radio" id="<?= e($optId) ?>" name="<?= e($name) ?>" value="<?= e($iconName) ?>"<?= $iconName === $value ? ' checked' : '' ?><?= $required ? ' required' : '' ?><?= $error !== '' ? ' aria-invalid="true"' : '' ?>>
            <label class="icon-picker__label" for="<?= e($optId) ?>">
                <span class="icon-picker__preview"><?= icon($iconName, 22) ?></span>
                <span class="icon-picker__name"><?= e((string) $iconLabel) ?></span>
                <span class="icon-picker__code" aria-hidden="true"><?= e($iconName) ?></span>
            </label>
        </li>
<?php endforeach; ?>
    </ul>
<?php if ($error !== ''): ?>
    <p class="field__error" id="<?= e($id) ?>-erreur"><?= icon('info', 16) ?><span><?= e($error) ?></span></p>
<?php endif; ?>
</fieldset>
