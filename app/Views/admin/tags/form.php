<?php
/**
 * Formulaire de tag (création et modification).
 *
 * Variables :
 *   $tag    ?array  tag modifié (+ project_count) ou null
 *   $old    array   valeurs à afficher
 *   $errors array   champ => message
 */

declare(strict_types=1);

$tag    = isset($tag) && is_array($tag) ? $tag : null;
$old    = isset($old) && is_array($old) ? $old : [];
$errors = isset($errors) && is_array($errors) ? $errors : [];

$isEdit = $tag !== null;
$id     = $isEdit ? (int) $tag['id'] : 0;
$count  = $isEdit ? (int) ($tag['project_count'] ?? 0) : 0;
$err    = static fn (string $field): string => (string) ($errors[$field] ?? '');
?>
<?= partial('admin/page-header', [
    'comment' => $isEdit ? '-- tags/' . (string) $tag['slug'] : '-- tags/nouveau',
    'title'   => $isEdit ? 'Modifier le tag' : 'Nouveau tag',
    'lead'    => $isEdit ? (string) $tag['name'] . ' · utilisé par ' . $count . ' projet(s)' : 'Une technologie ou un outil, ex. Lua, Cisco Packet Tracer, Linux.',
    'back'    => ['href' => '/admin/tags', 'label' => 'Tags'],
]) ?>

<form class="form card card--pad" method="post" action="<?= e($isEdit ? '/admin/tags/' . $id : '/admin/tags') ?>" novalidate data-focus-error>
    <?= csrf_field() ?>
<?php if ($errors !== []): ?>
    <?= partial('site/alert', ['type' => 'error', 'title' => 'Le tag n\'a pas été enregistré', 'message' => 'Corrigez les champs signalés ci-dessous.']) ?>
<?php endif; ?>
    <div class="form-row form-row--2">
        <?= partial('site/field', ['name' => 'name', 'label' => 'Nom', 'required' => true, 'maxlength' => 50, 'counter' => true,
            'hint' => 'Tel qu\'affiché sur le site. Doit être unique.',
            'value' => old($old, 'name'), 'error' => $err('name')]) ?>
        <?= partial('site/field', ['name' => 'slug', 'label' => 'Adresse (slug)', 'optional' => true, 'maxlength' => 55, 'class' => 'input--mono',
            'hint' => 'Identifiant technique unique. Vide : créé depuis le nom.',
            'value' => old($old, 'slug'), 'error' => $err('slug'),
            'attrs' => ['data-slug-source' => '#f-name', 'autocapitalize' => 'none', 'spellcheck' => 'false', 'autocomplete' => 'off']]) ?>
    </div>
    <div class="form__actions">
        <button class="btn btn--primary" type="submit" data-busy-label="Enregistrement…"><?= icon('check', 16) ?><?= $isEdit ? 'Enregistrer' : 'Créer le tag' ?></button>
        <a class="btn btn--secondary" href="/admin/tags">Annuler</a>
    </div>
</form>

<?php if ($isEdit): ?>
<section class="card card--pad stack danger-zone" aria-labelledby="titre-suppression">
    <h2 class="section-title" id="titre-suppression">Supprimer le tag</h2>
    <p class="text-soft"><?= e($count > 0 ? 'Il sera retiré des ' . $count . ' projet(s) qui l\'utilisent.' : 'Aucun projet n\'utilise ce tag.') ?></p>
    <div>
        <?= partial('admin/post-button', [
            'action'  => '/admin/tags/' . $id . '/supprimer',
            'label'   => 'Supprimer ce tag',
            'icon'    => 'trash',
            'variant' => 'danger',
            'size'    => 'md',
            'confirm' => 'Supprimer le tag « ' . (string) $tag['name'] . ' » ?' . ($count > 0 ? ' Il sera retiré des ' . $count . ' projet(s) qui l\'utilisent.' : ''),
        ]) ?>
    </div>
</section>
<?php endif; ?>
