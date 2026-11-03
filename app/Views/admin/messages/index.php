<?php
/**
 * Boîte de réception des messages de contact (20 par page, plus récents en premier).
 *
 * @var list<array<string, mixed>> $messages
 * @var int                        $total
 * @var int                        $unread
 * @var int                        $page
 * @var int                        $pages
 * @var int                        $purged   messages supprimés par la purge RGPD à l'instant
 * @var int                        $months   durée de conservation
 */

declare(strict_types=1);

$lead = $total === 0
    ? 'Les messages envoyés depuis la page « Contact » arrivent ici.'
    : $total . ' message' . ($total > 1 ? 's' : '') . ' · ' . $unread . ' non lu' . ($unread > 1 ? 's' : '') . '.';
?>
<?= partial('admin/page-header', [
    'comment' => '-- messages/',
    'title'   => 'Messages',
    'lead'    => $lead,
]) ?>

<div class="flashes">
<?= partial('site/alert', [
    'type'    => 'info',
    'role'    => '',
    'title'   => 'Protection des données (RGPD)',
    'message' => 'Chaque message est supprimé automatiquement ' . $months . ' mois après sa réception. Répondez depuis votre messagerie puis supprimez les messages traités.',
]) ?>
<?php if ($purged > 0): ?>
<?= partial('site/alert', [
    'type'    => 'info',
    'message' => $purged . ' message' . ($purged > 1 ? 's' : '') . ' de plus de ' . $months . ' mois ' . ($purged > 1 ? 'ont' : 'a') . ' été supprimé' . ($purged > 1 ? 's' : '') . ' automatiquement.',
]) ?>
<?php endif; ?>
</div>

<?php if ($messages === []): ?>
<?= partial('site/empty-state', [
    'comment' => '-- 0 message',
    'icon'    => 'mail',
    'title'   => 'Aucun message',
    'text'    => 'Vous recevrez ici les propositions de stage et les questions envoyées par le formulaire de contact.',
]) ?>
<?php else: ?>
<div class="table-wrap" tabindex="0" role="region" aria-label="Messages reçus<?= $pages > 1 ? ', page ' . $page . ' sur ' . $pages : '' ?>">
    <table class="table">
        <thead>
            <tr>
                <th scope="col">Expéditeur</th>
                <th scope="col">Sujet et extrait</th>
                <th scope="col">Reçu le</th>
                <th scope="col">Envoyé par e-mail</th>
                <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($messages as $message):
    $id     = (int) $message['id'];
    $isRead = (int) $message['is_read'] === 1;
    $name   = (string) $message['name'];
?>
            <tr<?= $isRead ? '' : ' class="is-unread"' ?>>
                <td>
                    <a class="table__title" href="/admin/messages/<?= $id ?>"><?= e($name) ?></a>
<?php if (!$isRead): ?>
                    <span class="badge badge--unread">Non lu</span>
<?php endif; ?>
                    <span class="table__sub"><?= e((string) $message['email']) ?></span>
                </td>
                <td class="message-cell">
                    <span class="message-cell__subject"><?= e((string) $message['subject']) ?></span>
                    <span class="table__sub"><?= e(str_limit((string) preg_replace('/\s+/u', ' ', (string) $message['body']), 90)) ?></span>
                </td>
                <td class="mono nowrap small"><?= e(datetime_fr((string) $message['created_at'])) ?></td>
                <td>
<?php if ((int) $message['mail_sent'] === 1): ?>
                    <span class="badge badge--success">oui</span>
<?php else: ?>
                    <span class="badge badge--draft">non</span>
<?php endif; ?>
                </td>
                <td class="table__actions">
                    <div class="btn-row">
                        <a class="btn btn--secondary btn--sm btn--icon" href="/admin/messages/<?= $id ?>" aria-label="Lire le message de <?= e($name) ?>" title="Lire"><?= icon('eye', 16) ?></a>
<?= partial('admin/post-button', [
    'action'   => '/admin/messages/' . $id . '/lu',
    'label'    => ($isRead ? 'Marquer comme non lu : ' : 'Marquer comme lu : ') . $name,
    'icon'     => $isRead ? 'mail' : 'check',
    'iconOnly' => true,
    'fields'   => ['page' => (string) $page],
]) ?>
<?= partial('admin/post-button', [
    'action'   => '/admin/messages/' . $id . '/supprimer',
    'label'    => 'Supprimer le message de ' . $name,
    'icon'     => 'trash',
    'iconOnly' => true,
    'variant'  => 'danger',
    'confirm'  => 'Supprimer définitivement le message de ' . $name . ' ?',
    'fields'   => ['page' => (string) $page],
]) ?>
                    </div>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= partial('site/pagination', ['page' => $page, 'pages' => $pages, 'path' => '/admin/messages']) ?>
<?php endif; ?>
