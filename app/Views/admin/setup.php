<?php
/**
 * Installation : création du premier compte administrateur.
 * Rendue par public_html/setup-admin.php avec le gabarit layouts/admin-auth.
 *
 * Variables :
 *   $state     string  'form' | 'created' | 'exists' | 'disabled' | 'insecure' | 'database'
 *   $errors    array   erreurs par champ (setup_token, username, password, password_confirmation)
 *   $old       array   valeurs saisies à réafficher (username uniquement : jamais de secret)
 *   $error     string  erreur générale (CSRF, limitation…)
 *   $username  string  (state 'created') identifiant créé
 *   $minLength int     (state 'disabled') longueur minimale du jeton
 */

declare(strict_types=1);

$state     = (string) ($state ?? 'form');
$errors    = isset($errors) && is_array($errors) ? $errors : [];
$old       = isset($old) && is_array($old) ? $old : [];
$error     = (string) ($error ?? '');
$username  = (string) ($username ?? '');
$minLength = (int) ($minLength ?? 24);
?>
<p class="auth-card__comment" aria-hidden="true">-- installation.lua</p>
<?php if ($state === 'created'): ?>
<h1 class="auth-card__title">Compte créé</h1>
<p class="auth-card__lead">Le compte administrateur « <?= e($username) ?> » est prêt.</p>
<div class="stack">
    <?= partial('site/alert', [
        'type'    => 'warning',
        'title'   => 'Dernière étape, à faire maintenant',
        'message' => 'Compte créé. Supprimez maintenant le fichier public_html/setup-admin.php et videz setup_token dans config.php.',
    ]) ?>
    <div class="prose small text-soft">
        <ol>
            <li>Gestionnaire de fichiers ou FTP : supprimez <code>public_html/setup-admin.php</code>.</li>
            <li>Dans <code>app/config.php</code>, remplacez la valeur de <code>setup_token</code> par <code>''</code>.</li>
            <li>Connectez-vous, puis activez la double authentification dans « Mon compte ».</li>
        </ol>
    </div>
    <a class="btn btn--primary btn--block" href="/admin/connexion"><?= icon('lock', 16) ?>Se connecter à l'administration</a>
</div>

<?php elseif ($state === 'exists'): ?>
<h1 class="auth-card__title">Installation terminée</h1>
<p class="auth-card__lead">Ce script ne sert qu'à créer le tout premier compte.</p>
<div class="stack">
    <?= partial('site/alert', [
        'type'    => 'warning',
        'role'    => '',
        'message' => 'Un administrateur existe déjà. Supprimez ce fichier.',
    ]) ?>
    <p class="small text-soft">Fichier à supprimer : <code>public_html/setup-admin.php</code>. Pensez aussi à vider <code>setup_token</code> dans <code>app/config.php</code>.</p>
    <a class="btn btn--primary btn--block" href="/admin/connexion"><?= icon('lock', 16) ?>Aller à la connexion</a>
</div>

<?php elseif ($state === 'disabled'): ?>
<h1 class="auth-card__title">Installation désactivée</h1>
<p class="auth-card__lead">La création du premier compte est verrouillée par un jeton d'installation.</p>
<div class="stack">
    <?= partial('site/alert', [
        'type'    => 'info',
        'role'    => '',
        'message' => 'Aucun jeton d\'installation valide n\'est configuré.',
    ]) ?>
    <div class="prose small text-soft">
        <p>Pour créer le compte administrateur :</p>
        <ol>
            <li>Dans <code>app/config.php</code>, donnez à <code>setup_token</code> une chaîne aléatoire d'au moins <?= $minLength ?> caractères (32 ou plus conseillés). En SSH : <code>php -r "echo bin2hex(random_bytes(24));"</code></li>
            <li>Rechargez cette page et suivez le formulaire.</li>
        </ol>
        <p>Si le compte existe déjà, supprimez simplement ce fichier.</p>
    </div>
</div>

