<?php
/**
 * Navigation latérale de l'administration (utilisée par layouts/admin).
 * Les vues n'ont pas à l'appeler.
 *
 * Variables :
 *   $unread   int     nombre de messages non lus (badge)
 *   $username string  (optionnel) identifiant de l'administrateur connecté
 */

declare(strict_types=1);

use App\Core\Request;

$unread   = max(0, (int) ($unread ?? 0));
$username = (string) ($username ?? '');
$path     = Request::path();

// Groupe => liste [libellé, lien, icône, actif ?]
$groups = [
    '-- contenu/' => [
        ['Tableau de bord', '/admin', 'home', $path === '/admin' || $path === '/admin/'],
        ['Projets', '/admin/projets', 'folder', is_current('/admin/projets') || is_current('/admin/images')],
        ['Catégories', '/admin/categories', 'folder', is_current('/admin/categories')],
        ['Tags', '/admin/tags', 'tag', is_current('/admin/tags')],
        ['Parcours', '/admin/parcours', 'calendar', is_current('/admin/parcours')],
        ['Compétences', '/admin/competences', 'chip', is_current('/admin/competences')
            && !is_current('/admin/competences/themes') && !is_current('/admin/competences/categories')],
        ['Thèmes de compétences', '/admin/competences/themes', 'book', is_current('/admin/competences/themes')],
        ['Sous-catégories de compétences', '/admin/competences/categories', 'code', is_current('/admin/competences/categories')],
        ['Centres d\'intérêt', '/admin/interets', 'star', is_current('/admin/interets')],
        ['À propos', '/admin/a-propos', 'user', is_current('/admin/a-propos')],
    ],
    '-- site/' => [
        ['Messages', '/admin/messages', 'mail', is_current('/admin/messages')],
        ['Réglages', '/admin/reglages', 'settings', is_current('/admin/reglages')],
        ['Mon compte', '/admin/compte', 'lock', is_current('/admin/compte')],
    ],
];
?>
<nav class="admin-nav" aria-label="Administration">
<?php foreach ($groups as $title => $items): ?>
    <p class="admin-nav__title" aria-hidden="true"><?= e($title) ?></p>
    <ul class="admin-nav__list">
<?php foreach ($items as [$label, $href, $iconName, $active]): ?>
        <li>
            <a class="admin-nav__link" href="<?= e($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                <?= icon($iconName, 18) ?>
                <span><?= e($label) ?></span>
<?php if ($href === '/admin/messages' && $unread > 0): ?>
                <span class="admin-nav__count"><?= $unread ?><span class="sr-only"> non lu<?= $unread > 1 ? 's' : '' ?></span></span>
<?php endif; ?>
            </a>
        </li>
<?php endforeach; ?>
    </ul>
<?php endforeach; ?>
</nav>
<div class="admin-sidebar__foot">
<?php if ($username !== ''): ?>
    <p class="admin-sidebar__user">Connecté : <strong><?= e($username) ?></strong></p>
<?php endif; ?>
    <ul class="admin-nav__list">
        <li>
            <a class="admin-nav__link" href="/" target="_blank" rel="noopener noreferrer">
                <?= icon('external', 18) ?><span>Voir le site<span class="sr-only"> (nouvel onglet)</span></span>
            </a>
        </li>
        <li>
            <form class="admin-nav__form" method="post" action="/admin/deconnexion">
                <?= csrf_field() ?>
                <button type="submit" class="admin-nav__link"><?= icon('logout', 18) ?><span>Déconnexion</span></button>
            </form>
        </li>
    </ul>
</div>
