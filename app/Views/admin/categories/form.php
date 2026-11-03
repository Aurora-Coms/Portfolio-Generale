<?php
/**
 * Formulaire de catégorie (création et modification).
 *
 * Variables :
 *   $category ?array  catégorie modifiée (+ project_count) ou null
 *   $old      array   valeurs à afficher
 *   $errors   array   champ => message
 *   $sections array   valeur => libellé
 */

declare(strict_types=1);

$category = isset($category) && is_array($category) ? $category : null;
$old      = isset($old) && is_array($old) ? $old : [];
$errors   = isset($errors) && is_array($errors) ? $errors : [];
$sections = isset($sections) && is_array($sections) ? $sections : [];

$isEdit = $category !== null;
$id     = $isEdit ? (int) $category['id'] : 0;
$count  = $isEdit ? (int) ($category['project_count'] ?? 0) : 0;
$err    = static fn (string $field): string => (string) ($errors[$field] ?? '');
?>
<?= partial('admin/page-header', [
    'comment' => $isEdit ? '-- categories/' . (string) $category['slug'] : '-- categories/nouveau',
    'title'   => $isEdit ? 'Modifier la catégorie' : 'Nouvelle catégorie',
    'lead'    => $isEdit ? (string) $category['name'] : 'Une catégorie regroupe des projets d\'une même section (ex. Roblox, Réseau).',
    'back'    => ['href' => '/admin/categories', 'label' => 'Catégories'],
]) ?>

<form class="form card card--pad" method="post" action="<?= e($isEdit ? '/admin/categories/' . $id : '/admin/categories') ?>" novalidate data-focus-error>
    <?= csrf_field() ?>
<?php if ($errors !== []): ?>
    <?= partial('site/alert', ['type' => 'error', 'title' => 'La catégorie n\'a pas été enregistrée', 'message' => 'Corrigez les champs signalés ci-dessous.']) ?>
<?php endif; ?>
    <div class="form-section">
        <div class="form-row form-row--2">
            <?= partial('site/field', ['name' => 'section', 'type' => 'select', 'label' => 'Section', 'required' => true,
                'options' => $sections, 'value' => old($old, 'section', 'game'), 'error' => $err('section'),
                'hint' => $count > 0 ? 'Utilisée par ' . $count . ' projet(s) : la section ne peut pas changer.' : '']) ?>
            <?= partial('site/field', ['name' => 'position', 'type' => 'number', 'label' => 'Ordre d\'affichage', 'optional' => true,
                'hint' => 'De 0 à 999 : les plus petits d\'abord dans les filtres.',
                'value' => old($old, 'position', '0'), 'error' => $err('position'),
                'attrs' => ['min' => '0', 'max' => '999', 'step' => '1', 'inputmode' => 'numeric']]) ?>
        </div>
        <div class="form-row form-row--2">
            <?= partial('site/field', ['name' => 'name', 'label' => 'Nom', 'required' => true, 'maxlength' => 80, 'counter' => true,
                'value' => old($old, 'name'), 'error' => $err('name')]) ?>
            <?= partial('site/field', ['name' => 'slug', 'label' => 'Adresse (slug)', 'optional' => true, 'maxlength' => 80, 'class' => 'input--mono',
                'hint' => 'Utilisée dans l\'adresse du filtre (?categorie=…). Vide : créée depuis le nom.',
                'value' => old($old, 'slug'), 'error' => $err('slug'),
                'attrs' => ['data-slug-source' => '#f-name', 'autocapitalize' => 'none', 'spellcheck' => 'false', 'autocomplete' => 'off']]) ?>
        </div>
        <?= partial('site/field', ['name' => 'description', 'type' => 'textarea', 'rows' => 3, 'label' => 'Description', 'optional' => true,
            'maxlength' => 255, 'counter' => true, 'hint' => 'Courte phrase de présentation (texte simple).',
            'value' => old($old, 'description'), 'error' => $err('description')]) ?>
    </div>
    <div class="form__actions">
        <button class="btn btn--primary" type="submit" data-busy-label="Enregistrement…"><?= icon('check', 16) ?><?= $isEdit ? 'Enregistrer' : 'Créer la catégorie' ?></button>
        <a class="btn btn--secondary" href="/admin/categories">Annuler</a>
    </div>
</form>

<?php if ($isEdit): ?>
<section class="card card--pad stack danger-zone" aria-labelledby="titre-suppression">
    <h2 class="section-title" id="titre-suppression">Supprimer la catégorie</h2>
    <p class="text-soft"><?= e($count > 0
        ? 'Elle sera retirée des ' . $count . ' projet(s) qui l\'utilisent. Les projets eux-mêmes ne sont pas supprimés.'
        : 'Aucun projet n\'utilise cette catégorie.') ?></p>
    <div>
        <?= partial('admin/post-button', [
            'action'  => '/admin/categories/' . $id . '/supprimer',
            'label'   => 'Supprimer cette catégorie',
            'icon'    => 'trash',
            'variant' => 'danger',
            'size'    => 'md',
            'confirm' => 'Supprimer la catégorie « ' . (string) $category['name'] . ' » ?'
                . ($count > 0 ? ' Elle sera retirée des ' . $count . ' projet(s) qui l\'utilisent (les projets ne sont pas supprimés).' : ''),
        ]) ?>
    </div>
</section>
<?php endif; ?>
