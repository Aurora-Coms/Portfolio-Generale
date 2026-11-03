<?php
/**
 * Gabarit du site public.
 *
 * Variables :
 *   $content    string  HTML de la page (fourni par View::render)
 *   $meta       array   (optionnel) title, description, canonical, og_image,
 *                       og_image_width, og_image_height, og_image_alt, og_type, robots
 *   $bodyClass  string  (optionnel) classe(s) ajoutée(s) à <body>
 *
 * @var string $content
 */

declare(strict_types=1);

use App\Core\Request;

$meta     = isset($meta) && is_array($meta) ? $meta : [];
$siteName = setting('site_name', 'Alexandre Derbez');
$path     = Request::path();

// Titre : « Projets · Alexandre Derbez », ou « Alexandre Derbez · Portfolio ».
$pageTitle = trim((string) ($meta['title'] ?? ''));
$fullTitle = $pageTitle !== '' && $pageTitle !== $siteName
    ? $pageTitle . ' · ' . $siteName
    : $siteName . ' · Portfolio';

$description = trim((string) ($meta['description'] ?? ''));
if ($description === '') {
    $description = setting('meta_description');
}
if ($description === '') {
    $description = $siteName . ' : étudiant en BTS CIEL option IR (cybersécurité, informatique et réseaux) à Avignon. '
        . 'Projets réseaux, systèmes et jeux en Lua sur Roblox Studio. Recherche de stage.';
}

$robots    = trim((string) ($meta['robots'] ?? ''));
$noindex   = str_contains($robots, 'noindex');
$canonical = abs_url((string) ($meta['canonical'] ?? $path));

// Image d'aperçu (LinkedIn, etc.) : toujours une URL absolue.
$ogImage = (string) ($meta['og_image'] ?? '/assets/img/og-default.png');
$ogIsDefault = !isset($meta['og_image']);
if (!preg_match('#^https?://#i', $ogImage)) {
    $ogImage = abs_url($ogImage);
}
$ogWidth  = $ogIsDefault ? 1200 : (int) ($meta['og_image_width'] ?? 0);
$ogHeight = $ogIsDefault ? 630 : (int) ($meta['og_image_height'] ?? 0);
$ogAlt    = (string) ($meta['og_image_alt'] ?? ($siteName . ' · ' . setting('hero_title', 'Étudiant BTS CIEL option IR')));
$ogType   = (string) ($meta['og_type'] ?? ($path === '/' ? 'profile' : 'website'));

// Liens externes : uniquement http(s) (jamais « javascript: »).
$safeUrl = static fn (string $url): string => preg_match('#^https?://[^\s"<>]+$#i', $url) ? $url : '';
$links = [
    'linkedin' => $safeUrl(setting('linkedin_url')),
    'github'   => $safeUrl(setting('github_url')),
];

// Données structurées (schema.org Person).
$sameAs = array_values(array_filter($links));
$school = [
    '@type'   => 'EducationalOrganization',
    'name'    => 'Campus St Jean-Baptiste de La Salle',
    'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Avignon', 'addressCountry' => 'FR'],
];
$person = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Person',
    'name'        => $siteName,
    'jobTitle'    => setting('hero_title', 'Étudiant BTS CIEL option IR'),
    'url'         => abs_url('/'),
    'image'       => abs_url('/assets/img/og-default.png'),
    'alumniOf'    => $school,
    'affiliation' => $school,
];
if ($sameAs !== []) {
    $person['sameAs'] = $sameAs;
}
$jsonLd = (string) json_encode($person, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

$bodyClass = isset($bodyClass) && is_string($bodyClass) ? trim($bodyClass) : '';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
<?php if ($robots !== ''): ?>
    <meta name="robots" content="<?= e($robots) ?>">
<?php endif; ?>
<?php if (!$noindex): ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>
    <meta name="theme-color" content="#0F1016">
    <meta name="color-scheme" content="dark">
<?= partial('site/favicons') ?>

    <meta property="og:type" content="<?= e($ogType) ?>">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:title" content="<?= e($fullTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
<?php if ($ogWidth > 0 && $ogHeight > 0): ?>
    <meta property="og:image:width" content="<?= $ogWidth ?>">
    <meta property="og:image:height" content="<?= $ogHeight ?>">
<?php endif; ?>
    <meta property="og:image:alt" content="<?= e($ogAlt) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($fullTitle) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">

    <link rel="preload" href="/assets/fonts/inter-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/jetbrains-mono-latin-700-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
    <script src="<?= e(asset('js/site.js')) ?>" defer></script>
    <script type="application/ld+json"><?= $jsonLd ?></script>
</head>
<body<?= $bodyClass !== '' ? ' class="' . e($bodyClass) . '"' : '' ?>>
<a class="skip-link" href="#contenu">Aller au contenu</a>

<?= partial('site/header', ['siteName' => $siteName, 'links' => $links]) ?>

<main id="contenu" class="site-main" tabindex="-1">
    <div class="container">
<?= partial('site/banner') ?>
<?= partial('site/flashes') ?>
<?= $content ?>
    </div>
</main>

<?= partial('site/footer', ['siteName' => $siteName, 'links' => $links]) ?>
</body>
</html>
