<?php
/**
 * Messages flash (Session::flash) affichés en haut du contenu.
 * Utilisé par les trois gabarits ; les vues n'ont pas à l'appeler.
 *
 * Variables :
 *   $items list<array{type:string, message:string}> (optionnel, défaut : flashes())
 */

declare(strict_types=1);

$items = isset($items) && is_array($items) ? $items : flashes();
if ($items === []) {
    return;
}
?>
<div class="flashes">
<?php foreach ($items as $flash): ?>
<?= partial('site/alert', ['type' => (string) ($flash['type'] ?? 'info'), 'message' => (string) ($flash['message'] ?? '')]) ?>
<?php endforeach; ?>
</div>
