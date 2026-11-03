<?php
/**
 * Formulaire d'une compétence (création et modification).
 *
 * @var array<string, mixed>|null  $skill       compétence modifiée (null = création)
 * @var array<string, mixed>       $old
 * @var array<string, string>      $errors
 * @var list<array<string, mixed>> $categories  SkillCategory::all()
 * @var string                     $action
 */

declare(strict_types=1);

$isNew = $skill === null;

$categoryOptions = ['' => 'Choisir une catégorie…'];
foreach ($categories as $category) {
    $categoryOptions[(string) (int) $category['id']] = (string) $category['name'];
}

$levelOptions = [];
for ($n = 1; $n <= 5; $n++) {
    $levelOptions[(string) $n] = $n . ' · ' . level_label($n);
}
?>
<?= partial('admin/page-header', [
    'back'    => ['href' => '/admin/competences', 'label' => 'Compétences'],
    'comment' => $isNew ? '-- competences/nouveau' : '-- competences/' . (int) $skill['id'],
    'title'   => $isNew ? 'Nouvelle compétence' : 'Modifier « ' . $skill['name'] . ' »',
]) ?>

<?php if ($categories === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 catégorie',
    'icon'    => 'chip',
    'title'   => 'Aucune catégorie de compétences',
    'text'    => 'Créez d\'abord une catégorie : chaque compétence doit appartenir à l\'une d\'elles.',
    'actions' => '<a class="btn btn--primary btn--sm" href="/admin/competences/categories/nouveau">Créer une catégorie</a>',
]) ?>
<?php else: ?>
<form class="form card card--pad" method="post" action="<?= e($action) ?>" novalidate data-focus-error>
    <?= csrf_field() ?>
<?php if ($errors !== []): ?>
    <?= partial('site/alert', ['type' => 'error', 'title' => 'La compétence n\'a pas été enregistrée', 'message' => 'Corrigez les champs signalés ci-dessous.']) ?>
<?php endif; ?>

    <div class="form-section">
        <div class="form-row form-row--2">
            <?= partial('site/field', [
                'name' => 'name', 'label' => 'Nom', 'required' => true, 'maxlength' => 80, 'counter' => true,
                'placeholder' => 'Configuration de switchs Cisco', 'value' => old($old, 'name'), 'error' => $errors['name'] ?? '',
            ]) ?>
            <?= partial('site/field', [
                'name' => 'category_id', 'type' => 'select', 'label' => 'Catégorie', 'required' => true,
                'options' => $categoryOptions, 'value' => old($old, 'category_id'), 'error' => $errors['category_id'] ?? '',
            ]) ?>
        </div>
        <div class="form-row form-row--2">
            <?= partial('site/field', [
                'name' => 'level', 'type' => 'select', 'label' => 'Niveau', 'required' => true,
                'hint' => 'Soyez honnête : un niveau réaliste inspire confiance.',
                'options' => $levelOptions, 'value' => old($old, 'level', '3'), 'error' => $errors['level'] ?? '',
            ]) ?>
            <?= partial('site/field', [
                'name' => 'position', 'type' => 'number', 'label' => 'Ordre', 'optional' => true,
                'hint' => 'Ordre dans la catégorie (0 en premier).',
                'value' => old($old, 'position', '0'), 'error' => $errors['position'] ?? '',
                'attrs' => ['min' => '0', 'max' => '9999', 'step' => '1', 'inputmode' => 'numeric'],
            ]) ?>
        </div>
        <?= partial('site/field', [
            'name' => 'description', 'type' => 'textarea', 'label' => 'Description', 'optional' => true,
            'maxlength' => 255, 'counter' => true, 'rows' => 3,
            'hint' => 'Une phrase : ce que vous savez faire concrètement. Texte simple.',
            'value' => old($old, 'description'), 'error' => $errors['description'] ?? '',
        ]) ?>
    </div>

    <div class="form__actions">
        <button class="btn btn--primary" type="submit"><?= icon('check', 16) ?>Enregistrer</button>
        <a class="btn btn--secondary" href="/admin/competences">Annuler</a>
    </div>
</form>
<?php endif; ?>
