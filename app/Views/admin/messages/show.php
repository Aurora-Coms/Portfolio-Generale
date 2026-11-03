<?php
/**
 * Lecture d'un message de contact (déjà marqué comme lu par le contrôleur).
 * Tout le contenu vient d'un visiteur : il est ÉCHAPPÉ (e()), retours à la
 * ligne conservés par nl2br().
 *
 * @var array<string, mixed> $message
 * @var string               $replyTo  lien mailto: (vide si l'adresse est invalide)
 * @var int                  $months   durée de conservation
 */

declare(strict_types=1);

$id   = (int) $message['id'];
$name = (string) $message['name'];

$received = date_create_immutable((string) $message['created_at']);
$expires  = $received ? $received->modify('+' . $months . ' months') : null;
?>
<?= partial('admin/page-header', [
    'back'    => ['href' => '/admin/messages', 'label' => 'Messages'],
    'comment' => '-- messages/' . $id,
    'title'   => (string) $message['subject'],
    'lead'    => 'Message de ' . $name . ', reçu le ' . datetime_fr((string) $message['created_at']) . '.',
]) ?>

<div class="message-layout">
    <article class="card" aria-labelledby="titre-message">
        <div class="card__head">
            <h2 class="card__title" id="titre-message">Message</h2>
            <span class="badge badge--success">Lu</span>
        </div>
        <div class="card__body">
            <div class="message-body message-body--html"><?= nl2br(e((string) $message['body']), false) ?></div>
        </div>
        <div class="card__foot">
            <div class="btn-row">
<?php if ($replyTo !== ''): ?>
                <a class="btn btn--primary" href="<?= e($replyTo) ?>"><?= icon('mail', 16) ?>Répondre</a>
<?php endif; ?>
<?= partial('admin/post-button', [
    'action' => '/admin/messages/' . $id . '/lu',
    'label'  => 'Marquer comme non lu',
    'icon'   => 'mail',
    'size'   => 'md',
]) ?>
<?= partial('admin/post-button', [
    'action'  => '/admin/messages/' . $id . '/supprimer',
    'label'   => 'Supprimer',
    'icon'    => 'trash',
    'variant' => 'danger',
    'size'    => 'md',
    'confirm' => 'Supprimer définitivement le message de ' . $name . ' ?',
]) ?>
            </div>
        </div>
    </article>

    <aside class="card card--pad stack" aria-labelledby="titre-infos">
        <h2 class="card__title" id="titre-infos">Informations</h2>
        <dl class="dl dl--stacked">
            <div class="dl__row"><dt>de</dt><dd><?= e($name) ?></dd></div>
            <div class="dl__row"><dt>e-mail</dt><dd class="break"><?php if ($replyTo !== ''): ?><a href="<?= e($replyTo) ?>"><?= e((string) $message['email']) ?></a><?php else: ?><?= e((string) $message['email']) ?> <span class="badge badge--error">adresse invalide</span><?php endif; ?></dd></div>
            <div class="dl__row"><dt>sujet</dt><dd><?= e((string) $message['subject']) ?></dd></div>
            <div class="dl__row"><dt>reçu le</dt><dd><?= e(datetime_fr((string) $message['created_at'])) ?></dd></div>
            <div class="dl__row"><dt>envoyé par e-mail</dt><dd><?= (int) $message['mail_sent'] === 1 ? 'oui : une copie vous a été envoyée' : 'non : consultable ici uniquement' ?></dd></div>
<?php if ($expires !== null): ?>
            <div class="dl__row"><dt>suppression auto.</dt><dd><?= e(date_fr($expires->format('Y-m-d'))) ?> (RGPD)</dd></div>
<?php endif; ?>
        </dl>
    </aside>
</div>
