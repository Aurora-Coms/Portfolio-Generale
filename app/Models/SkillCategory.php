<?php
/**
 * Sous-catégories de compétences (Réseau, Systèmes, Scripting / Lua…),
 * rangées chacune dans un thème (SkillTheme). Le nom de la table reste
 * « skill_categories » pour ne pas casser les liens existants.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;

final class SkillCategory
{
    /** Colonnes modifiables (liste blanche). */
    private const COLUMNS = ['theme_id', 'name', 'slug', 'icon', 'position'];

    /**
     * Toutes les catégories (par position), chacune avec sa clé « skills »
     * (compétences par position ; liste vide si la catégorie n'en a pas).
     * Deux requêtes au total.
     *
     * Conservée pour compatibilité : la page « Compétences » et
     * l'administration utilisent désormais SkillTheme::tree() (regroupement
     * par thème).
     *
     * @return list<array<string, mixed>>
     */
    public static function withSkills(): array
    {
        $categories = Database::fetchAll('SELECT * FROM skill_categories ORDER BY position ASC, name ASC, id ASC');
        $skills = [];
        foreach (Database::fetchAll('SELECT * FROM skills ORDER BY position ASC, name ASC, id ASC') as $skill) {
            $skills[(int) $skill['category_id']][] = $skill;
        }
        foreach ($categories as &$category) {
            $category['skills'] = $skills[(int) $category['id']] ?? [];
        }
        unset($category);
        return $categories;
    }

    /**
     * Toutes les sous-catégories + theme_name et skill_count, triées par
     * thème (position du thème) puis par position dans le thème.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        $rows = Database::fetchAll(
            'SELECT c.*, t.name AS theme_name,
                    (SELECT COUNT(*) FROM skills s WHERE s.category_id = c.id) AS skill_count
             FROM skill_categories c
             JOIN skill_themes t ON t.id = c.theme_id
             ORDER BY t.position ASC, t.name ASC, t.id ASC, c.position ASC, c.name ASC, c.id ASC'
        );
        foreach ($rows as &$row) {
            $row['skill_count'] = (int) $row['skill_count'];
        }
        unset($row);
        return $rows;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM skill_categories WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $d */
    public static function create(array $d): int
    {
        return Crud::insert('skill_categories', self::COLUMNS, $d);
    }

    /** @param array<string, mixed> $d */
    public static function update(int $id, array $d): void
    {
        Crud::update('skill_categories', self::COLUMNS, $id, $d);
    }

    /**
     * Supprime la catégorie. Renvoie false (sans exception) si des compétences
     * y sont encore rattachées (contrainte ON DELETE RESTRICT), true sinon
     * (y compris si la catégorie n'existait déjà plus).
     */
    public static function delete(int $id): bool
    {
        if ((int) Database::value('SELECT COUNT(*) FROM skills WHERE category_id = ?', [$id]) > 0) {
            return false;
        }
        try {
            Database::execute('DELETE FROM skill_categories WHERE id = ?', [$id]);
        } catch (PDOException $e) {
            // Compétence ajoutée entre-temps : violation de clé étrangère (SQLSTATE 23000).
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
        return true;
    }

    public static function slugExists(string $slug, ?int $except = null): bool
    {
        return Database::value('SELECT 1 FROM skill_categories WHERE slug = ? AND id <> ?', [$slug, $except ?? 0]) !== null;
    }
}
