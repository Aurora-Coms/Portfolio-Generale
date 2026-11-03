<?php
/**
 * Page d'erreur (404, 403, 419, 429, 500…), rendue par App\Core\ErrorHandler.
 * Gabarit : layouts/site pour le site public, layouts/admin-auth sous /admin.
 *
 * Variables :
 *   $status  int          code HTTP
 *   $message string       message sûr d'une HttpException (peut être vide)
 *   $detail  string|null  détail technique : UNIQUEMENT en développement (sinon null)
 *   $meta    array        ['title' => ErrorHandler::title($status), 'robots' => 'noindex']
 */

declare(strict_types=1);

use App\Core\Request;

$status  = (int) ($status ?? 500);
$message = trim((string) ($message ?? ''));
$detail  = isset($detail) && is_string($detail) && $detail !== '' ? $detail : null;
$title   = (string) (($meta['title'] ?? '') ?: 'Erreur');
$path    = Request::path();
$isAdmin = str_starts_with($path, '/admin');

// Texte d'explication par défaut, selon le code.
$defaultText = match ($status) {
    400     => 'La requête envoyée est invalide.',
    403     => 'Vous n\'avez pas l\'autorisation d\'accéder à cette page.',
    404     => 'Cette page n\'existe pas ou a été déplacée.',
    405     => 'Cette action n\'est pas possible à cette adresse.',
    413     => 'Les fichiers envoyés sont trop volumineux.',
    419     => 'Votre session a expiré. Rechargez la page puis recommencez.',
    429     => 'Trop de tentatives en peu de temps. Patientez quelques minutes puis réessayez.',
    503     => 'Le site est momentanément indisponible. Réessayez dans quelques instants.',
    default => 'Une erreur inattendue s\'est produite. Elle a été enregistrée et sera corrigée.',
};
$text = $message !== '' ? $message : $defaultText;

// Chemin affiché dans la fenêtre de code (tronqué, toujours échappé).
$shownPath = str_limit($path, 60);
?>
<?php if ($isAdmin): ?>
<div class="auth-error">
    <p class="auth-error__code" aria-hidden="true">-- erreur <?= $status ?></p>
    <h1 class="auth-card__title"><?= e($title) ?></h1>
    <p class="auth-card__lead"><?= e($text) ?></p>
    <div class="btn-row">
        <a class="btn btn--primary" href="/admin"><?= icon('home', 18) ?>Tableau de bord</a>
        <a class="btn btn--secondary" href="/"><?= icon('arrow-left', 18) ?>Voir le site</a>
    </div>
<?php if ($detail !== null): ?>
    <pre class="error-detail"><?= e($detail) ?></pre>
<?php endif; ?>
</div>
<?php else: ?>
<section class="error-page" aria-labelledby="erreur-titre">
    <p class="error-page__code" aria-hidden="true">-- erreur.lua</p>
    <p class="error-page__status" aria-hidden="true"><?= $status ?></p>
    <h1 class="error-page__title" id="erreur-titre"><?= e($title) ?><span class="sr-only"> (erreur <?= $status ?>)</span></h1>
    <p class="error-page__text"><?= e($text) ?></p>

<?= partial('site/code-window', [
    'filename' => 'erreur.lua',
    'label'    => 'Illustration : erreur ' . $status,
    'lines'    => $status === 404
        ? [
            '<span class="tok-kw">local</span> page = require(<span class="tok-str">"' . e($shownPath) . '"</span>)',
            '<span class="tok-kw">if</span> page == <span class="tok-kw">nil</span> <span class="tok-kw">then</span>',
            '    error(<span class="tok-str">"page introuvable"</span>, <span class="tok-num">404</span>)',
            '<span class="tok-kw">end</span>',
        ]
        : [
            '<span class="tok-com">-- statut HTTP</span>',
            '<span class="tok-kw">local</span> statut = <span class="tok-num">' . $status . '</span>',
            '<span class="tok-kw">return</span> <span class="tok-str">"' . e($title) . '"</span>',
        ],
]) ?>

    <div class="btn-row error-page__actions">
        <a class="btn btn--primary" href="/"><?= icon('home', 18) ?>Retour à l'accueil</a>
        <a class="btn btn--secondary" href="/projets/jeux">Voir les projets</a>
        <a class="btn btn--secondary" href="/contact"><?= icon('mail', 18) ?>Me contacter</a>
    </div>

<?php if ($detail !== null): ?>
    <pre class="error-page__detail" tabindex="0" aria-label="Détail technique (affiché en développement uniquement)"><?= e($detail) ?></pre>
<?php endif; ?>
</section>
<?php endif; ?>
