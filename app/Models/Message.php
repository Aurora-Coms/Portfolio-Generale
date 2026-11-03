<?php
/**
 * Messages reçus par le formulaire de contact (purgés après 12 mois : RGPD).
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Message
{
    /** Taille de page maximale acceptée par paginate(). */
    private const MAX_PER_PAGE = 100;

    public static function create(string $name, string $email, string $subject, string $body): int
    {
        return Database::insert(
            'INSERT INTO messages (name, email, subject, body) VALUES (?, ?, ?, ?)',
            [$name, $email, $subject, $body]
        );
    }

    public static function markMailSent(int $id): void
    {
        Database::execute('UPDATE messages SET mail_sent = 1 WHERE id = ?', [$id]);
    }

    /**
     * Page de messages, du plus récent au plus ancien.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public static function paginate(int $page, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = Crud::clamp($perPage, 1, self::MAX_PER_PAGE);
        // Borne haute de la page : évite un OFFSET démesuré (dépassement d'entier).
        $page    = min($page, 1_000_000);
        return [
            'items' => Database::fetchAll(
                'SELECT * FROM messages ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?',
                [$perPage, ($page - 1) * $perPage]
            ),
            'total' => (int) Database::value('SELECT COUNT(*) FROM messages'),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM messages WHERE id = ?', [$id]);
    }

    public static function markRead(int $id): void
    {
        Database::execute('UPDATE messages SET is_read = 1 WHERE id = ?', [$id]);
    }

    public static function toggleRead(int $id): void
    {
        Database::execute('UPDATE messages SET is_read = 1 - is_read WHERE id = ?', [$id]);
    }

    public static function delete(int $id): bool
    {
        return Database::execute('DELETE FROM messages WHERE id = ?', [$id]) > 0;
    }

    public static function unreadCount(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM messages WHERE is_read = 0');
    }

    /** @return list<array<string, mixed>> */
    public static function latest(int $limit): array
    {
        return Database::fetchAll(
            'SELECT * FROM messages ORDER BY created_at DESC, id DESC LIMIT ?',
            [Crud::clamp($limit, 1, self::MAX_PER_PAGE)]
        );
    }

    /**
     * Supprime les messages reçus il y a plus de $months mois (au moins 1).
     * Renvoie le nombre de messages supprimés.
     */
    public static function purgeOlderThan(int $months = 12): int
    {
        return Database::execute(
            'DELETE FROM messages WHERE created_at < NOW() - INTERVAL ? MONTH',
            [Crud::clamp($months, 1, 1200)]
        );
    }
}
