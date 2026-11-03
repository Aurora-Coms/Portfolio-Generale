<?php
/**
 * Chiffrement symétrique authentifié (AES-256-GCM).
 *
 * Utilisé pour stocker le secret de double authentification (TOTP) : même si
 * la base de données fuitait, le secret resterait illisible sans app_key
 * (qui, elle, est dans config.php, hors de la base).
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    public static function encrypt(string $plaintext, string $context = 'default'): string
    {
        $iv  = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, self::CIPHER, self::key($context), OPENSSL_RAW_DATA, $iv, $tag, $context, 16);
        if ($cipher === false) {
            throw new RuntimeException('Échec du chiffrement.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    /** Renvoie null si le message a été modifié ou si la clé ne correspond pas. */
    public static function decrypt(string $encoded, string $context = 'default'): ?string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $iv     = substr($raw, 0, 12);
        $tag    = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain  = openssl_decrypt($cipher, self::CIPHER, self::key($context), OPENSSL_RAW_DATA, $iv, $tag, $context);
        return $plain === false ? null : $plain;
    }

    /** Clé dérivée (HKDF) : une clé différente par usage. */
    private static function key(string $context): string
    {
        return hash_hkdf('sha256', Config::appKey(), 32, 'portfolio:' . $context);
    }
}
