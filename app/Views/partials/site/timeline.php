<?php
/**
 * Timeline verticale du parcours (études, stages, autre).
 * Chaque type a une couleur ET une forme ET un libellé texte (jamais la couleur seule).
 *
 * Variables :
 *   $entries      list  lignes de TimelineEntry::published() : type, title, organization, location,
 *                       start_date, end_date, date_precision, description (Markdown)
 *   $headingLevel int   (optionnel) niveau des titres, 3 par défaut
 *   $legend       bool  (optionnel) afficher la légende des types (défaut true)
 *   $reveal       bool  (optionnel) apparition au défilement (défaut true)
 */

declare(strict_types=1);

$entries      = isset($entries) && is_array($entries) ? $entries : [];
$headingLevel = max(2, min(4, (int) ($headingLevel ?? 3)));
$legend       = (bool) ($legend ?? true);
$reveal       = (bool) ($reveal ?? true);

if ($entries === []) {
    return;
}
$types = ['education' => 'education', 'internship' => 'internship', 'other' => 'other'];
?>
<?php if ($legend): ?>
<ul class="legend" aria-label="Légende">
    <li class="legend__item"><span class="legend__swatch" aria-hidden="true"></span><?= e(timeline_type_label('education')) ?></li>
    <li class="legend__item"><span class="legend__swatch legend__swatch--internship" aria-hidden="true"></span><?= e(timeline_type_label('internship')) ?></li>
    <li class="legend__item"><span class="legend__swatch legend__swatch--other" aria-hidden="true"></span><?= e(timeline_type_label('other')) ?></li>
</ul>
<?php endif; ?>
<ol class="timeline">
<?php foreach ($entries as $entry):
    $type      = $types[(string) ($entry['type'] ?? '')] ?? 'other';
    $precision = in_array($entry['date_precision'] ?? '', ['day', 'month', 'year'], true) ? (string) $entry['date_precision'] : 'month';
    $org       = trim((string) ($entry['organization'] ?? ''));
    $location  = trim((string) ($entry['location'] ?? ''));
?>
    <li class="timeline__item timeline__item--<?= e($type) ?>"<?= $reveal ? ' data-reveal' : '' ?>>
        <span class="timeline__dot" aria-hidden="true"></span>
        <div class="timeline__head">
            <span class="timeline__type"><?= e(timeline_type_label($type)) ?></span>
            <p class="timeline__date"><?= e(period_fr($entry['start_date'] ?? null, $entry['end_date'] ?? null, $precision)) ?></p>
        </div>
        <h<?= $headingLevel ?> class="timeline__title"><?= e((string) ($entry['title'] ?? '')) ?></h<?= $headingLevel ?>>
<?php if ($org !== '' || $location !== ''): ?>
        <p class="timeline__org"><?= e($org) ?><?= $org !== '' && $location !== '' ? ', ' : '' ?><?= e($location) ?></p>
<?php endif; ?>
<?php if (trim((string) ($entry['description'] ?? '')) !== ''): ?>
        <div class="timeline__desc prose"><?= md((string) $entry['description']) ?></div>
<?php endif; ?>
    </li>
<?php endforeach; ?>
</ol>
