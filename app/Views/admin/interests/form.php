<?php
/**
 * Formulaire d'un centre d'intérêt (création et modification).
 *
 * @var array<string, mixed>|null $interest  élément modifié (null = création)
 * @var array<string, mixed>      $old
 * @var array<string, string>     $errors
 * @var array<string, string>     $icons     nom d'icône => libellé
 * @var string                    $action
 */

declare(strict_types=1);

$isNew = $interest === null;
?>
<?= partial('admin/page-header', [
    'back'    => ['href' => '/admin/interets', 'label' => 'Centres d\'intérêt'],
    'comment' => $isNew ? '-- interets/nouveau' : '-- interets/' . (int) $interest['id'],
    'title'   => $isNew ? 'Nouveau centre d\'intérêt' : 'Modifier « ' . $interest['label'] . ' »',
]) ?>

<form class="form card card--pad" method="post" action="<?= e($action) ?>" novalidate data-focus-error>
    <?= csrf_field() ?>
<?php if ($errors !== []): ?>
    <?= partial('site/alert', ['type' => 'error', 'title' => 'Le centre d\'intérêt n\'a pas été enregistré', 'message' => 'Corrigez les champs signalés ci-dessous.']) ?>
<?php endif; ?>

    <div class="form-section">
        <div class="form-row form-row--2">
            <?= partial('site/field', [
                'name' => 'label', 'label' => 'Libellé', 'required' => true, 'maxlength' => 80, 'counter' => true,
                'placeholder' => 'VTT', 'value' => old($old, 'label'), 'error' => $errors['label'] ?? '',
            ]) ?>
            <?= partial('site/field', [
                'name' => 'position', 'type' => 'number', 'label' => 'Ordre', 'optional' => true,
                'hint' => 'Les cartes sont affichées de la plus petite valeur à la plus grande.',
                'value' => old($old, 'position', '0'), 'error' => $errors['position'] ?? '',
                'attrs' => ['min' => '0', 'max' => '9999', 'step' => '1', 'inputmode' => 'numeric'],
            ]) ?>
        </div>
        <?= partial('site/field', [
            'name' => 'description', 'type' => 'textarea', 'label' => 'Description', 'optional' => true,
            'maxlength' => 255, 'counter' => true, 'rows' => 3,
            'hint' => 'Une ou deux phrases. Texte simple.',
            'value' => old($old, 'description'), 'error' => $errors['description'] ?? '',
        ]) ?>
        <?= partial('admin/icon-picker', [
            'name' => 'icon', 'legend' => 'Icône', 'required' => true, 'options' => $icons,
            'value' => old($old, 'icon'), 'error' => $errors['icon'] ?? '',
        ]) ?>
    </div>

    <div class="form__actions">
        <button class="btn btn--primary" type="submit"><?= icon('check', 16) ?>Enregistrer</button>
        <a class="btn btn--secondary" href="/admin/interets">Annuler</a>
    </div>
</form>
