<?php
/**
 * Thèmes généraux de compétences (Informatique & réseaux, Développement,
 * Langues…). Organisation : thème > sous-catégories (SkillCategory) >
 * compétences (Skill).
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDOException;

final class SkillTheme
{
    /** Colonnes modifiables (liste blanche). */
    private const COLUMNS = ['name', 'slug', 'icon', 'position'];

    /**
     * Arborescence complète, en 3 requêtes au total :
     * thèmes (par position), chacun avec sa clé « categories » (par position),
     * chacune avec sa clé « skills » (par position).
     *
     * Par défaut (page publique), les sous-catégories sans compétence et les
     * thèmes sans sous-catégorie affichable sont exclus. Avec $includeEmpty
     * (administration), tout est renvoyé.
     *
     * @return list<array<string, mixed>>
     */
    public static function tree(bool $includeEmpty = false): array
    {
        $themes = Database::fetchAll('SELECT * FROM skill_themes ORDER BY position ASC, name ASC, id ASC');

        $skills = [];
        foreach (Database::fetchAll('SELECT * FROM skills ORDER BY position ASC, name ASC, id ASC') as $skill) {
            $skills[(int) $skill['category_id']][] = $skill;
        }

        $categories = [];
        foreach (Database::fetchAll('SELECT * FROM skill_categories ORDER BY position ASC, name ASC, id ASC') as $category) {
            $category['skills'] = $skills[(int) $category['id']] ?? [];
            if ($includeEmpty || $category['skills'] !== []) {
                $categories[(int) $category['theme_id']][] = $category;
            }
        }

        $tree = [];
        foreach ($themes as $theme) {
            $theme['categories'] = $categories[(int) $theme['id']] ?? [];
            if ($includeEmpty || $theme['categories'] !== []) {
                $tree[] = $theme;
            }
        }
        return $tree;
    }

    /**
     * Tous les thèmes (par position) + category_count (nombre de sous-catégories).
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        $rows = Database::fetchAll(
            'SELECT t.*, (SELECT COUNT(*) FROM skill_categories c WHERE c.theme_id = t.id) AS category_count
             FROM skill_themes t
             ORDER BY t.position ASC, t.name ASC, t.id ASC'
        );
        foreach ($rows as &$row) {
            $row['category_count'] = (int) $row['category_count'];
        }
        unset($row);
        return $rows;
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM skill_themes WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $d */
    public static function create(array $d): int
    {
        return Crud::insert('skill_themes', self::COLUMNS, $d);
    }

    /** @param array<string, mixed> $d */
    public static function update(int $id, array $d): void
    {
        Crud::update('skill_themes', self::COLUMNS, $id, $d);
    }

    /**
     * Supprime le thème. Renvoie false (sans exception) s'il contient encore
     * des sous-catégories (contrainte ON DELETE RESTRICT), true sinon
     * (y compris si le thème n'existait déjà plus).
     */
    public static function delete(int $id): bool
    {
        if ((int) Database::value('SELECT COUNT(*) FROM skill_categories WHERE theme_id = ?', [$id]) > 0) {
            return false;
        }
        try {
            Database::execute('DELETE FROM skill_themes WHERE id = ?', [$id]);
        } catch (PDOException $e) {
            // Sous-catégorie ajoutée entre-temps : violation de clé étrangère (SQLSTATE 23000).
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
        return true;
    }

    public static function slugExists(string $slug, ?int $except = null): bool
    {
        return Database::value('SELECT 1 FROM skill_themes WHERE slug = ? AND id <> ?', [$slug, $except ?? 0]) !== null;
    }
}
