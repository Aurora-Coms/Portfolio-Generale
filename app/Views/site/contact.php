<?php
/**
 * Contact : formulaire et informations pratiques.
 *
 * Variables (ContactController::form) :
 *   $old        array<string,string>  valeurs saisies (name, email, subject, message, consent)
 *   $errors     array<string,string>  erreurs par champ ; '_form' = erreur générale (jeton, limite)
 *   $formTs     string  jeton horodaté signé (champ caché form_ts)
 *   $honeypot   string  nom du champ piège
 *   $limits     array   name, subject, messageMin, messageMax
 *   $linkedin   string  URL LinkedIn validée ou ''
 *   $github     string  URL GitHub validée ou ''
 *   $location   string  ville (réglage location)
 *   $internship string  « du 18 mai au 26 juin 2026 » si un stage est recherché, sinon ''
 */

declare(strict_types=1);

$old        = isset($old) && is_array($old) ? $old : [];
$errors     = isset($errors) && is_array($errors) ? $errors : [];
$formTs     = (string) ($formTs ?? '');
$honeypot   = (string) ($honeypot ?? 'website');
$limits     = isset($limits) && is_array($limits) ? $limits : [];
$linkedin   = (string) ($linkedin ?? '');
$github     = (string) ($github ?? '');
$location   = (string) ($location ?? '');
$internship = (string) ($internship ?? '');

$maxName    = (int) ($limits['name'] ?? 100);
$maxSubject = (int) ($limits['subject'] ?? 150);
$minMessage = (int) ($limits['messageMin'] ?? 10);
$maxMessage = (int) ($limits['messageMax'] ?? 5000);

$formError   = (string) ($errors['_form'] ?? '');
$fieldErrors = array_diff_key($errors, ['_form' => true]);
?>
<?= partial('site/page-header', [
    'comment' => '-- contact.sh',
    'title'   => 'Contact',
    'lead'    => static_site()
        ? 'Une proposition de stage, une question sur un projet ou sur mon parcours : contactez-moi via les liens ci-dessous.'
        : 'Une proposition de stage, une question sur un projet ou sur mon parcours : écrivez-moi avec ce formulaire.',
]) ?>

<div class="detail-layout">
    <div class="detail-main">
<?php if (static_site()): ?>
        <section class="card card--pad stack" aria-labelledby="titre-formulaire">
            <h2 class="card__title" id="titre-formulaire">Me contacter</h2>
            <p>Le formulaire de contact sera disponible sur la version hébergée du site. En attendant, le plus simple est de m'écrire sur LinkedIn.</p>
<?php if ($linkedin !== ''): ?>
            <p><a class="btn btn--primary" href="<?= e($linkedin) ?>" rel="noopener noreferrer"><?= icon('linkedin') ?>M'écrire sur LinkedIn</a></p>
<?php endif; ?>
<?php if ($github !== ''): ?>
            <p><a class="btn btn--secondary" href="<?= e($github) ?>" rel="noopener noreferrer"><?= icon('github') ?>Voir mon GitHub</a></p>
<?php endif; ?>
        </section>
<?php else: ?>
        <form class="form card card--pad" method="post" action="/contact" novalidate data-focus-error aria-labelledby="titre-formulaire">
            <h2 class="card__title" id="titre-formulaire">Envoyer un message</h2>
            <?= csrf_field() ?>
            <input type="hidden" name="form_ts" value="<?= e($formTs) ?>">
<?php if ($errors !== []): ?>
<?= partial('site/alert', [
    'type'    => 'error',
    'title'   => 'Le message n\'a pas pu être envoyé',
    'message' => $formError !== '' ? $formError : 'Corrigez les champs signalés ci-dessous.',
    'html'    => $formError !== '' && $fieldErrors !== [] ? '<p>Corrigez aussi les champs signalés ci-dessous.</p>' : '',
]) ?>
<?php endif; ?>
            <p class="form__note">Les champs marqués d'un astérisque (<span class="text-accent">*</span>) sont obligatoires.</p>
            <div class="form-row form-row--2">
