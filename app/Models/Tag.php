<?php
/**
 * Tags / technologies (Lua, Cisco, Linux…), communs à toutes les sections.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Tag
{
    /** Colonnes modifiables (liste blanche). */
    private const COLUMNS = ['name', 'slug'];

    /**
     * Tous les tags (par nom) + project_count (projets liés, brouillons compris).
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        $rows = Database::fetchAll(
            'SELECT t.*,
                    (SELECT COUNT(*) FROM project_tags pt WHERE pt.tag_id = t.id) AS project_count
             FROM tags t
             ORDER BY t.name ASC, t.id ASC'
        );
        foreach ($rows as &$row) {
            $row['project_count'] = (int) $row['project_count'];
        }
        unset($row);
        return $rows;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM tags WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $d */
    public static function create(array $d): int
    {
        return Crud::insert('tags', self::COLUMNS, $d);
    }

    /** @param array<string, mixed> $d */
    public static function update(int $id, array $d): void
    {
        Crud::update('tags', self::COLUMNS, $id, $d);
    }

    /** Supprime le tag (les liaisons aux projets partent en cascade). */
    public static function delete(int $id): bool
    {
        return Database::execute('DELETE FROM tags WHERE id = ?', [$id]) > 0;
    }

    public static function slugExists(string $slug, ?int $except = null): bool
    {
        return Database::value('SELECT 1 FROM tags WHERE slug = ? AND id <> ?', [$slug, $except ?? 0]) !== null;
    }

    /** Comparaison insensible à la casse (collation utf8mb4_unicode_ci). */
    public static function nameExists(string $name, ?int $except = null): bool
    {
        return Database::value('SELECT 1 FROM tags WHERE name = ? AND id <> ?', [$name, $except ?? 0]) !== null;
    }
}
