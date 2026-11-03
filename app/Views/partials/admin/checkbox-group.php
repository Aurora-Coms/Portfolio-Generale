<?php
/**
 * Groupe de cases à cocher (fieldset + legend), ex. catégories ou tags d'un projet.
 * Les cases envoient name[] (liste d'identifiants).
 *
 * Variables :
 *   $name     string  nom du champ SANS [], ex. 'category_ids' (obligatoire)
 *   $legend   string  titre du groupe (obligatoire)
 *   $options  list    [['value' => 3, 'label' => 'Sci-fi', 'section' => 'game' (optionnel)], …]
 *   $checked  list    valeurs cochées, ex. $project['category_ids'] ou old()
 *   $error    string  (optionnel) message d'erreur
 *   $hint     string  (optionnel) aide
 *   $id       string  (optionnel) id du fieldset, défaut 'f-{name}'
 *   $columns  bool    (optionnel) disposition en colonnes, défaut true
 *   $sectionSelect string (optionnel) sélecteur CSS du <select> de section (ex. '#f-section') :
 *                    admin.js n'affiche alors que les cases dont data-section correspond.
 *   $empty    string  (optionnel) texte si aucune option, défaut 'Aucun élément pour le moment.'
 */

declare(strict_types=1);

$name    = (string) ($name ?? '');
$legend  = (string) ($legend ?? '');
$options = isset($options) && is_array($options) ? $options : [];
$checked = array_map('strval', isset($checked) && is_array($checked) ? $checked : []);
$error   = (string) ($error ?? '');
$hint    = (string) ($hint ?? '');
$id      = (string) ($id ?? ('f-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name)));
$columns = (bool) ($columns ?? true);
$sectionSelect = (string) ($sectionSelect ?? '');
$empty   = (string) ($empty ?? 'Aucun élément pour le moment.');

$describedBy = trim(($hint !== '' ? $id . '-aide ' : '') . ($error !== '' ? $id . '-erreur' : ''));
?>
<fieldset class="field" id="<?= e($id) ?>"<?= $describedBy !== '' ? ' aria-describedby="' . e($describedBy) . '"' : '' ?><?= $sectionSelect !== '' ? ' data-section-filter="' . e($sectionSelect) . '"' : '' ?>>
    <legend><?= e($legend) ?></legend>
<?php if ($hint !== ''): ?>
    <p class="field__hint" id="<?= e($id) ?>-aide"><?= e($hint) ?></p>
<?php endif; ?>
<?php if ($options === []): ?>
    <p class="field__hint"><?= e($empty) ?></p>
<?php else: ?>
    <ul class="check-group<?= $columns ? ' check-group--cols' : '' ?>">
<?php foreach ($options as $i => $opt):
    $value   = (string) ($opt['value'] ?? '');
    $section = (string) ($opt['section'] ?? '');
    $optId   = $id . '-' . $i;
?>
        <li class="check"<?= $section !== '' ? ' data-section="' . e($section) . '"' : '' ?>>
            <input class="check__input" type="checkbox" id="<?= e($optId) ?>" name="<?= e($name) ?>[]" value="<?= e($value) ?>"<?= in_array($value, $checked, true) ? ' checked' : '' ?>>
            <label class="check__label" for="<?= e($optId) ?>"><?= e((string) ($opt['label'] ?? $value)) ?></label>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="field__error" id="<?= e($id) ?>-erreur"><?= icon('info', 16) ?><span><?= e($error) ?></span></p>
<?php endif; ?>
</fieldset>
