<?php
/**
 * Authentification de l'administrateur.
 *
 * Mesures de sécurité :
 *  - mots de passe vérifiés avec password_verify (Argon2id / bcrypt) ;
 *  - format d'identifiant strict et comparaison binaire (pas de variantes
 *    « ádmin », « ａｄｍｉｎ »… qui contourneraient les compteurs) ;
 *  - temps de réponse identique que l'identifiant existe ou non (hachage
 *    factice PRÉCALCULÉ : une seule vérification dans tous les cas) ;
 *  - message d'erreur générique (on ne révèle pas si l'identifiant existe) ;
 *  - limitation ATOMIQUE : la tentative est enregistrée AVANT la vérification,
 *    puis comptée ; des requêtes envoyées en parallèle ne passent donc pas
 *    sous la limite (5 échecs / 15 min par IP, 10 / heure par compte) ;
 *  - « cookie d'appareil » signé (recommandation OWASP) : un navigateur qui
 *    s'est déjà connecté n'est pas bloqué par le verrou du compte, ce qui
 *    empêche un attaquant de bloquer le vrai administrateur ;
 *  - nouvel identifiant de session et nouveau jeton CSRF à la connexion ;
 *  - expiration après inactivité et durée de vie maximale de la session ;
 *  - « version de session » : changer le mot de passe ou désactiver la 2FA
 *    déconnecte les autres appareils ;
 *  - double authentification TOTP optionnelle (+ codes de secours), avec
 *    anti-rejeu atomique.
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

    /** Format accepté pour un identifiant (création et connexion). */
    public const USERNAME_PATTERN = '/^[A-Za-z0-9._-]{3,50}$/D';

    /** Cookie d'appareil de confiance (90 jours). */
    private const DEVICE_COOKIE = 'pf_device';
    private const DEVICE_TTL    = 7776000;

    /**
     * Hachages factices précalculés (mêmes paramètres que Admin::hashPassword).
     * Utilisés quand le compte n'existe pas, pour que la réponse prenne le
     * même temps qu'avec un vrai compte.
     */
    private const DUMMY_ARGON2ID = '$argon2id$v=19$m=65536,t=4,p=1$b0hhZ0J6RldQY0hRYUZQdA$Xmla6TjkuE6B44igUxE1EHgj9jxy04tkPqRqaWJRhuQ';
    private const DUMMY_BCRYPT   = '$2y$12$P7PqCu8Pis0hJc9/bhimlewBdGxdT0VoWsCzeK40hiKEkLvsANWw2';

    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    /**
     * Première étape de connexion.
     *
     * @return 'ok'|'2fa'|'invalid'|'locked'
     */
    public static function attempt(string $username, string $password): string
    {
        $ip       = self::ipKey(Request::ip());
        $username = trim($username);

        // 1. Limite par IP : on enregistre d'abord, on compte ensuite (atomique).
        Throttle::hit('login_ip', $ip);
        if (Throttle::count('login_ip', $ip, self::IP_WINDOW) > self::IP_MAX_FAILS) {
            return 'locked';
        }

        // 2. Identifiant au format inattendu : aucun accès à la base, même coût.
        $admin = preg_match(self::USERNAME_PATTERN, $username) === 1 ? Admin::findByUsername($username) : null;

        // 3. Limite par compte, sauf pour un appareil déjà reconnu.
        $userKey = $admin !== null ? 'id:' . $admin['id'] : '#inconnu';
        $trusted = $admin !== null && self::isTrustedDevice((int) $admin['id']);
        if (!$trusted) {
            Throttle::hit('login_user', $userKey);
            if (Throttle::count('login_user', $userKey, self::USER_WINDOW) > self::USER_MAX_FAILS) {
                return 'locked';
            }
        }

        // 4. Vérification (une seule, que le compte existe ou non).
        $hash  = $admin !== null ? (string) $admin['password_hash'] : self::dummyHash();
        $valid = password_verify($password, $hash) && $admin !== null;

        if (!$valid) {
            error_log('[auth] Échec de connexion administrateur.');
            return 'invalid';
        }

        // Succès : les tentatives réussies ne comptent pas comme des échecs.
        Throttle::clear('login_ip', $ip);
        if (!$trusted) {
            Throttle::clear('login_user', $userKey);
        }

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

        // Limite atomique : enregistrer puis compter.
        Throttle::hit('totp', (string) $id);
        if (Throttle::count('totp', (string) $id, self::IP_WINDOW) > self::TOTP_MAX_FAILS) {
            return 'locked';
        }

        $admin = Admin::find($id);
        if ($admin === null || (int) $admin['totp_enabled'] !== 1) {
            return 'expired';
        }

        $code = preg_replace('/\s+/', '', trim($code)) ?? '';
        $ok   = false;

        if (preg_match('/^\d{6}$/', $code)) {
            $secret = Crypto::decrypt((string) $admin['totp_secret_enc'], 'totp');
            if ($secret !== null) {
                $last = $admin['totp_last_step'] !== null ? (int) $admin['totp_last_step'] : null;
                $step = Totp::verify($secret, $code, $last);
                // Anti-rejeu atomique : seul le premier à enregistrer ce pas gagne.
                $ok = $step !== null && Admin::setTotpLastStep($id, $step);
            }
        } else {
            $ok = Admin::useRecoveryCode($id, $code);
            if ($ok) {
                Session::flash('warning', 'Vous vous êtes connecté avec un code de secours. Il reste '
                    . Admin::remainingRecoveryCodes($id) . ' code(s) : pensez à en régénérer.');
            }
        }

        if (!$ok) {
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
        $admin = Admin::find($adminId);
        $now   = time();
        Session::set('_admin', [
            'id'        => $adminId,
            'auth_at'   => $now,
            'last_seen' => $now,
            'ua'        => hash('sha256', Request::userAgent()),
            'version'   => (int) ($admin['session_version'] ?? 1),
        ]);
        Csrf::rotate();
        Admin::touchLogin($adminId);
        self::rememberDevice($adminId);
        self::$user = null;
    }

    /** L'administrateur est-il connecté (et sa session encore valide) ? */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        if (!Session::hasCookie()) {
            return null;
        }
        $data = Session::get('_admin');
        if (!is_array($data) || !isset($data['id'], $data['auth_at'], $data['last_seen'], $data['ua'])) {
            return null;
        }
        $now      = time();
        $idle     = (int) Config::get('security.session_idle_timeout', 1800);
        $absolute = (int) Config::get('security.session_absolute_timeout', 28800);
        $admin    = Admin::find((int) $data['id']);

        $expired = $now - (int) $data['last_seen'] > $idle
            || $now - (int) $data['auth_at'] > $absolute
            || !hash_equals((string) $data['ua'], hash('sha256', Request::userAgent()));
        // Compte supprimé, ou session ouverte avant un changement de mot de passe / de 2FA.
        $revoked = $admin === null || (int) ($admin['session_version'] ?? 1) !== (int) ($data['version'] ?? 1);

        if ($expired || $revoked) {
            self::logout();
            Session::start();
            Session::flash('info', $revoked
                ? 'Vous avez été déconnecté : les identifiants du compte ont changé.'
                : 'Votre session a expiré, reconnectez-vous.');
            return null;
        }

        $data['last_seen'] = $now;
        Session::set('_admin', $data);
        return self::$user = $admin;
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

    /**
     * À appeler après un changement de mot de passe ou la désactivation de la
     * 2FA : toutes les AUTRES sessions du compte deviennent invalides, la
     * session courante reste ouverte (avec un nouvel identifiant).
     */
    public static function revokeOtherSessions(): void
    {
        $id = self::id();
        if ($id === null) {
            return;
        }
        $version = Admin::bumpSessionVersion($id);
        Session::regenerate();
        $data = Session::get('_admin');
        if (is_array($data)) {
            $data['version'] = $version;
            Session::set('_admin', $data);
        }
        Csrf::rotate();
        self::$user = null;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
    }

    // --- Outils internes -------------------------------------------------------

    private static function dummyHash(): string
    {
        return defined('PASSWORD_ARGON2ID') ? self::DUMMY_ARGON2ID : self::DUMMY_BCRYPT;
    }

    /** Clé de limitation pour une IP : en IPv6, tout le préfixe /64 compte comme une seule adresse. */
    private static function ipKey(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed !== false && strlen($packed) === 16) {
            return bin2hex(substr($packed, 0, 8)) . '::/64';
        }
        return $ip;
    }

    /** Pose (ou renouvelle) le cookie signé de l'appareil après une connexion réussie. */
    private static function rememberDevice(int $adminId): void
    {
        if (headers_sent()) {
            return;
        }
        $payload = $adminId . '.' . time() . '.' . bin2hex(random_bytes(8));
        $value   = $payload . '.' . hash_hmac('sha256', 'device|' . $payload, Config::appKey());
        setcookie(self::DEVICE_COOKIE, $value, [
            'expires'  => time() + self::DEVICE_TTL,
            'path'     => '/admin',
            'secure'   => Request::isHttps() || !Config::isDev(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    /** Ce navigateur s'est-il déjà connecté avec succès à ce compte (cookie signé valide) ? */
    private static function isTrustedDevice(int $adminId): bool
    {
        $value = $_COOKIE[self::DEVICE_COOKIE] ?? null;
        if (!is_string($value) || strlen($value) > 200 || substr_count($value, '.') !== 3) {
            return false;
        }
        [$id, $issued, $nonce, $mac] = explode('.', $value);
        $payload = $id . '.' . $issued . '.' . $nonce;
        return hash_equals(hash_hmac('sha256', 'device|' . $payload, Config::appKey()), $mac)
            && ctype_digit($id) && (int) $id === $adminId
            && ctype_digit($issued) && time() - (int) $issued < self::DEVICE_TTL;
    }
}
