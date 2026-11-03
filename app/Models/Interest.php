<?php
/**
 * Centres d'intérêt (page À propos).
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Interest
{
    /** Colonnes modifiables (liste blanche). */
    private const COLUMNS = ['label', 'description', 'icon', 'position'];

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return Database::fetchAll('SELECT * FROM interests ORDER BY position ASC, id ASC');
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM interests WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $d */
    public static function create(array $d): int
    {
        return Crud::insert('interests', self::COLUMNS, $d);
    }

    /** @param array<string, mixed> $d */
    public static function update(int $id, array $d): void
    {
        Crud::update('interests', self::COLUMNS, $id, $d);
    }

    public static function delete(int $id): bool
    {
        return Database::execute('DELETE FROM interests WHERE id = ?', [$id]) > 0;
    }
}
