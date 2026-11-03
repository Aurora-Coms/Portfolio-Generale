<?php
/**
 * Compte administrateur et codes de secours 2FA.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Admin
{
    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM admins WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed>|null */
    public static function findByUsername(string $username): ?array
    {
        // Comparaison BINAIRE : la collation par défaut confondrait « admin »,
        // « ádmin » ou « ａｄｍｉｎ », ce qui contournerait la limitation par compte.
        return Database::fetch('SELECT * FROM admins WHERE username = ? COLLATE utf8mb4_bin', [$username]);
    }

    public static function count(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM admins');
    }

    public static function create(string $username, string $password): int
    {
        return Database::insert(
            'INSERT INTO admins (username, password_hash) VALUES (?, ?)',
            [$username, self::hashPassword($password)]
        );
    }

    public static function updatePassword(int $id, string $password): void
    {
        Database::execute('UPDATE admins SET password_hash = ? WHERE id = ?', [self::hashPassword($password), $id]);
    }

    public static function touchLogin(int $id): void
    {
        Database::execute('UPDATE admins SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    /**
     * Hachage du mot de passe : Argon2id si disponible, sinon bcrypt (coût 12).
     */
    public static function hashPassword(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function needsRehash(string $hash): bool
    {
        return defined('PASSWORD_ARGON2ID')
            ? password_needs_rehash($hash, PASSWORD_ARGON2ID)
            : password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Règles de mot de passe. Renvoie un message d'erreur ou null si valide.
     */
    public static function passwordProblem(string $password, string $username = ''): ?string
    {
        $length = mb_strlen($password);
        if ($length < 12) {
            return 'Le mot de passe doit contenir au moins 12 caractères.';
        }
        if (strlen($password) > 72) {
            return 'Le mot de passe ne doit pas dépasser 72 octets.';
        }
        if ($username !== '' && stripos($password, $username) !== false) {
            return 'Le mot de passe ne doit pas contenir l\'identifiant.';
        }
        if (count(array_unique(mb_str_split($password))) < 6) {
            return 'Le mot de passe est trop répétitif.';
        }
        $common = ['motdepasse', 'password', 'azerty', 'qwerty', '123456', 'admin', 'portfolio', 'soleil', 'bonjour'];
        $lower  = mb_strtolower($password);
        foreach ($common as $word) {
            if (str_contains($lower, $word) && $length < 16) {
                return 'Le mot de passe contient un mot trop courant : allongez-le ou choisissez une phrase de passe.';
            }
        }
        return null;
    }

    // --- Double authentification -------------------------------------------

    public static function enableTotp(int $id, string $encryptedSecret, int $step): void
    {
        Database::execute(
            'UPDATE admins SET totp_secret_enc = ?, totp_enabled = 1, totp_last_step = ? WHERE id = ?',
            [$encryptedSecret, $step, $id]
        );
    }

    public static function disableTotp(int $id): void
    {
        Database::transaction(static function () use ($id): void {
            Database::execute('UPDATE admins SET totp_secret_enc = NULL, totp_enabled = 0, totp_last_step = NULL WHERE id = ?', [$id]);
            Database::execute('DELETE FROM admin_recovery_codes WHERE admin_id = ?', [$id]);
        });
    }

    /**
     * Enregistre le dernier pas TOTP accepté, de façon ATOMIQUE : renvoie false
     * si ce pas (ou un pas plus récent) a déjà été utilisé, y compris par une
     * requête concurrente (anti-rejeu).
     */
    public static function setTotpLastStep(int $id, int $step): bool
    {
        return Database::execute(
            'UPDATE admins SET totp_last_step = ? WHERE id = ? AND (totp_last_step IS NULL OR totp_last_step < ?)',
            [$step, $id, $step]
        ) === 1;
    }

    /**
     * Incrémente la « version de session » du compte : toutes les sessions
     * ouvertes avant cet appel deviennent invalides. Renvoie la nouvelle version.
     */
    public static function bumpSessionVersion(int $id): int
    {
        Database::execute('UPDATE admins SET session_version = session_version + 1 WHERE id = ?', [$id]);
        return (int) Database::value('SELECT session_version FROM admins WHERE id = ?', [$id]);
    }

    /**
     * Génère 8 nouveaux codes de secours (affichés UNE fois, stockés hachés).
     *
     * @return list<string>
     */
    public static function regenerateRecoveryCodes(int $id): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $raw     = strtoupper(bin2hex(random_bytes(5))); // 10 caractères hexadécimaux
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
        }
        Database::transaction(static function () use ($id, $codes): void {
            Database::execute('DELETE FROM admin_recovery_codes WHERE admin_id = ?', [$id]);
            foreach ($codes as $code) {
                Database::insert(
                    'INSERT INTO admin_recovery_codes (admin_id, code_hash) VALUES (?, ?)',
                    [$id, password_hash(str_replace('-', '', $code), PASSWORD_BCRYPT, ['cost' => 10])]
                );
            }
        });
        return $codes;
    }

    /** Consomme un code de secours s'il est valide (usage unique). */
    public static function useRecoveryCode(int $id, string $code): bool
    {
        $normalized = strtoupper(preg_replace('/[^0-9a-fA-F]/', '', $code) ?? '');
        if (strlen($normalized) !== 10) {
            return false;
        }
        $rows = Database::fetchAll('SELECT id, code_hash FROM admin_recovery_codes WHERE admin_id = ? AND used_at IS NULL', [$id]);
        foreach ($rows as $row) {
            if (password_verify($normalized, (string) $row['code_hash'])) {
                return Database::execute(
                    'UPDATE admin_recovery_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL',
                    [(int) $row['id']]
                ) === 1;
            }
        }
        return false;
    }

    public static function remainingRecoveryCodes(int $id): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM admin_recovery_codes WHERE admin_id = ? AND used_at IS NULL', [$id]);
    }
}
