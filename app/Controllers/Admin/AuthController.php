<?php
/**
 * Connexion à l'administration : identifiant + mot de passe, puis code de
 * double authentification (TOTP ou code de secours) si elle est activée.
 *
 * Ce contrôleur n'hérite PAS d'AdminController (les pages sont accessibles
 * sans session) : il envoie donc lui-même les en-têtes « pas de cache » et
 * « pas d'indexation », et utilise le gabarit layouts/admin-auth.
 *
 * Sécurité (le détail est dans App\Core\Auth) :
 *  - message d'erreur unique, que l'identifiant existe ou non ;
 *  - blocage après 5 échecs en 15 minutes (429) ;
 *  - nouvel identifiant de session et nouveau jeton CSRF à la connexion ;
 *  - le mot de passe n'est jamais réaffiché ni lu dans l'URL ;
 *  - déconnexion uniquement en POST (jeton CSRF vérifié par le routeur).
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Session;
use App\Core\View;

final class AuthController
{
    private const MSG_INVALID = 'Identifiant ou mot de passe incorrect.';
    private const MSG_LOCKED  = 'Trop de tentatives. Réessayez dans 15 minutes.';
    private const MSG_CODE    = 'Code incorrect ou déjà utilisé.';
    private const MSG_RECOVERY = 'Code de secours incorrect ou déjà utilisé.';

    /**
     * Durée minimale d'une réponse « identifiants incorrects » (secondes).
     * Auth calcule un hachage factice quand le compte n'existe pas, mais ce calcul
     * s'ajoute à la vérification : sans ce plancher, un identifiant inexistant
     * répond sensiblement plus lentement et trahit l'existence du compte.
     * Le plancher rend les deux cas indiscernables (et ralentit les essais).
     */
    private const FAIL_MIN_SECONDS = 1.0;

    public function __construct()
    {
        header('Cache-Control: no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
    }

    /** GET /admin/connexion */
    public function loginForm(): void
    {
        if (Auth::check()) {
            redirect('/admin');
        }
        $this->renderLogin();
    }

    /** POST /admin/connexion */
    public function login(): void
    {
        $username = self::post('username');
        $password = self::post('password');

        $start  = hrtime(true);
        $result = Auth::attempt($username, $password);
        // Le mot de passe n'est plus utile : on ne le garde pas en mémoire plus longtemps.
        unset($password);
        if ($result === 'invalid') {
            self::waitUntil($start, self::FAIL_MIN_SECONDS);
        }

        match ($result) {
            'ok'     => redirect('/admin'),
            '2fa'    => redirect('/admin/connexion/verification'),
            'locked' => $this->renderLogin(['username' => $username], '', self::MSG_LOCKED, 429),
            default  => $this->renderLogin(['username' => $username], self::MSG_INVALID, '', 401),
        };
    }

    /** GET /admin/connexion/verification */
    public function twoFactorForm(): void
    {
        if (Auth::check()) {
            redirect('/admin');
        }
        if (Auth::pendingSecondFactor() === null) {
            redirect('/admin/connexion');
        }
        $this->renderTwoFactor();
    }

    /** POST /admin/connexion/verification */
    public function twoFactor(): void
    {
        $mode = self::post('mode') === 'recovery' ? 'recovery' : 'totp';
        $code = self::post('code');
        if ($mode === 'totp') {
            // « 123 456 » (copié depuis l'application) est accepté.
            $code = (string) preg_replace('/\s+/u', '', $code);
        }
        // Un code n'a jamais besoin de plus de quelques dizaines de caractères.
        $code = substr($code, 0, 64);

        $result = Auth::attemptSecondFactor($code);

        if ($result === 'ok') {
            redirect('/admin');
        }
        if ($result === 'expired') {
            Session::flash('warning', 'Le délai de saisie du code est dépassé. Reconnectez-vous.');
            redirect('/admin/connexion');
        }
        if ($result === 'locked') {
            $this->renderTwoFactor($mode, '', self::MSG_LOCKED, 429);
            return;
        }
        $this->renderTwoFactor($mode, $mode === 'recovery' ? self::MSG_RECOVERY : self::MSG_CODE, '', 401);
    }

    /** POST /admin/deconnexion (jamais en GET : le routeur répond 405) */
    public function logout(): void
    {
        Auth::logout();
        // Nouvelle session anonyme (nouvel identifiant) pour le message de confirmation.
        Session::regenerate();
        Session::flash('success', 'Vous êtes déconnecté.');
        redirect('/admin/connexion');
    }

    // --- Interne -------------------------------------------------------------------

    /**
     * @param array<string, string> $old
     */
    private function renderLogin(array $old = [], string $error = '', string $alert = '', int $status = 200): void
    {
        View::render('admin/auth/login', [
            'old'   => $old,
            'error' => $error,
            'alert' => $alert,
            'meta'  => ['title' => 'Connexion', 'robots' => 'noindex'],
        ], 'layouts/admin-auth', $status);
    }

    private function renderTwoFactor(string $mode = 'totp', string $error = '', string $alert = '', int $status = 200): void
    {
        View::render('admin/auth/two-factor', [
            'mode'  => $mode,
            'error' => $error,
            'alert' => $alert,
            'meta'  => ['title' => 'Double authentification', 'robots' => 'noindex'],
        ], 'layouts/admin-auth', $status);
    }

    /** Attend que $seconds se soient écoulées depuis $start (hrtime en nanosecondes). */
    private static function waitUntil(int $start, float $seconds): void
    {
        $remaining = $seconds - (hrtime(true) - $start) / 1e9;
        if ($remaining > 0) {
            usleep((int) round($remaining * 1e6));
        }
    }

    /** Champ du corps POST uniquement (jamais de l'URL), toujours une chaîne. */
    private static function post(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? $value : '';
    }
}