<?= partial('site/field', [
    'name'      => 'name',
    'label'     => 'Nom',
    'required'  => true,
    'maxlength' => $maxName,
    'value'     => old($old, 'name'),
    'error'     => $errors['name'] ?? '',
    'attrs'     => ['autocomplete' => 'name'],
]) ?>
<?= partial('site/field', [
    'name'     => 'email',
    'type'     => 'email',
    'label'    => 'Adresse e-mail',
    'required' => true,
    'maxlength' => 254,
    'value'    => old($old, 'email'),
    'error'    => $errors['email'] ?? '',
    'attrs'    => ['autocomplete' => 'email', 'inputmode' => 'email', 'autocapitalize' => 'none', 'spellcheck' => 'false'],
]) ?>
            </div>
<?= partial('site/field', [
    'name'      => 'subject',
    'label'     => 'Sujet',
    'optional'  => true,
    'maxlength' => $maxSubject,
    'value'     => old($old, 'subject'),
    'error'     => $errors['subject'] ?? '',
]) ?>
<?= partial('site/field', [
    'name'      => 'message',
    'type'      => 'textarea',
    'label'     => 'Message',
    'required'  => true,
    'maxlength' => $maxMessage,
    'counter'   => true,
    'hint'      => 'Entre ' . $minMessage . ' et ' . $maxMessage . ' caractères.',
    'value'     => old($old, 'message'),
    'error'     => $errors['message'] ?? '',
]) ?>
<?= partial('site/field', [
    'name'     => 'consent',
    'type'     => 'checkbox',
    'required' => true,
    'label'    => 'J\'accepte que les informations saisies soient utilisées pour répondre à ma demande.',
    'value'    => old($old, 'consent'),
    'error'    => $errors['consent'] ?? '',
    'after'    => '<p class="field__hint"><a href="/mentions-legales#donnees">Comment mes données sont-elles utilisées ?</a></p>',
]) ?>
<?= partial('site/honeypot', ['name' => $honeypot]) ?>
            <div class="form__actions">
                <button class="btn btn--primary" type="submit"><?= icon('mail') ?>Envoyer le message</button>
            </div>
            <p class="form__note">Vos données (nom, e-mail, sujet, message) servent uniquement à vous répondre. Elles ne sont ni cédées ni revendues et sont supprimées automatiquement au bout de 12 mois. <a href="/mentions-legales#donnees">En savoir plus</a></p>
        </form>
<?php endif; ?>
    </div>

    <aside class="detail-aside card card--pad stack" aria-labelledby="titre-infos-contact">
        <p class="comment" aria-hidden="true">-- infos.lua</p>
        <h2 class="card__title" id="titre-infos-contact">Infos pratiques</h2>
        <dl class="info-list">
<?php if ($linkedin !== ''): ?>
            <div class="info-list__row"><dt>linkedin</dt><dd><a class="meta-item" href="<?= e($linkedin) ?>" rel="noopener noreferrer"><?= icon('linkedin', 16) ?>Profil LinkedIn</a></dd></div>
<?php endif; ?>
<?php if ($github !== ''): ?>
            <div class="info-list__row"><dt>github</dt><dd><a class="meta-item" href="<?= e($github) ?>" rel="noopener noreferrer"><?= icon('github', 16) ?>Profil GitHub</a></dd></div>
<?php endif; ?>
<?php if ($location !== ''): ?>
            <div class="info-list__row"><dt>localisation</dt><dd><?= e($location) ?></dd></div>
<?php endif; ?>
<?php if ($internship !== ''): ?>
            <div class="info-list__row"><dt>disponibilité</dt><dd><span class="availability" aria-hidden="true">● </span>Stage recherché <?= e($internship) ?>, en développement informatique ou en réseaux.</dd></div>
<?php endif; ?>
            <div class="info-list__row"><dt>réponse</dt><dd>Je lis chaque message et réponds en général sous quelques jours.</dd></div>
        </dl>
    </aside>
</div>
