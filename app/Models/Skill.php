<?php
/**
 * Compétences, niveau de 1 (notions) à 5 (maîtrise).
 * level NULL : compétence sans barre de niveau (savoir-être, atouts).
 * level_label : libellé affiché à la place du libellé standard (« A2 »…).
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Skill
{
    /** Colonnes modifiables (liste blanche). */
    private const COLUMNS = ['category_id', 'name', 'level', 'level_label', 'description', 'position'];

    /**
     * Toutes les compétences + category_name et theme_name, triées par thème,
     * puis sous-catégorie, puis position.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return Database::fetchAll(
            'SELECT s.*, c.name AS category_name, t.name AS theme_name
             FROM skills s
             JOIN skill_categories c ON c.id = s.category_id
             JOIN skill_themes t ON t.id = c.theme_id
             ORDER BY t.position ASC, t.name ASC, t.id ASC, c.position ASC, c.name ASC, c.id ASC,
                      s.position ASC, s.name ASC, s.id ASC'
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
