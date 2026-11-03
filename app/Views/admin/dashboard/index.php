<?php
/**
 * Tableau de bord de l'administration.
 *
 * Variables :
 *   $counts          array  Project::counts()
 *   $featuredVisible int    projets publiés ET mis en avant
 *   $unread          int    messages non lus
 *   $messages        list   Message::latest(5)
 *   $drafts          list   brouillons (format Project::adminList())
 *   $alerts          list   points de vigilance [type, title, message, link]
 *   $username        string identifiant de l'administrateur
 */

declare(strict_types=1);

$counts          = isset($counts) && is_array($counts) ? $counts : [];
$featuredVisible = (int) ($featuredVisible ?? 0);
$unread          = (int) ($unread ?? 0);
$messages        = isset($messages) && is_array($messages) ? $messages : [];
$drafts          = isset($drafts) && is_array($drafts) ? $drafts : [];
$alerts          = isset($alerts) && is_array($alerts) ? $alerts : [];
$username        = (string) ($username ?? '');

$published     = (int) ($counts['published'] ?? 0);
$draftCount    = (int) ($counts['drafts'] ?? 0);
$featuredTotal = (int) ($counts['featured'] ?? 0);
$featuredHidden = max(0, $featuredTotal - $featuredVisible);

$plural = static fn (int $n, string $one, string $many): string => $n . ' ' . ($n > 1 ? $many : $one);

/** Ce qu'il reste à faire sur un brouillon (indications simples). */
$todo = static function (array $p): array {
    $items = [];
    if ((int) ($p['image_count'] ?? 0) === 0) {
        $items[] = 'aucune image';
    }
    if (($p['categories'] ?? []) === []) {
        $items[] = 'aucune catégorie';
    }
    foreach (['context' => 'contexte', 'work_done' => 'réalisation', 'learnings' => 'apprentissages'] as $col => $label) {
        $text = trim((string) ($p[$col] ?? ''));
        if ($text === '' || mb_stripos($text, 'à compléter') !== false) {
            $items[] = $label . ' à rédiger';
        }
    }
    return $items;
};
?>
<?= partial('admin/page-header', [
    'comment' => '-- tableau-de-bord/',
    'title'   => 'Tableau de bord',
    'lead'    => $username !== '' ? 'Bonjour ' . $username . '. Voici l\'état du site.' : 'Voici l\'état du site.',
    'actions' => '<a class="btn btn--primary" href="/admin/projets/nouveau">' . icon('edit', 16) . 'Nouveau projet</a>',
]) ?>

<div class="stack-lg">
<section aria-labelledby="titre-chiffres">
    <h2 class="sr-only" id="titre-chiffres">Chiffres clés</h2>
    <ul class="stats">
        <?= partial('admin/stat', [
            'label' => 'Projets publiés',
            'value' => $published,
            'icon'  => 'folder',
            'href'  => '/admin/projets',
            'hint'  => 'Sur ' . $plural($published + $draftCount, 'projet', 'projets') . ' au total',
        ]) ?>
        <?= partial('admin/stat', [
            'label' => 'Brouillons',
            'value' => $draftCount,
            'icon'  => 'edit',
            'href'  => '#titre-brouillons',
            'hint'  => $draftCount > 0 ? 'Invisibles sur le site' : 'Rien en attente',
        ]) ?>
        <?= partial('admin/stat', [
            'label' => 'En avant',
            'value' => $featuredVisible,
            'icon'  => 'star',
            'hint'  => 'Visibles sur l\'accueil' . ($featuredHidden > 0 ? ' · ' . $featuredHidden . ' en brouillon' : ''),
        ]) ?>
        <?= partial('admin/stat', [
            'label' => 'Messages non lus',
            'value' => $unread,
            'icon'  => 'mail',
            'href'  => '/admin/messages',
            'hint'  => $unread > 0 ? 'À traiter' : 'Boîte à jour',
        ]) ?>
    </ul>
</section>

<section aria-labelledby="titre-vigilance">
    <h2 class="section-title" id="titre-vigilance">Points de vigilance</h2>
<?php if ($alerts === []): ?>
    <?= partial('site/alert', ['type' => 'success', 'role' => '', 'message' => 'Aucun problème de sécurité ou de configuration détecté.']) ?>
<?php else: ?>
    <ul class="alert-list">
<?php foreach ($alerts as $alert):
    $link = isset($alert['link']) && is_array($alert['link']) ? $alert['link'] : null;
    $html = $link !== null
        ? '<p><a href="' . e((string) $link['href']) . '">' . e((string) $link['label']) . '</a></p>'
        : '';
