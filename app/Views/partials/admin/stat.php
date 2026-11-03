<?php
/**
 * Tuile de statistique du tableau de bord (à placer dans <ul class="stats">).
 *
 * Variables :
 *   $label string      libellé (obligatoire), ex. 'Projets publiés'
 *   $value int|string  valeur (obligatoire)
 *   $icon  string      (optionnel) nom d'icône
 *   $href  string      (optionnel) lien : toute la tuile devient cliquable
 *   $hint  string      (optionnel) précision sous la valeur, ex. '3 brouillons'
 */

declare(strict_types=1);

$label    = (string) ($label ?? '');
$value    = (string) ($value ?? '0');
$iconName = (string) ($icon ?? '');
$href     = (string) ($href ?? '');
$hint     = (string) ($hint ?? '');
?>
<li class="stat">
    <span class="stat__label"><?= $iconName !== '' ? icon($iconName, 16) : '' ?><?php if ($href !== ''): ?><a class="stat__link" href="<?= e($href) ?>"><?= e($label) ?></a><?php else: ?><?= e($label) ?><?php endif; ?></span>
    <span class="stat__value"><?= e($value) ?></span>
<?php if ($hint !== ''): ?>
    <span class="stat__hint"><?= e($hint) ?></span>
<?php endif; ?>
</li>