<?php elseif ($state === 'insecure'): ?>
<h1 class="auth-card__title">Connexion non chiffrée</h1>
<p class="auth-card__lead">Le mot de passe ne doit jamais circuler en clair.</p>
<div class="stack">
    <?= partial('site/alert', [
        'type'    => 'error',
        'role'    => '',
        'message' => 'Cette page n\'est disponible qu\'en HTTPS.',
    ]) ?>
    <p class="small text-soft">Activez d'abord le certificat SSL du domaine (hPanel, rubrique Sécurité puis SSL), puis ouvrez l'adresse en <code>https://</code>.</p>
</div>

<?php elseif ($state === 'database'): ?>
<h1 class="auth-card__title">Base de données inaccessible</h1>
<p class="auth-card__lead">Le compte ne peut pas être créé pour l'instant.</p>
<div class="stack">
    <?= partial('site/alert', [
        'type'    => 'error',
        'role'    => '',
        'message' => 'La connexion à la base de données a échoué.',
    ]) ?>
    <div class="prose small text-soft">
        <p>Vérifiez :</p>
        <ul>
            <li>la section <code>db</code> de <code>app/config.php</code> (nom de la base, utilisateur, mot de passe) ;</li>
            <li>que <code>database.sql</code> a bien été importé dans cette base avec phpMyAdmin.</li>
        </ul>
        <p>Le détail de l'erreur est dans <code>app/storage/logs/php-errors.log</code>.</p>
    </div>
</div>

<?php else: ?>
<h1 class="auth-card__title">Créer le compte administrateur</h1>
<p class="auth-card__lead">Première installation du site. Ce formulaire ne fonctionne qu'une seule fois.</p>
<?php if ($error !== ''): ?>
<div class="flashes">
    <?= partial('site/alert', ['type' => 'error', 'message' => $error]) ?>
</div>
<?php elseif ($errors !== []): ?>
<div class="flashes">
    <?= partial('site/alert', ['type' => 'error', 'title' => 'Le compte n\'a pas été créé', 'message' => 'Corrigez les champs signalés ci-dessous.']) ?>
</div>
<?php endif; ?>
<form class="form" method="post" action="/setup-admin.php" novalidate data-focus-error>
    <?= csrf_field() ?>
    <?= partial('site/field', [
        'name'     => 'setup_token',
        'type'     => 'password',
        'label'    => 'Jeton d\'installation',
        'hint'     => 'La valeur de setup_token dans app/config.php.',
        'required' => true,
        'error'    => $errors['setup_token'] ?? '',
        'class'    => 'input--mono',
        'attrs'    => ['autocomplete' => 'off', 'autocapitalize' => 'none', 'spellcheck' => 'false'],
    ]) ?>
    <?= partial('site/field', [
        'name'      => 'username',
        'label'     => 'Identifiant',
        'hint'      => '3 à 50 caractères : lettres sans accent, chiffres, point, tiret ou tiret bas.',
        'required'  => true,
        'maxlength' => 50,
        'value'     => old($old, 'username'),
        'error'     => $errors['username'] ?? '',
        'attrs'     => ['autocomplete' => 'username', 'autocapitalize' => 'none', 'spellcheck' => 'false', 'pattern' => '[a-zA-Z0-9._\-]{3,50}'],
    ]) ?>
    <?= partial('site/field', [
        'name'     => 'password',
        'type'     => 'password',
        'label'    => 'Mot de passe',
        'hint'     => 'Au moins 12 caractères, sans l\'identifiant. Une phrase de passe de plusieurs mots est idéale.',
        'required' => true,
        'error'    => $errors['password'] ?? '',
        'attrs'    => ['autocomplete' => 'new-password'],
    ]) ?>
    <?= partial('site/field', [
        'name'     => 'password_confirmation',
        'type'     => 'password',
        'label'    => 'Confirmation du mot de passe',
        'required' => true,
        'error'    => $errors['password_confirmation'] ?? '',
        'attrs'    => ['autocomplete' => 'new-password'],
    ]) ?>
    <button class="btn btn--primary btn--block" type="submit"><?= icon('user', 16) ?>Créer le compte</button>
    <p class="form__note">Ensuite : supprimez ce fichier et videz setup_token dans app/config.php.</p>
</form>
<?php endif; ?>
