<?php
/**
 * Session sécurisée.
 *
 * La session n'est démarrée QUE lorsqu'elle est nécessaire (formulaire de
 * contact, administration) : un simple visiteur du portfolio ne reçoit aucun
 * cookie.
 *
 * Cookie : HttpOnly (inaccessible au JavaScript), Secure (HTTPS uniquement),
 * SameSite=Strict (jamais envoyé depuis un autre site => protection CSRF
 * supplémentaire), préfixe « __Host- » en HTTPS (lié au domaine exact).
 */

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https  = Request::isHttps();
        // En production on exige « Secure » même si la détection HTTPS échouait :
        // mieux vaut une connexion impossible qu'un cookie envoyé en clair.
        $secure = $https || !Config::isDev();

        ini_set('session.use_strict_mode', '1');   // refuse les identifiants inventés
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cache_limiter', 'nocache');
        ini_set('session.gc_maxlifetime', (string) (int) Config::get('security.session_absolute_timeout', 28800));
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

        // Sessions stockées dans app/storage/sessions (hors web, propre au site).
        $dir = STORAGE_PATH . '/sessions';
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }

        session_name($secure && $https ? '__Host-pfsess' : 'pfsess');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    /** Nouvel identifiant de session (à chaque changement de privilège). */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /** Message affiché une seule fois (après une redirection). */
    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Récupère puis efface les messages flash.
     *
     * @return list<array{type:string, message:string}>
     */
    public static function pullFlashes(): array
    {
        // Pas de session existante => pas de message (et on n'en crée pas).
        if (session_status() !== PHP_SESSION_ACTIVE && !self::hasCookie()) {
            return [];
        }
        self::start();
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($flashes) ? $flashes : [];
    }

    /** Détruit complètement la session (déconnexion). */
    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_destroy();
    }

    /** Le navigateur a-t-il envoyé un cookie de session ? */
    public static function hasCookie(): bool
    {
        return isset($_COOKIE['__Host-pfsess']) || isset($_COOKIE['pfsess']);
    }
}
