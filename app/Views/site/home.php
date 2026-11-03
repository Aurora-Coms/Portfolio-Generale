<?php
/**
 * Accueil.
 *
 * Variables (HomeController::index) :
 *   $featured    list    Project::featured(4)
 *   $categories  list    catégories des projets mis en avant (vide si une seule) : name, slug, project_count
 *   $active      ?string slug de la catégorie active (?categorie=), null = toutes
 *   $timeline    list    3 dernières entrées de TimelineEntry::published()
 *   $cvAvailable bool    CV (PDF) disponible
 */

declare(strict_types=1);

use App\Core\Slug;

$featured    = isset($featured) && is_array($featured) ? $featured : [];
$categories  = isset($categories) && is_array($categories) ? $categories : [];
$active      = isset($active) && is_string($active) ? $active : null;
$timeline    = isset($timeline) && is_array($timeline) ? $timeline : [];
$cvAvailable = (bool) ($cvAvailable ?? false);

$siteName = setting('site_name', 'Alexandre Derbez');

// --- Fenêtre de code « profil.lua », générée à partir des données -------------
// Nom de variable Lua tiré du prénom (« alexandre »), sinon « profil ».
$var = str_replace('-', '_', Slug::make((string) strtok($siteName, ' ')));
if ($var === '' || !preg_match('/^[a-z_][a-z0-9_]*$/', $var)) {
    $var = 'profil';
}
$str = static fn (string $v): string => '<span class="tok-str">"' . e($v) . '"</span>';
$num = static fn (int $n): string => '<span class="tok-num">' . $n . '</span>';
$kw  = static fn (string $k): string => '<span class="tok-kw">' . e($k) . '</span>';

$fields = [
    'nom'       => $str($siteName),
    'formation' => $str('BTS CIEL option IR'),
    'annee'     => $num(1),
    'ecole'     => $str('Campus La Salle, Avignon'),
    'stages'    => $num(6),
    'passions'  => '{ ' . implode(', ', array_map($str, ['réseaux', 'cyber', 'Lua', 'VTT'])) . ' }',
];
$pad = max(array_map('strlen', array_keys($fields)));

$lines = ['<span class="tok-com">-- profil.lua</span>', $kw('local') . ' ' . e($var) . ' = {'];
foreach ($fields as $key => $value) {
    $lines[] = '    ' . str_pad($key, $pad) . ' = ' . $value . ',';
}
$lines[] = '}';
$lines[] = $kw('return') . ' ' . e($var);

// Version courte pour téléphone (maquette mobile) : lignes étroites, sans défilement.
$compactFields = [
    'formation' => $str('BTS CIEL IR'),
    'annee'     => $num(1),
    'stages'    => $num(6),
    'langage'   => $str('Lua'),
];
$compactPad   = max(array_map('strlen', array_keys($compactFields)));
$compactLines = [$kw('local') . ' ' . e($var) . ' = {'];
foreach ($compactFields as $key => $value) {
    $compactLines[] = '  ' . str_pad($key, $compactPad) . ' = ' . $value . ',';
}
$compactLines[] = '}';
$compactLines[] = $kw('return') . ' ' . e($var);

// --- Parcours : aperçu compact (sans les descriptions détaillées) -------------
$compact = array_map(static function (array $entry): array {
    $entry['description'] = '';
    return $entry;
}, $timeline);
?>
<section class="hero" aria-labelledby="titre-accueil">
    <div>
        <p class="hero__kicker" data-typewriter data-typewriter-delay="300"><?= e(setting('hero_kicker', '-- Bonjour, je suis')) ?></p>
        <h1 class="hero__title" id="titre-accueil"><?= e($siteName) ?></h1>
<?php if (setting('hero_title') !== ''): ?>
        <p class="hero__subtitle"><?= e(setting('hero_title')) ?></p>
<?php endif; ?>
<?php if (setting('hero_tagline') !== ''): ?>
        <p class="hero__lead"><?= e(setting('hero_tagline')) ?></p>
<?php endif; ?>
        <div class="btn-row hero__actions">
            <a class="btn btn--primary" href="/projets/jeux">Voir mes projets</a>
            <a class="btn btn--secondary" href="/parcours">Mon parcours</a>
<?php if ($cvAvailable): ?>
            <a class="btn btn--secondary" href="/cv"><?= icon('download') ?>CV (PDF)</a>
<?php endif; ?>
        </div>
    </div>
    <div class="hero__visual">
<?= partial('site/code-window', [
    'filename' => 'profil.lua',
    'tabs'     => ['cardverse.lua'],
    'label'    => 'Présentation sous forme de code Lua : profil.lua',
    'lines'    => $lines,
    'class'    => 'hero__code--full',
]) ?>
<?= partial('site/code-window', [
    'filename' => 'profil.lua',
    'label'    => 'Présentation sous forme de code Lua : profil.lua',
    'lines'    => $compactLines,
    'class'    => 'hero__code--compact',
]) ?>
    </div>
</section>

<section class="section" aria-labelledby="titre-projets">
<?= partial('site/section-head', [
    'comment' => '-- projets/',
    'title'   => 'Projets mis en avant',
    'id'      => 'titre-projets',
    'actions' => partial('site/project-filters', [
        'categories' => $categories,
        'active'     => $active,
        'basePath'   => '/',
        'target'     => 'projets-vedette',
        'label'      => 'Filtrer les projets mis en avant par catégorie',
    ]),
]) ?>
<?= partial('site/project-grid', [
    'projects'   => $featured,
    'id'         => 'projets-vedette',
    'active'     => $active,
    'emptyTitle' => 'Projets en cours de publication',
    'emptyText'  => 'Les premiers projets seront bientôt en ligne.',
]) ?>
    <p class="section-more">
        <a class="btn btn--secondary" href="/projets/jeux">Tous les projets<?= icon('arrow') ?></a>
    </p>
</section>

<?php if ($compact !== []): ?>
<section class="section" aria-labelledby="titre-parcours">
<?= partial('site/section-head', [
    'comment' => '-- parcours.md',
    'title'   => 'Parcours',
    'id'      => 'titre-parcours',
]) ?>
<?= partial('site/timeline', ['entries' => $compact, 'legend' => false]) ?>
    <p class="section-more">
        <a class="btn btn--secondary" href="/parcours">Tout le parcours<?= icon('arrow') ?></a>
    </p>
</section>
<?php endif; ?>
