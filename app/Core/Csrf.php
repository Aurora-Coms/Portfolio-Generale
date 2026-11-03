<?php
/**
 * Protection CSRF (Cross-Site Request Forgery).
 *
 * Chaque formulaire POST contient un jeton aléatoire lié à la session
 * (<?= csrf_field() ?>). Le routeur vérifie ce jeton pour TOUTES les requêtes
 * POST, avec une comparaison en temps constant (hash_equals).
 * En complément, l'en-tête Origin (s'il est présent) doit correspondre au site.
 */

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf';

    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    /** Nouveau jeton (après connexion / déconnexion). */
    public static function rotate(): void
    {
        Session::start();
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    /**
     * Vérifie la requête POST courante ; lève une HttpException sinon.
     */
    public static function verifyRequest(): void
    {
        // Envoi plus gros que post_max_size : PHP vide $_POST, on l'explique.
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($_POST === [] && $_FILES === [] && $length > self::iniBytes((string) ini_get('post_max_size'))) {
            throw new HttpException(413, 'Les fichiers envoyés sont trop volumineux.');
        }

        // Défense en profondeur : l'origine déclarée doit être ce site.
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if (is_string($origin) && $origin !== '' && $origin !== 'null') {
            $originHost = parse_url($origin, PHP_URL_HOST);
            $siteHost   = parse_url((string) Config::get('base_url'), PHP_URL_HOST);
            $reqHost    = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
            if (!is_string($originHost) || ($originHost !== $siteHost && $originHost !== $reqHost)) {
                throw new HttpException(403, 'Origine de la requête refusée.');
            }
        }

        $sent = $_POST[self::KEY] ?? '';
        Session::start();
        $expected = $_SESSION[self::KEY] ?? '';
        if (!is_string($sent) || !is_string($expected) || $expected === '' || !hash_equals($expected, $sent)) {
            throw new HttpException(419, 'Votre session a expiré. Rechargez la page puis réessayez.');
        }
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        $unit  = strtolower(substr($value, -1));
        $num   = (int) $value;
        return match ($unit) {
            'g'     => $num * 1024 ** 3,
            'm'     => $num * 1024 ** 2,
            'k'     => $num * 1024,
            default => $num,
        };
    }
}
