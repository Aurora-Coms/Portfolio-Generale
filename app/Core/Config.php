<?php
/**
 * Accès à la configuration (app/config.php).
 *
 * Exemple : Config::get('db.host'), Config::get('mail.transport', 'none').
 */

declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string, mixed> */
    private static array $values = [];

    /**
     * Charge et valide la configuration. En cas d'erreur, le site s'arrête
     * proprement (503) sans divulguer de détails.
     *
     * @param array<string, mixed> $values
     */
    public static function load(array $values): void
    {
        self::$values = $values;

        $key = (string) self::get('security.app_key', '');
        if (!preg_match('/^[0-9a-f]{64}$/i', $key)) {
            self::fail("security.app_key doit contenir 64 caractères hexadécimaux.");
        }
        $baseUrl = (string) self::get('base_url', '');
        // Un sous-chemin est permis (site de projet GitHub Pages : https://compte.github.io/Depot).
        if (!preg_match('#^https?://[^/\s]+(?:/[A-Za-z0-9._~-]+)*$#', $baseUrl)) {
            self::fail("base_url doit ressembler à https://www.votre-domaine.fr (sans / final).");
        }
    }

    /**
     * Lit une valeur avec une notation à points.
     */
    public static function get(string $path, mixed $default = null): mixed
    {
        $value = self::$values;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /** Vrai uniquement en environnement de développement local. */
    public static function isDev(): bool
    {
        return self::get('env') === 'development';
    }

    /** Clé secrète binaire (32 octets) de l'application. */
    public static function appKey(): string
    {
        return (string) hex2bin((string) self::get('security.app_key'));
    }

    private static function fail(string $message): never
    {
        error_log('[config] ' . $message);
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo self::isDev()
            ? "Configuration invalide : {$message}\n"
            : "Site en maintenance : configuration invalide.\n";
        exit;
    }
}
