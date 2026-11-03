<?php
/**
 * Champ de formulaire complet : libellé visible, champ, aide, erreur liée
 * (aria-describedby + aria-invalid). Fonctionne sur le site ET dans l'administration.
 *
 * Variables :
 *   $name      string  nom du champ (obligatoire)
 *   $label     string  libellé visible (obligatoire)
 *   $type      string  (optionnel) text (défaut) | email | url | password | number | date | tel | search
 *                      | textarea | select | checkbox | file
 *   $value     string  (optionnel) valeur (ex. old($old, 'name')) ; pour checkbox : '1' = coché
 *   $error     string  (optionnel) message d'erreur (ex. $errors['name'] ?? '')
 *   $hint      string  (optionnel) aide sous le libellé
 *   $id        string  (optionnel) id du champ, défaut 'f-{name}'
 *   $required  bool    (optionnel) champ obligatoire (attribut required + astérisque)
 *   $optional  bool    (optionnel) afficher « (facultatif) » après le libellé
 *   $maxlength int     (optionnel) longueur maximale (attribut maxlength)
 *   $counter   bool    (optionnel) compteur de caractères (data-maxlength, nécessite $maxlength)
 *   $options   array   (select) ['valeur' => 'Libellé', …]
 *   $placeholder string (optionnel) exemple de saisie (jamais à la place du libellé)
 *   $rows      int     (textarea) nombre de lignes, défaut 8
 *   $class     string  (optionnel) classe(s) en plus sur le champ, ex. 'textarea--lg input--mono'
 *   $attrs     array   (optionnel) attributs en plus ['autocomplete' => 'email', 'min' => '1',
 *                      'data-slug-source' => '#f-title', 'multiple' => true, 'accept' => 'image/*'…]
 *   $after     string  (optionnel) HTML DÉJÀ SÛR ajouté après le champ (ex. partial('admin/markdown-help'))
 */

declare(strict_types=1);

$name     = (string) ($name ?? '');
$label    = (string) ($label ?? '');
$type     = (string) ($type ?? 'text');
$value    = (string) ($value ?? '');
$error    = (string) ($error ?? '');
$hint     = (string) ($hint ?? '');
$id       = (string) ($id ?? ('f-' . trim((string) preg_replace('/[^a-z0-9_-]+/i', '-', $name), '-')));
$required = (bool) ($required ?? false);
$optional = (bool) ($optional ?? false);
$maxlength = (int) ($maxlength ?? 0);
$counter  = (bool) ($counter ?? false) && $maxlength > 0;
$options  = isset($options) && is_array($options) ? $options : [];
$placeholder = (string) ($placeholder ?? '');
$rows     = max(2, (int) ($rows ?? 8));
$class    = (string) ($class ?? '');
$attrs    = isset($attrs) && is_array($attrs) ? $attrs : [];
$after    = (string) ($after ?? '');

$hintId  = $id . '-aide';
$errorId = $id . '-erreur';
$describedBy = trim(($hint !== '' ? $hintId . ' ' : '') . ($error !== '' ? $errorId : ''));

// Attributs communs, tous échappés.
$common = ['id' => $id, 'name' => $name];
if ($required) {
    $common['required'] = true;
}
if ($maxlength > 0 && $type !== 'checkbox' && $type !== 'file') {
    $common['maxlength'] = (string) $maxlength;
    if ($counter) {
        $common['data-maxlength'] = (string) $maxlength;
    }
}
if ($placeholder !== '') {
    $common['placeholder'] = $placeholder;
}
if ($describedBy !== '') {
    $common['aria-describedby'] = $describedBy;
}
if ($error !== '') {
    $common['aria-invalid'] = 'true';
}
$all = $common + $attrs;

$render = static function (array $list): string {
    $html = '';
    foreach ($list as $k => $v) {
        // Nom d'attribut simple uniquement ; jamais de gestionnaire on* ni de style (CSP).
        if ($v === false || $v === null || !preg_match('/^[a-z][a-z0-9-]*$/i', (string) $k)
            || preg_match('/^(on|style$)/i', (string) $k)) {
            continue;
        }
        $html .= $v === true ? ' ' . $k : ' ' . $k . '="' . e((string) $v) . '"';
    }
    return $html;
};

$labelExtra = ($required ? '<span class="field__req" aria-hidden="true">*</span>' : '')
    . ($optional ? ' <span class="field__opt">(facultatif)</span>' : '');
?>
<?php if ($type === 'checkbox'): ?>
<div class="field">
    <div class="check">
        <input class="check__input" type="checkbox" value="1"<?= $value === '1' ? ' checked' : '' ?><?= $render($all) ?>>
        <label class="check__label" for="<?= e($id) ?>"><?= e($label) ?><?= $labelExtra ?></label>
    </div>
<?php else: ?>
<div class="field">
    <label class="field__label" for="<?= e($id) ?>"><?= e($label) ?><?= $labelExtra ?></label>
<?php endif; ?>
<?php if ($hint !== ''): ?>
    <p class="field__hint" id="<?= e($hintId) ?>"><?= e($hint) ?></p>
<?php endif; ?>
<?php if ($type === 'textarea'): ?>
    <textarea class="<?= e(trim('textarea ' . $class)) ?>" rows="<?= $rows ?>"<?= $render($all) ?>><?= e($value) ?></textarea>
<?php elseif ($type === 'select'): ?>
    <select class="<?= e(trim('select ' . $class)) ?>"<?= $render($all) ?>>
<?php foreach ($options as $optValue => $optLabel): ?>
        <option value="<?= e((string) $optValue) ?>"<?= (string) $optValue === $value ? ' selected' : '' ?>><?= e((string) $optLabel) ?></option>
<?php endforeach; ?>
    </select>
<?php elseif ($type === 'file'): ?>
    <input class="<?= e(trim('input ' . $class)) ?>" type="file"<?= $render($all) ?>>
<?php elseif ($type !== 'checkbox'): ?>
    <input class="<?= e(trim('input ' . $class)) ?>" type="<?= e($type) ?>" value="<?= e($value) ?>"<?= $render($all) ?>>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="field__error" id="<?= e($errorId) ?>"><?= icon('info', 16) ?><span><?= e($error) ?></span></p>
<?php endif; ?>
<?= $after ?>
</div>
