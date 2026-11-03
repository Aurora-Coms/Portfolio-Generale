<?php
/**
 * Compte administrateur : informations, mot de passe, double authentification.
 *
 * @var array<string, mixed>  $admin      ligne admins de l'utilisateur connecté
 * @var bool                  $enabled    double authentification active ?
 * @var int                   $remaining  codes de secours non utilisés
 * @var array<string, string> $errors     erreurs (clés distinctes pour chaque formulaire)
 */

declare(strict_types=1);

$username = (string) $admin['username'];
$codeAttrs = ['autocomplete' => 'one-time-code', 'inputmode' => 'numeric', 'pattern' => '[0-9 ]{6,7}', 'maxlength' => '7',
    'autocapitalize' => 'none', 'spellcheck' => 'false'];
?>
<?= partial('admin/page-header', [
    'comment' => '-- compte/',
    'title'   => 'Mon compte',
    'lead'    => 'Votre mot de passe et la double authentification protègent l\'accès à toute l\'administration.',
]) ?>

<div class="stack-lg">
<div class="account-grid">
    <section class="card card--pad stack" aria-labelledby="titre-identite">
        <h2 class="card__title" id="titre-identite">Identité</h2>
        <dl class="dl dl--stacked">
            <div class="dl__row"><dt>identifiant</dt><dd class="mono"><?= e($username) ?></dd></div>
            <div class="dl__row"><dt>dernière connexion</dt><dd><?= $admin['last_login_at'] !== null ? e(datetime_fr((string) $admin['last_login_at'])) : 'jamais' ?></dd></div>
            <div class="dl__row"><dt>compte créé le</dt><dd><?= e(datetime_fr((string) $admin['created_at'])) ?></dd></div>
            <div class="dl__row">
                <dt>double authentification</dt>
                <dd>
<?php if ($enabled): ?>
                    <span class="badge badge--success">activée</span>
<?php else: ?>
                    <span class="badge badge--warning">désactivée</span>
<?php endif; ?>
                </dd>
            </div>
<?php if ($enabled): ?>
            <div class="dl__row">
                <dt>codes de secours</dt>
                <dd>
                    <span class="badge <?= $remaining <= 2 ? 'badge--error' : 'badge--plain' ?>"><?= $remaining ?> sur 8 restant<?= $remaining > 1 ? 's' : '' ?></span>
                </dd>
            </div>
<?php endif; ?>
        </dl>
<?php if ($enabled && $remaining <= 2): ?>
        <?= partial('site/alert', ['type' => 'warning', 'role' => '', 'message' => 'Il vous reste peu de codes de secours : régénérez-en de nouveaux ci-dessous.']) ?>
<?php endif; ?>
    </section>

    <section class="card card--pad stack" aria-labelledby="titre-mdp">
        <h2 class="card__title" id="titre-mdp">Changer le mot de passe</h2>
        <form class="form" method="post" action="/admin/compte/mot-de-passe" novalidate data-focus-error>
            <?= csrf_field() ?>
            <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" hidden>
            <?= partial('site/field', ['name' => 'current_password', 'type' => 'password', 'label' => 'Mot de passe actuel', 'required' => true,
                'error' => $errors['current_password'] ?? '', 'attrs' => ['autocomplete' => 'current-password']]) ?>
            <?= partial('site/field', ['name' => 'new_password', 'type' => 'password', 'label' => 'Nouveau mot de passe', 'required' => true,
                'hint' => '12 caractères au minimum. Une phrase de passe (plusieurs mots au hasard) est à la fois sûre et facile à retenir.',
                'error' => $errors['new_password'] ?? '', 'attrs' => ['autocomplete' => 'new-password', 'minlength' => '12']]) ?>
            <?= partial('site/field', ['name' => 'new_password_confirmation', 'type' => 'password', 'label' => 'Confirmer le nouveau mot de passe', 'required' => true,
                'error' => $errors['new_password_confirmation'] ?? '', 'attrs' => ['autocomplete' => 'new-password']]) ?>
            <div class="form__actions">
                <button class="btn btn--primary" type="submit"><?= icon('lock', 16) ?>Modifier le mot de passe</button>
            </div>
        </form>
    </section>
</div>

<section class="card" aria-labelledby="titre-2fa">
    <div class="card__head">
        <h2 class="card__title" id="titre-2fa">Double authentification (2FA)</h2>
        <?= $enabled ? '<span class="badge badge--success">activée</span>' : '<span class="badge badge--warning">désactivée</span>' ?>
    </div>
    <div class="card__body stack">
        <p class="text-soft measure">
            À chaque connexion, en plus du mot de passe, un code à 6 chiffres est demandé. Ce code change toutes les
            30 secondes et n'est affiché que par une application installée sur votre téléphone : un mot de passe
            volé ne suffit plus pour entrer.
        </p>
<?php if (!$enabled): ?>
        <p class="text-soft measure">
            Applications compatibles (gratuites) : <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong>,
            <strong>Aegis</strong> (Android, open source), <strong>2FAS</strong>, ou le gestionnaire de mots de passe de votre choix.
        </p>
        <div class="btn-row">
            <a class="btn btn--primary" href="/admin/compte/2fa"><?= icon('shield', 16) ?>Activer la double authentification</a>
        </div>
<?php else: ?>
        <div class="grid grid--2">
            <form class="form card card--pad card--inset" method="post" action="/admin/compte/2fa/codes" novalidate data-focus-error>
                <?= csrf_field() ?>
                <h3 class="subsection-title">Nouveaux codes de secours</h3>
                <p class="field__hint">Un code de secours remplace le code à 6 chiffres si vous n'avez plus votre téléphone. Les anciens codes ne fonctionneront plus.</p>
                <?= partial('site/field', ['name' => 'codes_password', 'type' => 'password', 'label' => 'Mot de passe actuel', 'required' => true,
                    'error' => $errors['codes_password'] ?? '', 'attrs' => ['autocomplete' => 'current-password']]) ?>
                <div class="form__actions">
                    <button class="btn btn--secondary" type="submit"><?= icon('star', 16) ?>Générer 8 nouveaux codes</button>
                </div>
            </form>

            <form class="form card card--pad card--inset card--danger" method="post" action="/admin/compte/2fa/desactiver" novalidate data-focus-error
                  data-confirm="Désactiver la double authentification ? Votre compte ne sera plus protégé que par le mot de passe.">
                <?= csrf_field() ?>
                <h3 class="subsection-title">Désactiver la double authentification</h3>
                <p class="field__hint">Déconseillé. Confirmez avec votre mot de passe et le code actuel de l'application.</p>
                <?= partial('site/field', ['name' => 'disable_password', 'type' => 'password', 'label' => 'Mot de passe actuel', 'required' => true,
                    'error' => $errors['disable_password'] ?? '', 'attrs' => ['autocomplete' => 'current-password']]) ?>
                <?= partial('site/field', ['name' => 'disable_code', 'label' => 'Code à 6 chiffres', 'required' => true, 'class' => 'input--mono input--code',
                    'error' => $errors['disable_code'] ?? '', 'attrs' => $codeAttrs]) ?>
                <div class="form__actions">
                    <button class="btn btn--danger" type="submit"><?= icon('close', 16) ?>Désactiver</button>
                </div>
            </form>
        </div>
<?php endif; ?>
    </div>
</section>
</div>
