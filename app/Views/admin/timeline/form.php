<?php
/**
 * Formulaire d'une entrée du parcours (création et modification).
 *
 * @var array<string, mixed>|null $entry   entrée modifiée (null = création)
 * @var array<string, mixed>      $old     valeurs du formulaire
 * @var array<string, string>     $errors
 * @var string                    $action  URL d'envoi
 */

declare(strict_types=1);

$isNew = $entry === null;

$precision = old($old, 'date_precision', 'month');
$precision = in_array($precision, ['day', 'month', 'year'], true) ? $precision : 'month';
$start     = old($old, 'start_date');
$end       = old($old, 'end_date');
// Aperçu de la période telle qu'elle apparaîtra sur le site (mis à jour par admin.js).
$preview   = date_fr($start, $precision) !== '' ? period_fr($start, $end !== '' ? $end : null, $precision) : '';
?>
<?= partial('admin/page-header', [
    'back'    => ['href' => '/admin/parcours', 'label' => 'Parcours'],
    'comment' => $isNew ? '-- parcours/nouveau' : '-- parcours/' . (int) $entry['id'],
    'title'   => $isNew ? 'Nouvelle entrée' : 'Modifier « ' . $entry['title'] . ' »',
    'lead'    => 'Une formation, un stage ou une autre expérience. Sans date de fin, l\'entrée est affichée « en cours ».',
]) ?>

<form class="form card card--pad" method="post" action="<?= e($action) ?>" novalidate data-focus-error>
    <?= csrf_field() ?>
<?php if ($errors !== []): ?>
    <?= partial('site/alert', ['type' => 'error', 'title' => 'L\'entrée n\'a pas été enregistrée', 'message' => 'Corrigez les champs signalés ci-dessous.']) ?>
<?php endif; ?>

    <div class="form-section">
        <h2 class="section-title">Description</h2>
        <div class="form-row form-row--2">
            <?= partial('site/field', [
                'name' => 'type', 'type' => 'select', 'label' => 'Type', 'required' => true,
                'options' => ['education' => 'Études', 'internship' => 'Stage', 'other' => 'Autre expérience'],
                'value' => old($old, 'type', 'education'), 'error' => $errors['type'] ?? '',
            ]) ?>
            <?= partial('site/field', [
                'name' => 'title', 'label' => 'Titre', 'required' => true, 'maxlength' => 150, 'counter' => true,
                'placeholder' => 'BTS CIEL option IR', 'value' => old($old, 'title'), 'error' => $errors['title'] ?? '',
            ]) ?>
        </div>
        <div class="form-row form-row--2">
            <?= partial('site/field', [
                'name' => 'organization', 'label' => 'Établissement ou entreprise', 'required' => true, 'maxlength' => 150,
                'placeholder' => 'Campus St Jean-Baptiste de La Salle', 'value' => old($old, 'organization'), 'error' => $errors['organization'] ?? '',
            ]) ?>
            <?= partial('site/field', [
                'name' => 'location', 'label' => 'Lieu', 'optional' => true, 'maxlength' => 100,
                'placeholder' => 'Avignon (84)', 'value' => old($old, 'location'), 'error' => $errors['location'] ?? '',
            ]) ?>
        </div>
    </div>

    <div class="form-section">
        <h2 class="section-title">Dates</h2>
        <div class="form-row form-row--3">
            <?= partial('site/field', [
                'name' => 'start_date', 'type' => 'date', 'label' => 'Date de début', 'required' => true,
                'value' => $start, 'error' => $errors['start_date'] ?? '',
            ]) ?>
            <?= partial('site/field', [
                'name' => 'end_date', 'type' => 'date', 'label' => 'Date de fin', 'optional' => true,
                'hint' => 'Laissez vide si c\'est en cours.',
                'value' => $end, 'error' => $errors['end_date'] ?? '',
            ]) ?>
            <?= partial('site/field', [
                'name' => 'date_precision', 'type' => 'select', 'label' => 'Précision d\'affichage', 'required' => true,
                'hint' => 'Ce qui est écrit sur le site.',
                'options' => [
                    'day'   => 'Jour · ex. 18 mai 2026',
                    'month' => 'Mois · ex. mai 2026',
                    'year'  => 'Année · ex. 2026',
                ],
                'value' => $precision, 'error' => $errors['date_precision'] ?? '',
            ]) ?>
        </div>
        <p class="period-preview" data-period-preview data-start="#f-start_date" data-end="#f-end_date" data-precision="#f-date_precision">
            <span class="period-preview__label">Affichage sur le site :</span>
            <output class="period-preview__value" for="f-start_date f-end_date f-date_precision" aria-live="polite" data-period-output><?= e($preview !== '' ? $preview : 'indiquez une date de début') ?></output>
        </p>
    </div>

    <div class="form-section">
        <?= partial('site/field', [
            'name' => 'description', 'type' => 'textarea', 'label' => 'Détails', 'optional' => true,
            'maxlength' => 5000, 'counter' => true, 'rows' => 8,
            'hint' => 'Missions, compétences travaillées, diplôme obtenu… Mise en forme Markdown.',
            'value' => old($old, 'description'), 'error' => $errors['description'] ?? '',
            'after' => partial('admin/markdown-help', ['id' => 'aide-markdown-parcours']),
        ]) ?>
        <div class="form-row form-row--2">
            <?= partial('admin/switch', [
                'name' => 'is_published', 'label' => 'Publié',
                'checked' => old($old, 'is_published', '1') === '1',
                'hint' => 'Visible sur la page publique « Parcours ».',
            ]) ?>
            <?= partial('site/field', [
                'name' => 'position', 'type' => 'number', 'label' => 'Ordre', 'optional' => true,
                'hint' => 'Départage deux entrées qui commencent à la même date (0 en premier).',
                'value' => old($old, 'position', '0'), 'error' => $errors['position'] ?? '',
                'attrs' => ['min' => '0', 'max' => '9999', 'step' => '1', 'inputmode' => 'numeric'],
            ]) ?>
        </div>
    </div>

    <div class="form__actions">
        <button class="btn btn--primary" type="submit"><?= icon('check', 16) ?>Enregistrer</button>
        <a class="btn btn--secondary" href="/admin/parcours">Annuler</a>
    </div>
</form>
