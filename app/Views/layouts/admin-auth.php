<?php
/**
 * Gabarit des pages d'authentification de l'administration : connexion,
 * double authentification, installation, et page d'erreur sous /admin.
 * Une carte centrée, sans navigation.
 *
 * Variables :
 *   $content  string  HTML de la page (contenu de la carte)
 *   $meta     array   (optionnel) title ; robots est toujours « noindex, nofollow »
 *
 * Classes utiles dans la vue : .auth-card__comment, .auth-card__title (h1),
 * .auth-card__lead, .form, .field… (voir docs/STYLEGUIDE.md).
 *
 * @var string $content
 */

declare(strict_types=1);

$meta      = isset($meta) && is_array($meta) ? $meta : [];
$pageTitle = trim((string) ($meta['title'] ?? ''));

// Le nom vient des réglages ; si la base est indisponible, valeur par défaut.
try {
    $siteName = setting('site_name', 'Alexandre Derbez');
} catch (\Throwable) {
    $siteName = 'Alexandre Derbez';
}
$fullTitle = ($pageTitle !== '' ? $pageTitle . ' · ' : '') . 'Administration · ' . $siteName;
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">
    <meta name="theme-color" content="#0F1016">
    <meta name="color-scheme" content="dark">
<?= partial('site/favicons') ?>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</head>
<body class="auth-body">
<main id="contenu" class="auth-main">
    <div class="auth-brand">
        <a class="brand" href="/">
            <span class="brand__logo" aria-hidden="true">&gt;_</span>
            <span class="brand__text">
                <span class="brand__name"><?= e($siteName) ?></span>
                <span class="brand__class">Administration</span>
            </span>
        </a>
    </div>
    <div class="auth-card">
<?= partial('site/flashes') ?>
<?= $content ?>
    </div>
    <p class="auth-foot"><a href="/">Retour au site</a></p>
</main>
</body>
</html>
