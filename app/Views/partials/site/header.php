<?php
/**
 * En-tête du site : logo, navigation en onglets, menu mobile.
 *
 * Variables :
 *   $siteName string                 nom affiché (setting site_name)
 *   $links    array{linkedin:string, github:string}  URL externes déjà validées (ou '')
 */

declare(strict_types=1);

$siteName = isset($siteName) ? (string) $siteName : setting('site_name', 'Alexandre Derbez');
$links    = isset($links) && is_array($links) ? $links : ['linkedin' => '', 'github' => ''];
$class    = setting('site_class', 'BTS CIEL · IR');

// Onglets : libellé, lien, page active ?
$tabs = [
    ['label' => 'Accueil',     'href' => '/',             'active' => is_current('/')],
    ['label' => 'Projets',     'href' => '/projets/jeux', 'active' => is_current('/projets') || is_current('/projet')],
    ['label' => 'Parcours',    'href' => '/parcours',     'active' => is_current('/parcours')],
    ['label' => 'Compétences', 'href' => '/competences',  'active' => is_current('/competences')],
    ['label' => 'À propos',    'href' => '/a-propos',     'active' => is_current('/a-propos')],
    ['label' => 'Contact',     'href' => '/contact',      'active' => is_current('/contact')],
];
?>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="/">
            <span class="brand__logo" aria-hidden="true">&gt;_</span>
            <span class="brand__text">
                <span class="brand__name"><?= e($siteName) ?></span>
<?php if ($class !== ''): ?>
                <span class="brand__class"><?= e($class) ?></span>
<?php endif; ?>
            </span>
        </a>

        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="menu-mobile" aria-label="Menu" data-menu-toggle hidden>
            <?= icon('menu', 20, 'icon icon-open') ?>
            <?= icon('close', 20, 'icon icon-close') ?>
        </button>

        <nav class="tabs" aria-label="Navigation principale" data-tabs>
            <ul class="tabs__list">
<?php foreach ($tabs as $tab): ?>
                <li><a class="tabs__link" href="<?= e($tab['href']) ?>"<?= $tab['active'] ? ' aria-current="page"' : '' ?>><?= e($tab['label']) ?></a></li>
<?php endforeach; ?>
            </ul>
        </nav>
    </div>

    <div class="menu-panel" id="menu-mobile" data-menu-panel hidden>
        <nav class="container menu-panel__inner" aria-label="Menu complet">
            <p class="menu-panel__title" aria-hidden="true">-- pages/</p>
            <ul class="menu-panel__list">
<?php foreach ($tabs as $tab): ?>
                <li><a class="menu-panel__link" href="<?= e($tab['href']) ?>"<?= $tab['active'] ? ' aria-current="page"' : '' ?>><?= e($tab['label']) ?></a></li>
<?php endforeach; ?>
            </ul>
            <p class="menu-panel__title" aria-hidden="true">-- liens/</p>
            <ul class="menu-panel__list">
<?php if ($links['linkedin'] !== ''): ?>
                <li><a class="menu-panel__link" href="<?= e($links['linkedin']) ?>" rel="noopener noreferrer"><?= icon('linkedin') ?>LinkedIn</a></li>
<?php endif; ?>
<?php if ($links['github'] !== ''): ?>
                <li><a class="menu-panel__link" href="<?= e($links['github']) ?>" rel="noopener noreferrer"><?= icon('github') ?>GitHub</a></li>
<?php endif; ?>
<?php if (App\Core\PdfUpload::exists()): ?>
                <li><a class="menu-panel__link" href="/cv"><?= icon('download') ?>CV (PDF)</a></li>
<?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
