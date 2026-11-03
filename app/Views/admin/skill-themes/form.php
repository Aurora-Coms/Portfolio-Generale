<?php
/**
 * Formulaire d'un thème de compétences (création et modification).
 *
 * @var array<string, mixed>|null $theme   thème modifié (null = création)
 * @var array<string, mixed>      $old
 * @var array<string, string>     $errors
 * @var array<string, string>     $icons   nom d'icône => libellé
 * @var string                    $action
 */

declare(strict_types=1);

$isNew = $theme === null;
?>
<?= partial('admin/page-header', [
    'back'    => ['href' => '/admin/competences/themes', 'label' => 'Thèmes de compétences'],
    'comment' => $isNew ? '-- competences/themes/nouveau' : '-- competences/themes/' . $theme['slug'],
    'title'   => $isNew ? 'Nouveau thème' : 'Modifier « ' . $theme['name'] . ' »',
    'lead'    => 'L\'ancre de la page publique (#theme-…) est créée automatiquement à partir du nom.',
]) ?>

<form class="form card card--pad" method="post" action="<?= e($action) ?>" novalidate data-focus-error>
    <?= csrf_field() ?>
<?php if ($errors !== []): ?>
    <?= partial('site/alert', ['type' => 'error', 'title' => 'Le thème n\'a pas été enregistré', 'message' => 'Corrigez les champs signalés ci-dessous.']) ?>
<?php endif; ?>

    <div class="form-section">
        <div class="form-row form-row--2">
            <?= partial('site/field', [
                'name' => 'name', 'label' => 'Nom', 'required' => true, 'maxlength' => 80, 'counter' => true,
                'placeholder' => 'Informatique & réseaux', 'value' => old($old, 'name'), 'error' => $errors['name'] ?? '',
            ]) ?>
            <?= partial('site/field', [
                'name' => 'position', 'type' => 'number', 'label' => 'Ordre', 'optional' => true,
                'hint' => 'Les thèmes sont affichés de la plus petite valeur à la plus grande.',
                'value' => old($old, 'position', '0'), 'error' => $errors['position'] ?? '',
                'attrs' => ['min' => '0', 'max' => '9999', 'step' => '1', 'inputmode' => 'numeric'],
            ]) ?>
        </div>
        <?= partial('admin/icon-picker', [
            'name' => 'icon', 'legend' => 'Icône', 'required' => true, 'options' => $icons,
            'value' => old($old, 'icon'), 'error' => $errors['icon'] ?? '',
            'hint' => 'Affichée devant le titre du thème et dans les liens du haut de la page « Compétences ».',
        ]) ?>
    </div>

    <div class="form__actions">
        <button class="btn btn--primary" type="submit"><?= icon('check', 16) ?>Enregistrer</button>
        <a class="btn btn--secondary" href="/admin/competences/themes">Annuler</a>
    </div>
</form>
