<?php
/**
 * Bloc « en bref » : liste de faits façon table Lua (clé = valeur).
 *
 * Variables :
 *   $items list<array{label:string, value:string}>  faits (texte simple)
 *   $title string  (optionnel) commentaire de titre, défaut '-- en bref'
 */

declare(strict_types=1);

$items = isset($items) && is_array($items) ? $items : [];
$title = (string) ($title ?? '-- en bref');
if ($items === []) {
    return;
}
?>
<div class="card facts">
    <p class="facts__title"><?= e($title) ?></p>
    <dl class="facts__list">
<?php foreach ($items as $item): ?>
        <div class="facts__row">
            <dt><?= e((string) ($item['label'] ?? '')) ?></dt>
            <dd><?= e((string) ($item['value'] ?? '')) ?></dd>
        </div>
<?php endforeach; ?>
    </dl>
</div>
