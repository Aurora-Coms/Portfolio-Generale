<?php
/**
 * Formulaire d'une compétence (création et modification).
 *
 * @var array<string, mixed>|null  $skill       compétence modifiée (null = création)
 * @var array<string, mixed>       $old
 * @var array<string, string>      $errors
 * @var list<array<string, mixed>> $categories  SkillCategory::all() (triées par thème, avec theme_name)
 * @var string                     $action
 */

declare(strict_types=1);

$isNew = $skill === null;

// Sous-catégories triées par thème : « Thème · Sous-catégorie » (le nom seul
// si la sous-catégorie porte le nom de son thème).
$categoryOptions = ['' => 'Choisir une sous-catégorie…'];
foreach ($categories as $category) {
    $name  = (string) $category['name'];
    $theme = (string) ($category['theme_name'] ?? '');
    $categoryOptions[(string) (int) $category['id']] = $theme !== '' && $theme !== $name ? $theme . ' · ' . $name : $name;
}

$levelOptions = ['' => 'Sans niveau (pas de barre)'];
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
    'comment' => '-- 0 sous-catégorie',
    'icon'    => 'chip',
    'title'   => 'Aucune sous-catégorie de compétences',
    'text'    => 'Créez d\'abord une sous-catégorie : chaque compétence doit appartenir à l\'une d\'elles.',
    'actions' => '<a class="btn btn--primary btn--sm" href="/admin/competences/categories/nouveau">Créer une sous-catégorie</a>',
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
                'name' => 'category_id', 'type' => 'select', 'label' => 'Sous-catégorie', 'required' => true,
                'options' => $categoryOptions, 'value' => old($old, 'category_id'), 'error' => $errors['category_id'] ?? '',
            ]) ?>
        </div>
        <div class="form-row form-row--2">
            <?= partial('site/field', [
                'name' => 'level', 'type' => 'select', 'label' => 'Niveau', 'optional' => true,
                'hint' => 'Soyez honnête : un niveau réaliste inspire confiance. « Sans niveau » convient au savoir-être et aux atouts.',
                'options' => $levelOptions, 'value' => old($old, 'level'), 'error' => $errors['level'] ?? '',
            ]) ?>
            <?= partial('site/field', [
                'name' => 'level_label', 'label' => 'Libellé du niveau', 'optional' => true,
                'maxlength' => 40, 'counter' => true, 'placeholder' => 'A2',
                'hint' => 'Remplace le libellé standard (Notions, Bases…), ex. « A2 » ou « Langue maternelle » pour une langue.',
                'value' => old($old, 'level_label'), 'error' => $errors['level_label'] ?? '',
            ]) ?>
        </div>
        <div class="form-row form-row--2">
            <?= partial('site/field', [
                'name' => 'position', 'type' => 'number', 'label' => 'Ordre', 'optional' => true,
                'hint' => 'Ordre dans la sous-catégorie (0 en premier).',
                'value' => old($old, 'position', '0'), 'error' => $errors['position'] ?? '',
                'attrs' => ['min' => '0', 'max' => '9999', 'step' => '1', 'inputmode' => 'numeric'],
            ]) ?>
        </div>
        <?= partial('site/field', [
            'name' => 'description', 'type' => 'textarea', 'label' => 'Description', 'optional' => true,
            'maxlength' => 255, 'counter' => true, 'rows' => 3,
            'hint' => 'Une phrase : ce que vous savez faire concrètement, ou un exemple vécu pour le savoir-être. Texte simple.',
            'value' => old($old, 'description'), 'error' => $errors['description'] ?? '',
        ]) ?>
    </div>

    <div class="form__actions">
        <button class="btn btn--primary" type="submit"><?= icon('check', 16) ?>Enregistrer</button>
        <a class="btn btn--secondary" href="/admin/competences">Annuler</a>
    </div>
</form>
<?php endif; ?>
