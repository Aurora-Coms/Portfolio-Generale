<?php
/**
 * Modification du texte « À propos » (Markdown) et de la ville affichée.
 *
 * @var array<string, mixed>  $old
 * @var array<string, string> $errors
 * @var string                $saved  texte actuellement enregistré (aperçu)
 * @var int                   $max
 */

declare(strict_types=1);
?>
<?= partial('admin/page-header', [
    'comment' => '-- a-propos.md',
    'title'   => 'À propos',
    'lead'    => 'Votre présentation, affichée sur la page publique « À propos ». Les centres d\'intérêt se gèrent à part.',
    'actions' => '<a class="btn btn--secondary" href="/a-propos" target="_blank" rel="noopener noreferrer">' . icon('external', 16) . 'Voir la page<span class="sr-only"> (nouvel onglet)</span></a>'
        . '<a class="btn btn--secondary" href="/admin/interets">' . icon('star', 16) . 'Centres d\'intérêt</a>',
]) ?>

<div class="edit-layout">
    <form class="form card card--pad" method="post" action="/admin/a-propos" novalidate data-focus-error>
        <?= csrf_field() ?>
<?php if ($errors !== []): ?>
        <?= partial('site/alert', ['type' => 'error', 'title' => 'Le texte n\'a pas été enregistré', 'message' => 'Corrigez les champs signalés ci-dessous.']) ?>
<?php endif; ?>
        <?= partial('site/field', [
            'name' => 'about_text', 'type' => 'textarea', 'label' => 'Texte de présentation', 'optional' => true,
            'class' => 'textarea--lg', 'maxlength' => $max, 'counter' => true, 'rows' => 16,
            'hint' => 'Qui vous êtes, votre formation, ce que vous recherchez. Laissez une ligne vide entre deux paragraphes.',
            'value' => old($old, 'about_text'), 'error' => $errors['about_text'] ?? '',
            'after' => partial('admin/markdown-help', ['id' => 'aide-markdown-apropos']),
        ]) ?>
        <?= partial('site/field', [
            'name' => 'location', 'label' => 'Ville', 'optional' => true, 'maxlength' => 100,
            'hint' => 'Affichée dans « en bref » et dans les données de référencement, ex. Avignon (84).',
            'value' => old($old, 'location'), 'error' => $errors['location'] ?? '',
            'attrs' => ['autocomplete' => 'address-level2'],
        ]) ?>
        <div class="form__actions">
            <button class="btn btn--primary" type="submit"><?= icon('check', 16) ?>Enregistrer</button>
        </div>
    </form>

    <section class="card" aria-labelledby="titre-apercu">
        <div class="card__head">
            <h2 class="card__title" id="titre-apercu">Aperçu</h2>
            <span class="badge badge--plain">version enregistrée</span>
        </div>
        <div class="card__body">
<?php if (trim($saved) === ''): ?>
            <p class="text-muted">Aucun texte enregistré pour le moment.</p>
<?php else: ?>
            <div class="prose"><?= md($saved) ?></div>
<?php endif; ?>
        </div>
    </section>
</div>
