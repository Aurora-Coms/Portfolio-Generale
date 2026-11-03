<?php
/**
 * Activation de la double authentification : QR code, clé manuelle, confirmation.
 * Le secret n'est encore qu'en session : rien n'est enregistré tant qu'un code
 * valide n'a pas été saisi.
 *
 * @var string                $qr        <svg> produit par QrCode::svg() (sûr, sans style en ligne)
 * @var string                $uri       URI otpauth:// (lien direct vers l'application sur téléphone)
 * @var string                $secret    clé en groupes de 4 caractères
 * @var string                $issuer
 * @var string                $username
 * @var array<string, string> $errors
 */

declare(strict_types=1);
?>
<?= partial('admin/page-header', [
    'back'    => ['href' => '/admin/compte', 'label' => 'Mon compte'],
    'comment' => '-- compte/2fa',
    'title'   => 'Activer la double authentification',
    'lead'    => 'Trois étapes, environ deux minutes. Gardez votre téléphone à portée de main.',
]) ?>

<ol class="steps">
    <li class="steps__item card card--pad">
        <h2 class="steps__title">Installez une application d'authentification</h2>
        <p class="text-soft">Sur votre téléphone, installez l'une de ces applications gratuites (si vous n'en avez pas déjà une) :</p>
        <ul class="app-list">
            <li><strong>Google Authenticator</strong> <span class="text-muted">Android, iPhone</span></li>
            <li><strong>Microsoft Authenticator</strong> <span class="text-muted">Android, iPhone</span></li>
            <li><strong>Aegis</strong> <span class="text-muted">Android, open source</span></li>
            <li><strong>2FAS</strong> <span class="text-muted">Android, iPhone, open source</span></li>
        </ul>
    </li>

    <li class="steps__item card card--pad">
        <h2 class="steps__title">Ajoutez le compte dans l'application</h2>
        <div class="qr-setup">
            <div class="qr-setup__code">
                <div class="qr"><?= $qr ?></div>
            </div>
            <div class="stack">
                <p class="text-soft">Dans l'application, choisissez « Ajouter un compte » puis <strong>scannez ce QR code</strong>.</p>
                <p class="text-soft">Vous êtes sur le téléphone ? <a href="<?= e($uri) ?>">Ouvrir directement dans l'application</a>, ou saisissez la clé à la main :</p>
                <dl class="dl dl--stacked">
                    <div class="dl__row"><dt>clé secrète</dt><dd><code class="secret-key" id="cle-secrete"><?= e($secret) ?></code></dd></div>
                    <div class="dl__row"><dt>compte</dt><dd><?= e($issuer) ?> (<?= e($username) ?>)</dd></div>
                    <div class="dl__row"><dt>type</dt><dd>basé sur le temps (TOTP), 6 chiffres, 30 secondes</dd></div>
                </dl>
                <p class="field__hint">Ne partagez jamais cette clé : elle permet de produire vos codes. Elle ne sera plus affichée une fois la double authentification activée.</p>
            </div>
        </div>
    </li>

    <li class="steps__item card card--pad">
        <h2 class="steps__title" id="titre-confirmer">Confirmez avec un code</h2>
        <form class="form form--narrow" method="post" action="/admin/compte/2fa" novalidate data-focus-error aria-labelledby="titre-confirmer">
            <?= csrf_field() ?>
<?php if ($errors !== []): ?>
            <?= partial('site/alert', ['type' => 'error', 'title' => 'La double authentification n\'est pas encore activée', 'message' => 'Corrigez les champs signalés ci-dessous.']) ?>
<?php endif; ?>
            <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" hidden>
            <?= partial('site/field', ['name' => 'current_password', 'type' => 'password', 'label' => 'Mot de passe actuel', 'required' => true,
                    'error' => $errors['current_password'] ?? '', 'attrs' => ['autocomplete' => 'current-password']]) ?>
                <?= partial('site/field', ['name' => 'code', 'label' => 'Code à 6 chiffres affiché par l\'application', 'required' => true,
                    'class' => 'input--mono input--code', 'error' => $errors['code'] ?? '',
                    'attrs' => ['autocomplete' => 'one-time-code', 'inputmode' => 'numeric', 'pattern' => '[0-9 ]{6,7}', 'maxlength' => '7',
                        'autocapitalize' => 'none', 'spellcheck' => 'false']]) ?>
            <p class="field__hint">Après l'activation, 8 codes de secours vous seront montrés <strong>une seule fois</strong> : préparez de quoi les noter.</p>
            <div class="form__actions">
                <button class="btn btn--primary" type="submit"><?= icon('shield', 16) ?>Activer la double authentification</button>
                <a class="btn btn--secondary" href="/admin/compte">Annuler</a>
            </div>
        </form>
    </li>
</ol>
