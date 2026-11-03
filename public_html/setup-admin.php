<?php
/**
 * Création du PREMIER compte administrateur : script À USAGE UNIQUE.
 *
 * Mode d'emploi (voir README.md, étape « Créer le compte administrateur ») :
 *   1. Dans app/config.php, mettez une longue chaîne aléatoire dans
 *      security.setup_token (au moins 24 caractères, 32 conseillés).
 *   2. Ouvrez https://votre-domaine/setup-admin.php, saisissez ce jeton,
 *      l'identifiant et le mot de passe choisis.
 *   3. SUPPRIMEZ ensuite ce fichier et videz setup_token ('').
 *
 * Sécurité :
 *  - inactif tant que setup_token est vide ou trop court (403) ;
 *  - inutilisable dès qu'un administrateur existe (410) ;
 *  - HTTPS exigé en production (le mot de passe ne circule jamais en clair) ;
 *  - jeton CSRF vérifié ici (ce script ne passe pas par le routeur) ;
 *  - jeton d'installation comparé en temps constant (hash_equals) ;
 *  - 5 jetons erronés au plus par IP et par quart d'heure (Throttle) ;
 *  - mêmes règles de mot de passe que le reste du site (Admin::passwordProblem) ;
 *  - page jamais mise en cache ni indexée ; aucun style ni script en ligne (CSP).
 */

declare(strict_types=1);

define('PUBLIC_PATH', __DIR__);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\Throttle;
use App\Core\View;
use App\Models\Admin;

header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
header_remove('X-Powered-By');

/** Limitation des jetons erronés : 5 par IP sur 15 minutes. */
const SETUP_MAX_FAILS = 5;
const SETUP_WINDOW    = 900;

/** Longueur minimale du jeton d'installation (en octets). */
const SETUP_TOKEN_MIN = 24;

/**
 * Affiche la page d'installation (vue admin/setup, gabarit admin-auth) et termine.
 *
 * @param array<string, mixed> $data
 */
function setup_render(string $state, int $status, array $data = []): never
{
    View::render('admin/setup', $data + [
        'state'  => $state,
        'errors' => [],
        'old'    => [],
        'error'  => '',
        'meta'   => ['title' => 'Installation', 'robots' => 'noindex'],
    ], 'layouts/admin-auth', $status);
    exit;
}

$method = Request::method();
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, POST');
    throw new HttpException(405);
}

// 1. Installation désactivée tant qu'aucun jeton suffisamment long n'est configuré.
$setupToken = trim((string) Config::get('security.setup_token', ''));
if (strlen($setupToken) < SETUP_TOKEN_MIN) {
    setup_render('disabled', 403, ['minLength' => SETUP_TOKEN_MIN]);
}

// 2. En production, le formulaire n'est servi qu'en HTTPS.
if (!Config::isDev() && !Request::isHttps()) {
    setup_render('insecure', 403);
}

// 3. Un administrateur existe déjà : le script n'a plus aucune utilité.
try {
    $adminCount = Admin::count();
} catch (Throwable $e) {
    // Base inaccessible ou tables absentes : on journalise sans rien divulguer.
    error_log('[setup] Base de données inaccessible : ' . $e::class . ': ' . $e->getMessage());
    setup_render('database', 503);
}

if ($adminCount > 0) {
    // Juste après la création (redirection PRG), on affiche la confirmation une fois.
    $created = Session::hasCookie() ? Session::get('_setup_created') : null;
    if (is_string($created) && $created !== '') {
        Session::forget('_setup_created');
        setup_render('created', 200, ['username' => $created]);
    }
    setup_render('exists', 410);
}

// 4. Formulaire.
if ($method !== 'POST') {
    setup_render('form', 200);
}

// 4.1 Jeton CSRF + origine (le routeur n'est pas utilisé ici : vérification manuelle).
try {
    Csrf::verifyRequest();
} catch (HttpException $e) {
    setup_render('form', $e->status, [
        'error' => $e->getMessage() !== '' ? $e->getMessage() : 'Requête refusée : rechargez la page puis réessayez.',
    ]);
}

$ip           = Request::ip();
$username     = trim(Request::input('username'));
$password     = Request::input('password');
$confirmation = Request::input('password_confirmation');
$old          = ['username' => $username];

// 4.2 Trop de jetons erronés depuis cette adresse : on ne compare même plus.
if (Throttle::tooMany('setup', $ip, SETUP_MAX_FAILS, SETUP_WINDOW)) {
    setup_render('form', 429, [
        'old'   => $old,
        'error' => 'Trop de tentatives avec un jeton erroné. Patientez 15 minutes avant de réessayer.',
    ]);
}

// 4.3 Jeton d'installation (comparaison en temps constant).
$sentToken = trim(Request::input('setup_token'));
if ($sentToken === '' || !hash_equals($setupToken, $sentToken)) {
    Throttle::hit('setup', $ip);
    error_log('[setup] Jeton d\'installation invalide.');
    setup_render('form', 422, [
        'old'    => $old,
        'errors' => ['setup_token' => 'Jeton d\'installation incorrect.'],
    ]);
}

// 4.4 Identifiant et mot de passe.
$errors = [];
if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
    $errors['username'] = 'L\'identifiant doit faire 3 à 50 caractères : lettres sans accent, chiffres, point, tiret ou tiret bas.';
}
$problem = Admin::passwordProblem($password, $errors === [] ? $username : '');
if ($problem !== null) {
    $errors['password'] = $problem;
} elseif (!hash_equals($password, $confirmation)) {
    $errors['password_confirmation'] = 'Les deux mots de passe ne sont pas identiques.';
}
if ($errors !== []) {
    setup_render('form', 422, ['old' => $old, 'errors' => $errors]);
}

// 4.5 Création. On revérifie juste avant : deux envois simultanés ne créent
//     pas deux comptes (l'identifiant est de plus unique en base).
try {
    if (Admin::count() > 0) {
        setup_render('exists', 410);
    }
    Admin::create($username, $password);
} catch (PDOException $e) {
    error_log('[setup] Échec de la création du compte : ' . $e->getMessage());
    setup_render('form', 500, [
        'old'   => $old,
        'error' => 'Le compte n\'a pas pu être créé. Consultez app/storage/logs/php-errors.log.',
    ]);
}

error_log('[setup] Premier compte administrateur créé. Supprimez public_html/setup-admin.php.');
Throttle::clear('setup', $ip);

// Pattern PRG : nouvel identifiant de session, nouveau jeton CSRF, puis redirection
// (un rechargement de la page ne renvoie pas le formulaire).
Session::regenerate();
Csrf::rotate();
Session::set('_setup_created', $username);
redirect('/setup-admin.php');
