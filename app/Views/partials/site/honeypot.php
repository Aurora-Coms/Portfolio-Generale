<?php
/**
 * Champ piège anti-spam : invisible et inatteignable pour un humain (hors écran,
 * aria-hidden, tabindex -1, autocomplete off). Un robot qui le remplit est rejeté
 * par le contrôleur (le champ doit rester vide).
 *
 * Variables :
 *   $name  string  (optionnel) nom du champ, défaut 'site_web'
 */

declare(strict_types=1);

$name = (string) ($name ?? 'site_web');
?>
<div class="hp" aria-hidden="true">
    <label for="f-<?= e($name) ?>">Ne pas remplir ce champ</label>
    <input type="text" id="f-<?= e($name) ?>" name="<?= e($name) ?>" value="" tabindex="-1" autocomplete="off">
</div>
