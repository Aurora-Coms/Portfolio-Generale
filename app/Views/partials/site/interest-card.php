<?php
/**
 * Carte d'un centre d'intérêt.
 *
 * Variables :
 *   $interest     array  ligne de Interest::all() : label, description, icon
 *   $headingLevel int    (optionnel) niveau du titre, 3 par défaut
 */

declare(strict_types=1);

$interest     = isset($interest) && is_array($interest) ? $interest : [];
$headingLevel = max(2, min(4, (int) ($headingLevel ?? 3)));
$iconSvg      = icon((string) ($interest['icon'] ?? ''), 22);
if ($iconSvg === '') {
    $iconSvg = icon('star', 22);
}
?>
<div class="card interest-card" data-reveal>
    <span class="interest-card__icon"><?= $iconSvg ?></span>
    <div>
        <h<?= $headingLevel ?> class="interest-card__title"><?= e((string) ($interest['label'] ?? '')) ?></h<?= $headingLevel ?>>
<?php if (trim((string) ($interest['description'] ?? '')) !== ''): ?>
        <p class="interest-card__text"><?= e((string) $interest['description']) ?></p>
<?php endif; ?>
    </div>
</div>
