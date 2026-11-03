<?php
/**
 * Limitation de débit (anti brute-force / anti-spam).
 *
 * Chaque tentative est enregistrée dans la table « throttle » sous la forme
 * d'un HMAC-SHA256 de la clé (IP, identifiant…) : aucune IP en clair (RGPD).
 *
 *   if (Throttle::tooMany('login', $ip, 5, 900)) { ... bloqué ... }
 *   Throttle::hit('login', $ip);
 */

declare(strict_types=1);

namespace App\Core;

final class Throttle
{
    /** Nombre de tentatives enregistrées pour cette clé dans la fenêtre (secondes). */
    public static function count(string $action, string $value, int $windowSeconds): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM throttle WHERE action = ? AND key_hash = ? AND created_at > (NOW() - INTERVAL ? SECOND)',
            [$action, self::hash($action, $value), $windowSeconds]
        );
    }

    public static function tooMany(string $action, string $value, int $max, int $windowSeconds): bool
    {
        return self::count($action, $value, $windowSeconds) >= $max;
    }

    public static function hit(string $action, string $value): void
    {
        Database::insert('INSERT INTO throttle (action, key_hash) VALUES (?, ?)', [$action, self::hash($action, $value)]);

        // Nettoyage occasionnel des entrées de plus de 2 jours.
        if (random_int(1, 50) === 1) {
            Database::execute('DELETE FROM throttle WHERE created_at < (NOW() - INTERVAL 2 DAY)');
        }
    }

    public static function clear(string $action, string $value): void
    {
        Database::execute('DELETE FROM throttle WHERE action = ? AND key_hash = ?', [$action, self::hash($action, $value)]);
    }

    /** HMAC de la clé avec la clé secrète de l'application. */
    public static function hash(string $action, string $value): string
    {
        return hash_hmac('sha256', $action . '|' . $value, Config::appKey());
    }
}
