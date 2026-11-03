<?php
/**
 * Formulaire de connexion (gabarit layouts/admin-auth).
 *
 * Variables :
 *   $old   array   ['username' => …] : le mot de passe n'est JAMAIS réaffiché
 *   $error string  erreur d'identification (affichée sur le champ mot de passe)
 *   $alert string  blocage temporaire (trop de tentatives)
 */

declare(strict_types=1);

$old   = isset($old) && is_array($old) ? $old : [];
$error = (string) ($error ?? '');
$alert = (string) ($alert ?? '');
?>
<p class="auth-card__comment" aria-hidden="true">-- connexion.lua</p>
<h1 class="auth-card__title">Connexion</h1>
<p class="auth-card__lead">Espace réservé à l'administrateur du site.</p>
<?php if ($alert !== ''): ?>
<div class="flashes">
<?= partial('site/alert', ['type' => 'error', 'title' => 'Connexion bloquée', 'message' => $alert]) ?>
</div>
<?php endif; ?>
<form class="form" method="post" action="/admin/connexion" data-focus-error>
    <?= csrf_field() ?>
    <?= partial('site/field', [
        'name'      => 'username',
        'label'     => 'Identifiant',
        'required'  => true,
        'maxlength' => 50,
        'value'     => old($old, 'username'),
        'attrs'     => ['autocomplete' => 'username', 'autocapitalize' => 'none', 'spellcheck' => 'false'],
    ]) ?>
    <?= partial('site/field', [
        'name'     => 'password',
        'type'     => 'password',
        'label'    => 'Mot de passe',
        'required' => true,
        'error'    => $error,
        'attrs'    => ['autocomplete' => 'current-password'],
    ]) ?>
    <button class="btn btn--primary btn--block" type="submit" data-busy-label="Connexion…"><?= icon('lock', 16) ?>Se connecter</button>
</form>
