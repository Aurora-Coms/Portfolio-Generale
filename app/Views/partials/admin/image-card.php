<?php
/**
 * Vignette d'une image de projet avec ses actions (à placer dans <ul class="thumb-grid">) :
 * texte alternatif modifiable, monter, descendre, définir comme couverture, supprimer.
 * Toutes les actions sont des formulaires POST avec jeton CSRF (routes /admin/images/{id}/…).
 *
 * Variables :
 *   $image   array  ligne ProjectImage : id, thumb_filename, filename, alt_text, width, height, position
 *   $isCover bool   (optionnel) image de couverture (badge, bouton masqué)
 *   $isFirst bool   (optionnel) première de la liste (pas de « monter »)
 *   $isLast  bool   (optionnel) dernière de la liste (pas de « descendre »)
 *   $number  int    (optionnel) numéro affiché (position humaine)
 *   $error   string (optionnel) erreur de validation du texte alternatif
 */

declare(strict_types=1);

$image   = isset($image) && is_array($image) ? $image : [];
$id      = (int) ($image['id'] ?? 0);
$isCover = (bool) ($isCover ?? false);
$isFirst = (bool) ($isFirst ?? false);
$isLast  = (bool) ($isLast ?? false);
$number  = (int) ($number ?? 0);
$error   = (string) ($error ?? '');
$alt     = (string) ($image['alt_text'] ?? '');
$fieldId = 'alt-' . $id;
?>
<li class="thumb<?= $isCover ? ' is-cover' : '' ?>">
    <div class="thumb__media">
        <img src="<?= e(upload_url((string) ($image['thumb_filename'] ?? ''))) ?>" alt="<?= e($alt !== '' ? $alt : 'Image sans description') ?>" width="<?= (int) ($image['width'] ?? 0) ?>" height="<?= (int) ($image['height'] ?? 0) ?>" loading="lazy" decoding="async">
<?php if ($isCover): ?>
        <span class="thumb__badge"><span class="badge badge--featured">Couverture</span></span>
<?php endif; ?>
    </div>
    <form class="thumb__body" method="post" action="/admin/images/<?= $id ?>">
        <?= csrf_field() ?>
        <p class="thumb__meta"><?= $number > 0 ? 'Image ' . $number . ' · ' : '' ?><?= (int) ($image['width'] ?? 0) ?> × <?= (int) ($image['height'] ?? 0) ?> px</p>
        <div class="field">
            <label class="field__label" for="<?= e($fieldId) ?>">Texte alternatif</label>
            <input class="input" type="text" id="<?= e($fieldId) ?>" name="alt_text" value="<?= e($alt) ?>" maxlength="200"<?= $error !== '' ? ' aria-invalid="true" aria-describedby="' . e($fieldId) . '-erreur"' : '' ?>>
<?php if ($error !== ''): ?>
            <p class="field__error" id="<?= e($fieldId) ?>-erreur"><?= icon('info', 16) ?><span><?= e($error) ?></span></p>
<?php endif; ?>
        </div>
        <div><button type="submit" class="btn btn--secondary btn--sm"><?= icon('check', 16) ?>Enregistrer</button></div>
    </form>
    <div class="thumb__actions">
<?php if (!$isFirst): ?>
<?= partial('admin/post-button', ['action' => '/admin/images/' . $id . '/monter', 'label' => trim('Monter l\'image ' . ($number ?: '')), 'icon' => 'up', 'iconOnly' => true]) ?>
<?php endif; ?>
<?php if (!$isLast): ?>
<?= partial('admin/post-button', ['action' => '/admin/images/' . $id . '/descendre', 'label' => trim('Descendre l\'image ' . ($number ?: '')), 'icon' => 'down', 'iconOnly' => true]) ?>
<?php endif; ?>
<?php if (!$isCover): ?>
<?= partial('admin/post-button', ['action' => '/admin/images/' . $id . '/couverture', 'label' => 'Couverture', 'icon' => 'star']) ?>
<?php endif; ?>
<?= partial('admin/post-button', ['action' => '/admin/images/' . $id . '/supprimer', 'label' => trim('Supprimer l\'image ' . ($number ?: '')), 'icon' => 'trash', 'iconOnly' => true, 'variant' => 'danger', 'confirm' => 'Supprimer définitivement cette image ?']) ?>
    </div>
</li>
