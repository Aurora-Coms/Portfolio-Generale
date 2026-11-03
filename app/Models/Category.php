<?php
/**
 * Catégories de projets, propres à une section (game | tech).
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Category
{
    /** Colonnes modifiables (liste blanche). */
    private const COLUMNS = ['section', 'name', 'slug', 'description', 'position'];

    /**
     * Catégories d'une section + project_count (nombre de projets PUBLIÉS liés).
     * Avec $onlyUsed, seules les catégories ayant au moins un projet publié.
     *
     * @return list<array<string, mixed>>
     */
    public static function forSection(string $section, bool $onlyUsed = false): array
    {
        $sql = 'SELECT c.*,
                       (SELECT COUNT(*) FROM project_categories pc
                        JOIN projects p ON p.id = pc.project_id AND p.is_published = 1
                        WHERE pc.category_id = c.id) AS project_count
                FROM categories c
                WHERE c.section = ?'
             . ($onlyUsed ? ' HAVING project_count > 0' : '')
             . ' ORDER BY c.position ASC, c.name ASC, c.id ASC';
        return self::castCounts(Database::fetchAll($sql, [$section]));
    }

    /**
     * Toutes les catégories (par section puis position) + project_count
     * (tous les projets liés, brouillons compris : utile avant une suppression).
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return self::castCounts(Database::fetchAll(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM project_categories pc WHERE pc.category_id = c.id) AS project_count
             FROM categories c
             ORDER BY c.section ASC, c.position ASC, c.name ASC, c.id ASC'
        ));
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $d */
    public static function create(array $d): int
    {
        return Crud::insert('categories', self::COLUMNS, $d);
    }

    /** @param array<string, mixed> $d */
    public static function update(int $id, array $d): void
    {
        Crud::update('categories', self::COLUMNS, $id, $d);
    }

    /** Supprime la catégorie (les liaisons aux projets partent en cascade). */
    public static function delete(int $id): bool
    {
        return Database::execute('DELETE FROM categories WHERE id = ?', [$id]) > 0;
    }

    public static function slugExists(string $section, string $slug, ?int $except = null): bool
    {
        return Database::value(
            'SELECT 1 FROM categories WHERE section = ? AND slug = ? AND id <> ?',
            [$section, $slug, $except ?? 0]
        ) !== null;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function castCounts(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['project_count'] = (int) $row['project_count'];
        }
        unset($row);
        return $rows;
    }
}
