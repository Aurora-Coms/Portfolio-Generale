<?php
/**
 * Compétences, niveau de 1 (notions) à 5 (maîtrise).
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Skill
{
    /** Colonnes modifiables (liste blanche). */
    private const COLUMNS = ['category_id', 'name', 'level', 'description', 'position'];

    /**
     * Toutes les compétences + category_name, triées par catégorie puis position.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return Database::fetchAll(
            'SELECT s.*, c.name AS category_name
             FROM skills s JOIN skill_categories c ON c.id = s.category_id
             ORDER BY c.position ASC, c.name ASC, s.position ASC, s.name ASC, s.id ASC'
        );
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM skills WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $d */
    public static function create(array $d): int
    {
        return Crud::insert('skills', self::COLUMNS, $d);
    }

    /** @param array<string, mixed> $d */
    public static function update(int $id, array $d): void
    {
        Crud::update('skills', self::COLUMNS, $id, $d);
    }

    public static function delete(int $id): bool
    {
        return Database::execute('DELETE FROM skills WHERE id = ?', [$id]) > 0;
    }
}
