<?php
/**
 * Mots de passe à usage unique basés sur le temps (TOTP).
 *
 * Implémentation conforme à la RFC 6238 (TOTP) et à la RFC 4226 (HOTP) :
 * HMAC-SHA1, 6 chiffres, pas de 30 secondes. Ce sont les paramètres par
 * défaut de Google Authenticator, Microsoft Authenticator, Aegis et 2FAS.
 *
 * Le secret est manipulé sous forme base32 (RFC 4648, alphabet A-Z2-7),
 * format attendu par les applications d'authentification.
 */

declare(strict_types=1);

namespace App\Core;

final class Totp
{
    public const DIGITS = 6;
    public const PERIOD = 30;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Génère un secret aléatoire (20 octets = 160 bits, recommandé par la RFC 4226), encodé en base32. */
    public static function generateSecret(int $bytes = 20): string
    {
        if ($bytes < 10 || $bytes > 64) {
            throw new \InvalidArgumentException('Taille de secret invalide.');
        }
        return self::base32Encode(random_bytes($bytes));
    }

    /** Encodage base32 RFC 4648 sans caractère de remplissage « = ». */
    public static function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    /**
     * Décodage base32 tolérant : ignore la casse, les espaces, les tirets et le
     * remplissage final. Renvoie null si un caractère est hors alphabet ou si
     * les bits de fin ne sont pas nuls (encodage non canonique).
     */
    public static function base32Decode(string $encoded): ?string
    {
        $clean = strtoupper((string) preg_replace('/[\s\-]+/', '', $encoded));
        $clean = rtrim($clean, '=');
        if ($clean === '') {
            return null;
        }
        // Longueurs impossibles en base32 (1, 3 ou 6 caractères restants).
        if (in_array(strlen($clean) % 8, [1, 3, 6], true)) {
            return null;
        }
        $bits = '';
        $len  = strlen($clean);
        for ($i = 0; $i < $len; $i++) {
            $pos = strpos(self::ALPHABET, $clean[$i]);
            if ($pos === false) {
                return null;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $usable = intdiv(strlen($bits), 8) * 8;
        if (strpos(substr($bits, $usable), '1') !== false) {
            return null;
        }
        $out = '';
        foreach (str_split(substr($bits, 0, $usable), 8) as $byte) {
            $out .= chr(bindec($byte));
        }
        return $out;
    }

    /** Numéro du pas de temps courant (ou de l'horodatage donné). */
    public static function currentStep(?int $time = null): int
    {
        return intdiv($time ?? time(), self::PERIOD);
    }

    /**
     * Code HOTP (RFC 4226) pour un pas donné : HMAC-SHA1 puis troncature dynamique.
     * Le secret est en base32 ; lève une exception s'il est invalide.
     */
    public static function code(string $secret, int $step): string
    {
        $key = self::base32Decode($secret);
        if ($key === null) {
            throw new \InvalidArgumentException('Secret TOTP invalide.');
        }
        return self::hotp($key, $step);
    }

    /**
     * Vérifie un code saisi. Renvoie le pas accepté (à mémoriser pour
     * l'anti-rejeu) ou null. Tolère une dérive d'horloge de ±$window pas.
     * Tout pas inférieur ou égal à $lastStep est refusé : un code déjà
     * utilisé (ou plus ancien) ne peut pas être rejoué.
     */
    public static function verify(string $secret, string $code, ?int $lastStep = null, int $window = 1): ?int
    {
        if (preg_match('/^\d{6}$/D', $code) !== 1) {
            return null;
        }
        $key = self::base32Decode($secret);
        if ($key === null || $key === '') {
            return null;
        }
        $window  = max(0, min($window, 10));
        $current = self::currentStep();
        $matched = null;

        // On parcourt toute la fenêtre sans sortir plus tôt, pour un temps
        // d'exécution indépendant de la position du code correct.
        for ($offset = -$window; $offset <= $window; $offset++) {
            $step = $current + $offset;
            if ($step < 0) {
                continue;
            }
            $ok = hash_equals(self::hotp($key, $step), $code);
            if ($ok && ($lastStep === null || $step > $lastStep) && $matched === null) {
                $matched = $step;
            }
        }
        return $matched;
    }

    /**
     * URI otpauth:// à encoder en QR code (format « Key Uri Format » de
     * Google Authenticator, reconnu par toutes les applications courantes).
     */
    public static function provisioningUri(string $secret, string $label, string $issuer): string
    {
        $secret = strtoupper((string) preg_replace('/[\s=\-]+/', '', $secret));
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($label)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    /** HOTP sur une clé binaire. */
    private static function hotp(string $key, int $counter): string
    {
        // Compteur sur 8 octets gros-boutiste (« J » = entier 64 bits non signé).
        $hash   = hash_hmac('sha1', pack('J', $counter), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value  = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);
        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }
}
