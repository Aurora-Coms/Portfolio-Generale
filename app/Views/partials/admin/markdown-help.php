<?php
/**
 * Aide Markdown repliable (<details>), à placer sous un champ texte riche.
 *
 * Variables :
 *   $id string (optionnel) id de l'élément, à ajouter dans aria-describedby du textarea si voulu
 */

declare(strict_types=1);

$id = (string) ($id ?? 'aide-markdown');
$rows = [
    ['**texte en gras**', 'texte en gras'],
    ['*texte en italique*', 'texte en italique'],
    ['`code`', 'code en ligne'],
    ["- élément\n- élément", 'liste à puces'],
    ["1. étape\n2. étape", 'liste numérotée'],
    ['### Sous-titre', 'sous-titre'],
    ['[texte](https://exemple.fr)', 'lien (https uniquement)'],
    ["```\ncode\n```", 'bloc de code'],
];
?>
<details class="md-help" id="<?= e($id) ?>">
    <summary>Aide de mise en forme (Markdown)</summary>
    <div class="md-help__body">
        <table class="md-help__table">
            <thead>
                <tr><th scope="col">Saisir</th><th scope="col">Résultat</th></tr>
            </thead>
            <tbody>
<?php foreach ($rows as [$syntax, $result]): ?>
                <tr><td><code><?= e($syntax) ?></code></td><td><?= e($result) ?></td></tr>
<?php endforeach; ?>
            </tbody>
        </table>
        <p class="field__hint">Le HTML n'est pas interprété. Laissez une ligne vide entre deux paragraphes.</p>
    </div>
</details>
