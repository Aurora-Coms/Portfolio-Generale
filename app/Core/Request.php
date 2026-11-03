<?php
/**
 * Lecture de la requête HTTP courante.
 */

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /**
     * Chemin demandé, décodé et normalisé (« /projet/tardis »), sans query string.
     */
    public static function path(): string
    {
        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }
        $path = rawurldecode($path);
        // Caractères de contrôle / octet nul : requête invalide.
        if (preg_match('/[\x00-\x1F\x7F]/', $path) || strlen($path) > 2000) {
            return '/__invalid__';
        }
        return $path;
    }

    /**
     * Valeur GET ou POST, toujours une chaîne (ou $default si absente / tableau).
     */
    public static function input(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    /**
     * Champ envoyé par formulaire POST uniquement (jamais lu dans l'URL) :
     * à utiliser pour les secrets (mots de passe, codes).
     */
    public static function post(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    /** Paramètre de query string uniquement. */
    public static function query(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    /**
     * Fichiers envoyés pour un champ, normalisés en liste (gère name="images[]").
     *
     * @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    public static function files(string $key): array
    {
        $raw = $_FILES[$key] ?? null;
        if (!is_array($raw) || !isset($raw['error'])) {
            return [];
        }
        if (!is_array($raw['error'])) {
            return [self::normalizeFile($raw)];
        }
        $files = [];
        foreach (array_keys($raw['error']) as $i) {
            $files[] = self::normalizeFile([
                'name'     => $raw['name'][$i] ?? '',
                'type'     => $raw['type'][$i] ?? '',
                'tmp_name' => $raw['tmp_name'][$i] ?? '',
                'error'    => $raw['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $raw['size'][$i] ?? 0,
            ]);
        }
        return array_values(array_filter($files, static fn (array $f): bool => $f['error'] !== UPLOAD_ERR_NO_FILE));
    }

    /**
     * Adresse IP du client. On utilise REMOTE_ADDR uniquement : les en-têtes
     * X-Forwarded-For sont falsifiables par n'importe qui.
     */
    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    /** La connexion est-elle chiffrée (HTTPS) ? */
    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        if (Config::get('security.trust_proxy') === true) {
            return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        }
        return false;
    }

    /**
     * @param array{name?:mixed,type?:mixed,tmp_name?:mixed,error?:mixed,size?:mixed} $f
     * @return array{name:string,type:string,tmp_name:string,error:int,size:int}
     */
    private static function normalizeFile(array $f): array
    {
        return [
            'name'     => is_string($f['name'] ?? null) ? $f['name'] : '',
            'type'     => is_string($f['type'] ?? null) ? $f['type'] : '',
            'tmp_name' => is_string($f['tmp_name'] ?? null) ? $f['tmp_name'] : '',
            'error'    => is_int($f['error'] ?? null) ? $f['error'] : UPLOAD_ERR_NO_FILE,
            'size'     => is_int($f['size'] ?? null) ? $f['size'] : 0,
        ];
    }
}
