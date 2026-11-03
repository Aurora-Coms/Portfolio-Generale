<?php
/**
 * Authentification de l'administrateur.
 *
 * Mesures de sécurité :
 *  - mots de passe vérifiés avec password_verify (Argon2id / bcrypt) ;
 *  - temps de réponse identique que l'identifiant existe ou non (hachage factice) ;
 *  - message d'erreur générique (on ne révèle pas si l'identifiant existe) ;
 *  - limitation : 5 échecs / 15 min par IP, 10 échecs / heure par identifiant ;
 *  - nouvel identifiant de session et nouveau jeton CSRF à la connexion ;
 *  - expiration après inactivité et durée de vie maximale de la session ;
 *  - double authentification TOTP optionnelle (+ codes de secours).
 */

declare(strict_types=1);

namespace App\Core;

use App\Models\Admin;

final class Auth
{
    public const IP_MAX_FAILS   = 5;
    public const IP_WINDOW      = 900;   // 15 minutes
    public const USER_MAX_FAILS = 10;
    public const USER_WINDOW    = 3600;  // 1 heure
    public const TOTP_MAX_FAILS = 5;
    public const PENDING_TTL    = 300;   // 5 minutes pour saisir le code 2FA

    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    /**
     * Première étape de connexion.
     *
     * @return 'ok'|'2fa'|'invalid'|'locked'
     */
    public static function attempt(string $username, string $password): string
    {
        $ip       = Request::ip();
        $username = trim($username);
        $userKey  = mb_strtolower($username);

        if (Throttle::tooMany('login_ip', $ip, self::IP_MAX_FAILS, self::IP_WINDOW)
            || Throttle::tooMany('login_user', $userKey, self::USER_MAX_FAILS, self::USER_WINDOW)) {
            return 'locked';
        }

        $admin = ($username !== '' && strlen($username) <= 50) ? Admin::findByUsername($username) : null;
        // Hachage factice si le compte n'existe pas : même durée de calcul.
        $hash  = $admin !== null ? (string) $admin['password_hash'] : self::dummyHash();
        $valid = password_verify($password, $hash) && $admin !== null;

        if (!$valid) {
            Throttle::hit('login_ip', $ip);
            Throttle::hit('login_user', $userKey);
            error_log('[auth] Échec de connexion administrateur.');
            return 'invalid';
        }

        Throttle::clear('login_ip', $ip);

        if (Admin::needsRehash($hash)) {
            Admin::updatePassword((int) $admin['id'], $password);
        }

        if ((int) $admin['totp_enabled'] === 1) {
            Session::regenerate();
            Session::set('_2fa_pending', ['id' => (int) $admin['id'], 'at' => time()]);
            return '2fa';
        }

        self::completeLogin((int) $admin['id']);
        return 'ok';
    }

    /** Une connexion attend-elle le code de double authentification ? */
    public static function pendingSecondFactor(): ?int
    {
        $pending = Session::get('_2fa_pending');
        if (!is_array($pending) || !isset($pending['id'], $pending['at'])) {
            return null;
        }
        if (time() - (int) $pending['at'] > self::PENDING_TTL) {
            Session::forget('_2fa_pending');
            return null;
        }
        return (int) $pending['id'];
    }

    /**
     * Deuxième étape : code TOTP à 6 chiffres OU code de secours.
     *
     * @return 'ok'|'invalid'|'locked'|'expired'
     */
    public static function attemptSecondFactor(string $code): string
    {
        $id = self::pendingSecondFactor();
        if ($id === null) {
            return 'expired';
        }
        if (Throttle::tooMany('totp', (string) $id, self::TOTP_MAX_FAILS, self::IP_WINDOW)) {
            return 'locked';
        }

        $admin = Admin::find($id);
        if ($admin === null || (int) $admin['totp_enabled'] !== 1) {
            return 'expired';
        }

        $code = trim($code);
        $ok   = false;

        if (preg_match('/^\d{6}$/', $code)) {
            $secret = Crypto::decrypt((string) $admin['totp_secret_enc'], 'totp');
            if ($secret !== null) {
                $last = $admin['totp_last_step'] !== null ? (int) $admin['totp_last_step'] : null;
                $step = Totp::verify($secret, $code, $last);
                if ($step !== null) {
                    Admin::setTotpLastStep($id, $step); // anti-rejeu
                    $ok = true;
                }
            }
        } else {
            $ok = Admin::useRecoveryCode($id, $code);
            if ($ok) {
                Session::flash('warning', 'Vous vous êtes connecté avec un code de secours. Il reste '
                    . Admin::remainingRecoveryCodes($id) . ' code(s) : pensez à en régénérer.');
            }
        }

        if (!$ok) {
            Throttle::hit('totp', (string) $id);
            error_log('[auth] Code de double authentification invalide.');
            return 'invalid';
        }

        Throttle::clear('totp', (string) $id);
        self::completeLogin($id);
        return 'ok';
    }

    /** Ouvre la session administrateur. */
    public static function completeLogin(int $adminId): void
    {
        Session::regenerate();
        Session::forget('_2fa_pending');
        $now = time();
        Session::set('_admin', [
            'id'        => $adminId,
            'auth_at'   => $now,
            'last_seen' => $now,
            'ua'        => hash('sha256', Request::userAgent()),
        ]);
        Csrf::rotate();
        Admin::touchLogin($adminId);
        self::$user = null;
    }

    /** L'administrateur est-il connecté (et sa session encore valide) ? */
    public static function check(): bool
    {
        if (!Session::hasCookie()) {
            return false;
        }
        $data = Session::get('_admin');
        if (!is_array($data) || !isset($data['id'], $data['auth_at'], $data['last_seen'], $data['ua'])) {
            return false;
        }
        $now      = time();
        $idle     = (int) Config::get('security.session_idle_timeout', 1800);
        $absolute = (int) Config::get('security.session_absolute_timeout', 28800);

        if ($now - (int) $data['last_seen'] > $idle
            || $now - (int) $data['auth_at'] > $absolute
            || !hash_equals((string) $data['ua'], hash('sha256', Request::userAgent()))) {
            self::logout();
            Session::start();
            Session::flash('info', 'Votre session a expiré, reconnectez-vous.');
            return false;
        }

        $data['last_seen'] = $now;
        Session::set('_admin', $data);
        return true;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        if (self::$user === null) {
            self::$user = Admin::find((int) Session::get('_admin')['id']);
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user !== null ? (int) $user['id'] : null;
    }

    /** Redirige vers la page de connexion si personne n'est connecté. */
    public static function requireLogin(): void
    {
        if (self::user() === null) {
            redirect('/admin/connexion');
        }
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
    }

    private static function dummyHash(): string
    {
        static $hash = null;
        return $hash ??= Admin::hashPassword(bin2hex(random_bytes(16)));
    }
}
