<?php
/**
 * Gabarit de l'administration (pages protégées).
 *
 * Variables :
 *   $content  string  HTML de la page (fourni par View::render)
 *   $meta     array   (optionnel) title ; robots est toujours « noindex, nofollow »
 *
 * Chaque vue commence par partial('admin/page-header', [...]) (titre h1 + actions).
 * Les messages flash sont affichés ici : les vues n'ont pas à les appeler.
 *
 * @var string $content
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Models\Message;

$meta      = isset($meta) && is_array($meta) ? $meta : [];
$siteName  = setting('site_name', 'Alexandre Derbez');
$pageTitle = trim((string) ($meta['title'] ?? ''));
$fullTitle = ($pageTitle !== '' ? $pageTitle . ' · ' : '') . 'Administration · ' . $siteName;

// Badge des messages non lus : ne doit jamais casser la page.
$unread = 0;
try {
    $unread = Message::unreadCount();
} catch (\Throwable) {
    $unread = 0;
}

$username = '';
try {
    $user = Auth::user();
    $username = is_array($user) ? (string) ($user['username'] ?? '') : '';
} catch (\Throwable) {
    $username = '';
}
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
<body class="admin-body">
<a class="skip-link" href="#contenu">Aller au contenu</a>

<div class="admin-shell">
    <header class="admin-topbar">
        <a class="brand" href="/admin">
            <span class="brand__logo" aria-hidden="true">&gt;_</span>
            <span class="brand__text">
                <span class="brand__name">Administration</span>
                <span class="brand__class"><?= e($siteName) ?></span>
            </span>
        </a>
        <a class="admin-menu-toggle" href="#admin-nav" data-admin-menu-link><?= icon('menu', 18) ?>Menu</a>
        <button class="admin-menu-toggle" type="button" aria-expanded="false" aria-controls="admin-nav" data-admin-menu-toggle hidden>
            <?= icon('menu', 18, 'icon icon-open') ?><?= icon('close', 18, 'icon icon-close') ?>Menu
        </button>
    </header>

    <aside class="admin-sidebar" id="admin-nav" aria-label="Menu de l'administration">
        <div class="admin-sidebar__brand">
            <a class="brand" href="/admin">
                <span class="brand__logo" aria-hidden="true">&gt;_</span>
                <span class="brand__text">
                    <span class="brand__name">Administration</span>
                    <span class="brand__class"><?= e($siteName) ?></span>
                </span>
            </a>
        </div>
<?= partial('admin/sidebar', ['unread' => $unread, 'username' => $username]) ?>
    </aside>

    <div class="admin-main">
        <main id="contenu" class="admin-content" tabindex="-1">
<?= partial('site/flashes') ?>
<?= $content ?>
        </main>
    </div>
</div>
</body>
</html>
