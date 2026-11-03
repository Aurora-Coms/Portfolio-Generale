<?php
/**
 * Fenêtre d'éditeur de code avec onglets et numéros de ligne.
 *
 * Variables :
 *   $filename string        nom du fichier de l'onglet actif, ex. 'profil.lua' (obligatoire)
 *   $tabs     list<string>  (optionnel) onglets secondaires inactifs, ex. ['cardverse.lua']
 *   $lines    list<string>  lignes de code EN HTML DÉJÀ SÛR : le développeur échappe lui-même
 *                           le texte avec e() et ajoute les classes tok-kw / tok-str / tok-num / tok-com
 *   $label    string        (optionnel) nom accessible de la figure (défaut : « Extrait de code : {filename} »)
 *   $cursor   bool          (optionnel) curseur clignotant ▍ en fin de dernière ligne (défaut true)
 *   $class    string        (optionnel) classe(s) supplémentaire(s) sur la figure
 */

declare(strict_types=1);

$filename = (string) ($filename ?? '');
$tabs     = isset($tabs) && is_array($tabs) ? $tabs : [];
$lines    = isset($lines) && is_array($lines) ? array_values(array_map('strval', $lines)) : [];
$label    = (string) ($label ?? ('Extrait de code : ' . $filename));
$cursor   = (bool) ($cursor ?? true);
$class    = trim('code-window ' . (string) ($class ?? ''));

$count  = max(1, count($lines));
$gutter = implode("\n", range(1, $count));
$code   = implode("\n", $lines);
if ($cursor) {
    $code .= '<span class="cursor" aria-hidden="true">▍</span>';
}
?>
<figure class="<?= e($class) ?>" aria-label="<?= e($label) ?>">
    <ul class="code-window__tabs" aria-hidden="true">
        <li class="code-window__tab code-window__tab--active"><?= e($filename) ?></li>
<?php foreach ($tabs as $tab): ?>
        <li class="code-window__tab"><?= e((string) $tab) ?></li>
<?php endforeach; ?>
    </ul>
    <div class="code-window__body">
        <div class="code-window__gutter" aria-hidden="true"><?= $gutter ?></div>
        <pre class="code-window__code" tabindex="0"><code><?= $code ?></code></pre>
    </div>
</figure>
