<?php
/**
 * Fil d'Ariane (séparateur « / » façon chemin).
 *
 * Variables :
 *   $items list<array{label:string, href?:string|null}>  du plus général au plus précis ;
 *          le dernier élément (page courante) n'a pas de lien.
 */

declare(strict_types=1);

$items = isset($items) && is_array($items) ? array_values($items) : [];
if ($items === []) {
    return;
}
$last = count($items) - 1;
?>
<nav class="breadcrumb" aria-label="Fil d'Ariane">
    <ol class="breadcrumb__list">
<?php foreach ($items as $i => $item): ?>
<?php $href = (string) ($item['href'] ?? ''); ?>
        <li class="breadcrumb__item">
<?php if ($i === $last || $href === ''): ?>
            <span<?= $i === $last ? ' aria-current="page"' : '' ?>><?= e((string) ($item['label'] ?? '')) ?></span>
<?php else: ?>
            <a href="<?= e($href) ?>"><?= e((string) ($item['label'] ?? '')) ?></a>
<?php endif; ?>
        </li>
<?php endforeach; ?>
    </ol>
</nav>
