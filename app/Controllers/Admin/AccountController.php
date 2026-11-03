<?php
/**
 * Compte administrateur : mot de passe et double authentification (TOTP).
 *
 *   GET  /admin/compte                index
 *   POST /admin/compte/mot-de-passe   updatePassword
 *   GET  /admin/compte/2fa            setupTwoFactor   (QR code + formulaire d'activation)
 *   POST /admin/compte/2fa            enableTwoFactor  (affiche les codes de secours UNE fois)
 *   POST /admin/compte/2fa/desactiver disableTwoFactor
 *   POST /admin/compte/2fa/codes      regenerateCodes  (affiche les nouveaux codes UNE fois)
 *
 * Sécurité :
 *  - toute action sensible redemande le mot de passe actuel (password_verify) ;
 *  - ces vérifications sont limitées (5 échecs / 15 min par compte) contre
 *    l'essai de mots de passe depuis une session laissée ouverte ;
 *  - le secret TOTP en cours de configuration reste en SESSION, il n'est
 *    enregistré (chiffré AES-256-GCM) qu'après un code valide ;
 *  - les codes de secours ne sont affichés qu'une fois, dans la réponse même
 *    (pas de redirection, aucune copie en session) : la page n'est jamais
 *    mise en cache (Cache-Control: no-store envoyé par AdminController).
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminController;
use App\Core\Auth;
use App\Core\Crypto;
use App\Core\QrCode;
use App\Core\Session;
use App\Core\Throttle;
use App\Core\Totp;
use App\Models\Admin;

final class AccountController extends AdminController
{
    /** Nom de l'émetteur affiché dans l'application d'authentification. */
    public const ISSUER = 'Portfolio Alexandre Derbez';

    /** Clé de session du secret en cours de configuration. */
    private const SETUP_KEY = '_2fa_setup';

    /** Limitation des vérifications de mot de passe / code depuis une session ouverte. */
    private const REAUTH_ACTION = 'reauth';
    private const REAUTH_MAX    = 5;
    private const REAUTH_WINDOW = 900; // 15 minutes

    public function index(): void
    {
        $this->renderIndex();
    }

    public function updatePassword(): void
    {
        $admin   = $this->admin();
        $current = self::post('current_password');
        $new     = self::post('new_password');
        $confirm = self::post('new_password_confirmation');
        $errors  = [];

        $check = $this->checkPassword($admin, $current);
        if ($check !== null) {
            $errors['current_password'] = $check;
        } elseif ($new === '') {
            $errors['new_password'] = 'Choisissez un nouveau mot de passe.';
        } elseif (hash_equals($current, $new)) {
            $errors['new_password'] = 'Le nouveau mot de passe doit être différent de l\'actuel.';
        } elseif (($problem = Admin::passwordProblem($new, (string) $admin['username'])) !== null) {
            $errors['new_password'] = $problem;
        } elseif (!hash_equals($new, $confirm)) {
            $errors['new_password_confirmation'] = 'Les deux mots de passe ne sont pas identiques.';
        }

        if ($errors !== []) {
            $this->renderIndex($errors, 422);
            return;
        }

        Throttle::clear(self::REAUTH_ACTION, (string) $admin['id']);
        Admin::updatePassword((int) $admin['id'], $new);
        // Changement de privilège : les AUTRES sessions du compte sont fermées,
        // celle-ci reçoit un nouvel identifiant et un nouveau jeton CSRF.
        Auth::revokeOtherSessions();
        error_log('[auth] Mot de passe administrateur modifié.');
        $this->success('/admin/compte', 'Mot de passe modifié. Utilisez-le dès votre prochaine connexion.');
    }

    public function setupTwoFactor(): void
    {
        $admin = $this->admin();
        if ((int) $admin['totp_enabled'] === 1) {
            Session::flash('info', 'La double authentification est déjà activée.');
            redirect('/admin/compte');
        }
        $this->renderSetup($admin, $this->setupSecret());
    }

    public function enableTwoFactor(): void
    {
        $admin = $this->admin();
        if ((int) $admin['totp_enabled'] === 1) {
            Session::flash('info', 'La double authentification est déjà activée.');
            redirect('/admin/compte');
        }
        $secret = Session::get(self::SETUP_KEY);
        if (!is_string($secret) || $secret === '') {
            Session::flash('error', 'La configuration a expiré : scannez le nouveau QR code puis recommencez.');
            redirect('/admin/compte/2fa');
        }

        $errors = [];
        $check  = $this->checkPassword($admin, self::post('current_password'));
        $step   = null;
        if ($check !== null) {
            $errors['current_password'] = $check;
        } else {
            $step = Totp::verify($secret, self::normalizeCode(self::post('code')), null);
            if ($step === null) {
                $this->failedAttempt($admin);
                $errors['code'] = 'Code incorrect. Vérifiez l\'heure du téléphone et saisissez le code affiché en ce moment (6 chiffres).';
            }
        }

        if ($errors !== [] || $step === null) {
            $this->renderSetup($admin, $secret, $errors, 422);
            return;
        }

        $id = (int) $admin['id'];
        Throttle::clear(self::REAUTH_ACTION, (string) $id);
        Admin::enableTotp($id, Crypto::encrypt($secret, 'totp'), $step);
        Session::forget(self::SETUP_KEY);
        $codes = Admin::regenerateRecoveryCodes($id);
        // Les sessions ouvertes sans double authentification sont fermées.
        Auth::revokeOtherSessions();
        error_log('[auth] Double authentification activée.');

        $this->renderCodes($codes, true);
    }

    public function disableTwoFactor(): void
    {
        $admin = $this->admin();
        if ((int) $admin['totp_enabled'] !== 1) {
            Session::flash('info', 'La double authentification n\'est pas activée.');
            redirect('/admin/compte');
        }

        $errors = [];
        $check  = $this->checkPassword($admin, self::post('disable_password'));
        if ($check !== null) {
            $errors['disable_password'] = $check;
        } else {
            $secret = Crypto::decrypt((string) $admin['totp_secret_enc'], 'totp');
            $last   = $admin['totp_last_step'] !== null ? (int) $admin['totp_last_step'] : null;
            $step   = $secret !== null ? Totp::verify($secret, self::normalizeCode(self::post('disable_code')), $last) : null;
            if ($step === null) {
                $this->failedAttempt($admin);
                $errors['disable_code'] = 'Code incorrect ou déjà utilisé. Saisissez le code actuel de votre application (6 chiffres).';
            }
        }

        if ($errors !== []) {
            $this->renderIndex($errors, 422);
            return;
        }

        $id = (int) $admin['id'];
        Throttle::clear(self::REAUTH_ACTION, (string) $id);
        Admin::disableTotp($id);
        Auth::revokeOtherSessions();
        error_log('[auth] Double authentification désactivée.');
        $this->success('/admin/compte', 'Double authentification désactivée. Vos codes de secours ont été supprimés.');
    }

    public function regenerateCodes(): void
    {
        $admin = $this->admin();
        if ((int) $admin['totp_enabled'] !== 1) {
            Session::flash('info', 'Activez d\'abord la double authentification.');
            redirect('/admin/compte');
        }

        $check = $this->checkPassword($admin, self::post('codes_password'));
        if ($check !== null) {
            $this->renderIndex(['codes_password' => $check], 422);
            return;
        }

        Throttle::clear(self::REAUTH_ACTION, (string) $admin['id']);
        $codes = Admin::regenerateRecoveryCodes((int) $admin['id']);
        error_log('[auth] Codes de secours régénérés.');
        $this->renderCodes($codes, false);
    }

    // -------------------------------------------------------------------------

    /** @return array<string, mixed> administrateur connecté (le constructeur garantit la connexion) */
    private function admin(): array
    {
        $admin = Auth::user();
        if ($admin === null) {
            redirect('/admin/connexion');
        }
        return $admin;
    }

    /**
     * Vérifie le mot de passe actuel, avec limitation des essais.
     * Renvoie un message d'erreur, ou null si le mot de passe est correct.
     *
     * @param array<string, mixed> $admin
     */
    private function checkPassword(array $admin, string $password): ?string
    {
        $key = (string) $admin['id'];
        if (Throttle::tooMany(self::REAUTH_ACTION, $key, self::REAUTH_MAX, self::REAUTH_WINDOW)) {
            return 'Trop de tentatives incorrectes. Réessayez dans 15 minutes.';
        }
        if ($password === '' || !password_verify($password, (string) $admin['password_hash'])) {
            $this->failedAttempt($admin);
            return 'Mot de passe actuel incorrect.';
        }
        return null;
    }

    /** @param array<string, mixed> $admin */
    private function failedAttempt(array $admin): void
    {
        Throttle::hit(self::REAUTH_ACTION, (string) $admin['id']);
        error_log('[auth] Vérification échouée sur la page du compte.');
    }

    /** Secret en cours de configuration : créé une seule fois, gardé en session. */
    private function setupSecret(): string
    {
        $secret = Session::get(self::SETUP_KEY);
        if (!is_string($secret) || Totp::base32Decode($secret) === null) {
            $secret = Totp::generateSecret();
            Session::set(self::SETUP_KEY, $secret);
        }
        return $secret;
    }

    /** Champ envoyé en POST uniquement (jamais lu dans l'URL), toujours une chaîne. */
    private static function post(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? $value : '';
    }

    /** Code saisi : espaces retirés (« 123 456 » est accepté). */
    private static function normalizeCode(string $code): string
    {
        return (string) preg_replace('/\s+/', '', $code);
    }

    /** @param array<string, string> $errors */
    private function renderIndex(array $errors = [], int $status = 200): void
    {
        $admin = $this->admin();
        $enabled = (int) $admin['totp_enabled'] === 1;
        $this->render('admin/account/index', [
            'admin'     => $admin,
            'enabled'   => $enabled,
            'remaining' => $enabled ? Admin::remainingRecoveryCodes((int) $admin['id']) : 0,
            'errors'    => $errors,
            'meta'      => ['title' => 'Mon compte'],
        ], $status);
    }

    /**
     * @param array<string, mixed>  $admin
     * @param array<string, string> $errors
     */
    private function renderSetup(array $admin, string $secret, array $errors = [], int $status = 200): void
    {
        $uri = Totp::provisioningUri($secret, (string) $admin['username'], self::ISSUER);
        $this->render('admin/account/two-factor', [
            'qr'       => QrCode::svg($uri, 220),
            'uri'      => $uri,
            'secret'   => implode(' ', str_split($secret, 4)),
            'issuer'   => self::ISSUER,
            'username' => (string) $admin['username'],
            'errors'   => $errors,
            'meta'     => ['title' => 'Activer la double authentification'],
        ], $status);
    }

    /** @param list<string> $codes */
    private function renderCodes(array $codes, bool $justEnabled): void
    {
        $this->render('admin/account/recovery-codes', [
            'codes'       => $codes,
            'justEnabled' => $justEnabled,
            'meta'        => ['title' => 'Codes de secours'],
        ]);
    }
}
