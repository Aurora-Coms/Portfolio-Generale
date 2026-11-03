<?php
/**
 * Deuxième étape de connexion : code à 6 chiffres de l'application
 * d'authentification, ou code de secours (formulaire repliable).
 * Gabarit layouts/admin-auth.
 *
 * Variables :
 *   $mode  string  'totp' | 'recovery' : formulaire concerné par l'erreur
 *   $error string  erreur affichée sur le champ du formulaire concerné
 *   $alert string  blocage temporaire (trop de tentatives)
 */

declare(strict_types=1);

$mode  = ($mode ?? 'totp') === 'recovery' ? 'recovery' : 'totp';
$error = (string) ($error ?? '');
$alert = (string) ($alert ?? '');
?>
<p class="auth-card__comment" aria-hidden="true">-- verification.lua</p>
<h1 class="auth-card__title">Double authentification</h1>
<p class="auth-card__lead">Saisissez le code à 6 chiffres affiché par votre application d'authentification.</p>
<?php if ($alert !== ''): ?>
<div class="flashes">
<?= partial('site/alert', ['type' => 'error', 'title' => 'Vérification bloquée', 'message' => $alert]) ?>
</div>
<?php endif; ?>
<form class="form" method="post" action="/admin/connexion/verification"<?= $mode === 'totp' ? ' data-focus-error' : '' ?>>
    <?= csrf_field() ?>
    <input type="hidden" name="mode" value="totp">
    <?= partial('site/field', [
        'name'      => 'code',
        'label'     => 'Code de vérification',
        'required'  => true,
        'class'     => 'input--mono input--code',
        'hint'      => 'Le code change toutes les 30 secondes.',
        'error'     => $mode === 'totp' ? $error : '',
        'attrs'     => [
            'autocomplete'   => 'one-time-code',
            'inputmode'      => 'numeric',
            'autocapitalize' => 'none',
            'spellcheck'     => 'false',
            'maxlength'      => '7',
            'autofocus'      => $mode === 'totp' && $error === '',
        ],
    ]) ?>
    <button class="btn btn--primary btn--block" type="submit" data-busy-label="Vérification…"><?= icon('check', 16) ?>Vérifier</button>
</form>

<details class="disclosure"<?= $mode === 'recovery' ? ' open' : '' ?>>
    <summary>Utiliser un code de secours</summary>
    <div class="disclosure__body">
        <p class="field__hint">Vous n'avez plus accès à votre application ? Saisissez l'un des codes de secours remis lors de l'activation (format <span class="mono">XXXXX-XXXXX</span>). Chaque code ne fonctionne qu'une seule fois.</p>
        <form class="form" method="post" action="/admin/connexion/verification"<?= $mode === 'recovery' ? ' data-focus-error' : '' ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="mode" value="recovery">
            <?= partial('site/field', [
                'name'      => 'code',
                'id'        => 'f-recovery-code',
                'label'     => 'Code de secours',
                'required'  => true,
                'maxlength' => 20,
                'class'     => 'input--mono',
                'error'     => $mode === 'recovery' ? $error : '',
                'attrs'     => ['autocomplete' => 'off', 'autocapitalize' => 'characters', 'spellcheck' => 'false'],
            ]) ?>
            <button class="btn btn--secondary btn--block" type="submit" data-busy-label="Vérification…"><?= icon('lock', 16) ?>Utiliser ce code</button>
        </form>
    </div>
</details>
<p class="auth-card__alt"><a href="/admin/connexion">Recommencer la connexion</a></p>
