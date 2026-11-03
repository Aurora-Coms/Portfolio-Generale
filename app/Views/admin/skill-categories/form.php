<?php
/**
 * Formulaire d'une sous-catégorie de compétences (création et modification).
 *
 * @var array<string, mixed>|null  $category  sous-catégorie modifiée (null = création)
 * @var array<string, mixed>       $old
 * @var array<string, string>      $errors
 * @var list<array<string, mixed>> $themes    SkillTheme::all()
 * @var array<string, string>      $icons     nom d'icône => libellé
 * @var string                     $action
 */

declare(strict_types=1);

$isNew = $category === null;

$themeOptions = ['' => 'Choisir un thème…'];
foreach ($themes as $theme) {
    $themeOptions[(string) (int) $theme['id']] = (string) $theme['name'];
}
?>
<?= partial('admin/page-header', [
    'back'    => ['href' => '/admin/competences/categories', 'label' => 'Sous-catégories de compétences'],
    'comment' => $isNew ? '-- competences/categories/nouveau' : '-- competences/categories/' . $category['slug'],
    'title'   => $isNew ? 'Nouvelle sous-catégorie' : 'Modifier « ' . $category['name'] . ' »',
    'lead'    => 'L\'adresse interne (slug) est créée automatiquement à partir du nom.',
]) ?>

<?php if ($themes === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 thème',
    'icon'    => 'book',
    'title'   => 'Aucun thème de compétences',
    'text'    => 'Créez d\'abord un thème : chaque sous-catégorie doit appartenir à l\'un d\'eux.',
    'actions' => '<a class="btn btn--primary btn--sm" href="/admin/competences/themes/nouveau">Créer un thème</a>',
]) ?>
<?php else: ?>
<form class="form card card--pad" method="post" action="<?= e($action) ?>" novalidate data-focus-error>
    <?= csrf_field() ?>
<?php if ($errors !== []): ?>
    <?= partial('site/alert', ['type' => 'error', 'title' => 'La sous-catégorie n\'a pas été enregistrée', 'message' => 'Corrigez les champs signalés ci-dessous.']) ?>
<?php endif; ?>

    <div class="form-section">
        <div class="form-row form-row--2">
            <?= partial('site/field', [
                'name' => 'name', 'label' => 'Nom', 'required' => true, 'maxlength' => 80, 'counter' => true,
                'placeholder' => 'Réseau', 'value' => old($old, 'name'), 'error' => $errors['name'] ?? '',
                'hint' => 'Si un thème ne contient qu\'une sous-catégorie du même nom, ce titre n\'est pas répété sur la page publique.',
            ]) ?>
            <?= partial('site/field', [
                'name' => 'theme_id', 'type' => 'select', 'label' => 'Thème', 'required' => true,
                'hint' => 'Grande partie de la page « Compétences » dans laquelle la carte est rangée.',
                'options' => $themeOptions, 'value' => old($old, 'theme_id'), 'error' => $errors['theme_id'] ?? '',
            ]) ?>
        </div>
        <div class="form-row form-row--2">
            <?= partial('site/field', [
                'name' => 'position', 'type' => 'number', 'label' => 'Ordre', 'optional' => true,
                'hint' => 'Ordre dans le thème (0 en premier).',
                'value' => old($old, 'position', '0'), 'error' => $errors['position'] ?? '',
                'attrs' => ['min' => '0', 'max' => '9999', 'step' => '1', 'inputmode' => 'numeric'],
            ]) ?>
        </div>
        <?= partial('admin/icon-picker', [
            'name' => 'icon', 'legend' => 'Icône', 'required' => true, 'options' => $icons,
            'value' => old($old, 'icon'), 'error' => $errors['icon'] ?? '',
            'hint' => 'Affichée en tête de la carte sur la page « Compétences ».',
        ]) ?>
    </div>

    <div class="form__actions">
        <button class="btn btn--primary" type="submit"><?= icon('check', 16) ?>Enregistrer</button>
        <a class="btn btn--secondary" href="/admin/competences/categories">Annuler</a>
    </div>
</form>
<?php endif; ?>