?>
        <li><?= partial('site/alert', [
            'type'    => (string) $alert['type'],
            'title'   => (string) $alert['title'],
            'message' => (string) $alert['message'],
            'html'    => $html,
            'role'    => '',
        ]) ?></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
</section>

<div class="grid grid--2">
    <section class="card" aria-labelledby="titre-messages">
        <div class="card__head">
            <h2 class="card__title" id="titre-messages">Derniers messages</h2>
            <a class="btn btn--ghost btn--sm" href="/admin/messages">Tout voir<span class="sr-only"> (messages)</span></a>
        </div>
<?php if ($messages === []): ?>
        <div class="card__body">
            <?= partial('site/empty-state', ['icon' => 'mail', 'comment' => '-- 0 message', 'title' => 'Aucun message', 'text' => 'Les messages du formulaire de contact apparaîtront ici.']) ?>
        </div>
<?php else: ?>
        <ul class="item-list">
<?php foreach ($messages as $m):
    $isUnread = (int) ($m['is_read'] ?? 1) === 0;
?>
            <li class="item-list__item<?= $isUnread ? ' is-unread' : '' ?>">
                <div class="item-list__main">
                    <a class="item-list__title" href="/admin/messages/<?= (int) $m['id'] ?>"><?= e(str_limit((string) $m['subject'], 80)) ?></a>
                    <span class="item-list__meta"><?= e((string) $m['name']) ?> · <?= e(datetime_fr((string) $m['created_at'])) ?></span>
                </div>
<?php if ($isUnread): ?>
                <span class="badge badge--unread">Non lu</span>
<?php endif; ?>
            </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
    </section>

    <section class="card" aria-labelledby="titre-brouillons">
        <div class="card__head">
            <h2 class="card__title" id="titre-brouillons" tabindex="-1">Brouillons à compléter</h2>
            <a class="btn btn--ghost btn--sm" href="/admin/projets">Tous les projets</a>
        </div>
<?php if ($drafts === []): ?>
        <div class="card__body">
            <?= partial('site/empty-state', ['icon' => 'check', 'comment' => '-- 0 brouillon', 'title' => 'Aucun brouillon', 'text' => 'Tous les projets sont publiés.']) ?>
        </div>
<?php else: ?>
        <ul class="item-list">
<?php foreach ($drafts as $p):
    $items = $todo($p);
?>
            <li class="item-list__item">
                <div class="item-list__main">
                    <a class="item-list__title" href="/admin/projets/<?= (int) $p['id'] ?>/modifier"><?= e((string) $p['title']) ?></a>
                    <span class="item-list__meta"><?= e(section_label((string) $p['section'])) ?> · <?= e($plural((int) $p['image_count'], 'image', 'images')) ?></span>
<?php if ($items !== []): ?>
                    <span class="item-list__meta text-soft">À faire : <?= e(implode(', ', $items)) ?></span>
<?php endif; ?>
                </div>
                <a class="btn btn--secondary btn--sm btn--icon" href="/admin/projets/<?= (int) $p['id'] ?>/images" aria-label="Images de « <?= e((string) $p['title']) ?> »" title="Images"><?= icon('image', 16) ?></a>
            </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
    </section>
</div>

<section class="card card--pad stack" aria-labelledby="titre-raccourcis">
    <h2 class="card__title" id="titre-raccourcis">Raccourcis</h2>
    <div class="btn-row">
        <a class="btn btn--secondary btn--sm" href="/admin/projets/nouveau"><?= icon('edit', 16) ?>Nouveau projet</a>
        <a class="btn btn--secondary btn--sm" href="/admin/categories"><?= icon('folder', 16) ?>Catégories</a>
        <a class="btn btn--secondary btn--sm" href="/admin/tags"><?= icon('tag', 16) ?>Tags</a>
        <a class="btn btn--secondary btn--sm" href="/admin/parcours"><?= icon('calendar', 16) ?>Parcours</a>
        <a class="btn btn--secondary btn--sm" href="/admin/competences"><?= icon('chip', 16) ?>Compétences</a>
        <a class="btn btn--secondary btn--sm" href="/admin/reglages"><?= icon('settings', 16) ?>Réglages et CV</a>
        <a class="btn btn--secondary btn--sm" href="/admin/compte"><?= icon('lock', 16) ?>Mon compte</a>
        <a class="btn btn--secondary btn--sm" href="/" target="_blank" rel="noopener noreferrer"><?= icon('external', 16) ?>Voir le site<span class="sr-only"> (nouvel onglet)</span></a>
    </div>
</section>
</div>
