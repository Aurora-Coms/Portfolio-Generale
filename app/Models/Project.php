<?php
/**
 * Projets (jeux Roblox et projets techniques) avec leurs catégories, tags et images.
 *
 * Les listes publiques sont « hydratées » en un nombre fixe de requêtes
 * (catégories, tags, couvertures chargées pour tous les projets d'un coup
 * avec IN (...)) : pas de problème N+1.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Slug;
use InvalidArgumentException;

final class Project
{
    /** Colonnes modifiables via create() / update() (liste blanche). */
    private const COLUMNS = [
        'section', 'title', 'slug', 'summary', 'context', 'work_done', 'learnings',
        'youtube_id', 'external_url', 'external_label', 'status', 'project_date',
        'is_published', 'is_featured', 'position',
    ];

    private const SECTIONS = ['game', 'tech'];

    /** Tri commun des listes de projets. */
    private const ORDER = 'p.position ASC, p.project_date DESC, p.id DESC';

    // --- Site public -----------------------------------------------------------

    /**
     * Projets publiés d'une section, éventuellement filtrés par catégorie.
     *
     * @return list<array<string, mixed>>
     */
    public static function published(string $section, ?string $categorySlug = null): array
    {
        if (!in_array($section, self::SECTIONS, true)) {
            return [];
        }
        if ($categorySlug === null || $categorySlug === '') {
            $rows = Database::fetchAll(
                'SELECT p.* FROM projects p
                 WHERE p.section = ? AND p.is_published = 1
                 ORDER BY ' . self::ORDER,
                [$section]
            );
        } else {
            $rows = Database::fetchAll(
                'SELECT p.* FROM projects p
                 WHERE p.section = ? AND p.is_published = 1
                   AND EXISTS (
                       SELECT 1 FROM project_categories pc
                       JOIN categories c ON c.id = pc.category_id
                       WHERE pc.project_id = p.id AND c.section = p.section AND c.slug = ?
                   )
                 ORDER BY ' . self::ORDER,
                [$section, $categorySlug]
            );
        }
        return self::hydrate($rows);
    }

    /**
     * Projets publiés et mis en avant (toutes sections).
     *
     * @return list<array<string, mixed>>
     */
    public static function featured(int $limit = 4): array
    {
        $rows = Database::fetchAll(
            'SELECT p.* FROM projects p
             WHERE p.is_published = 1 AND p.is_featured = 1
             ORDER BY ' . self::ORDER . '
             LIMIT ?',
            [Crud::clamp($limit, 1, 50)]
        );
        return self::hydrate($rows);
    }

    /**
     * Projet publié par son slug, avec toutes ses images (clé « images »).
     *
     * @return array<string, mixed>|null
     */
    public static function findPublishedBySlug(string $slug): ?array
    {
        $row = Database::fetch('SELECT p.* FROM projects p WHERE p.slug = ? AND p.is_published = 1', [$slug]);
        if ($row === null) {
            return null;
        }
        $project = self::hydrate([$row])[0];
        $project['images'] = ProjectImage::forProject((int) $project['id']);
        return $project;
    }

    // --- Administration ----------------------------------------------------------

    /**
     * Tous les projets (brouillons compris) + image_count + categories.
     *
     * @return list<array<string, mixed>>
     */
    public static function adminList(?string $section = null): array
    {
        $select = 'SELECT p.*, (SELECT COUNT(*) FROM project_images i WHERE i.project_id = p.id) AS image_count
                   FROM projects p';
        if ($section === null) {
            $rows = Database::fetchAll($select . ' ORDER BY p.section ASC, ' . self::ORDER);
        } elseif (in_array($section, self::SECTIONS, true)) {
            $rows = Database::fetchAll($select . ' WHERE p.section = ? ORDER BY ' . self::ORDER, [$section]);
        } else {
            return [];
        }
        $categories = self::categoriesFor(array_map(static fn (array $r): int => (int) $r['id'], $rows));
        foreach ($rows as &$row) {
            $row['image_count'] = (int) $row['image_count'];
            $row['categories']  = $categories[(int) $row['id']] ?? [];
        }
        unset($row);
        return $rows;
    }

    /**
     * Ligne brute + category_ids + tag_ids.
     *
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $row = Database::fetch('SELECT * FROM projects WHERE id = ?', [$id]);
        if ($row === null) {
            return null;
        }
        $row['category_ids'] = array_map('intval', array_column(
            Database::fetchAll('SELECT category_id FROM project_categories WHERE project_id = ? ORDER BY category_id', [$id]),
            'category_id'
        ));
        $row['tag_ids'] = array_map('intval', array_column(
            Database::fetchAll('SELECT tag_id FROM project_tags WHERE project_id = ? ORDER BY tag_id', [$id]),
            'tag_id'
        ));
        return $row;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<mixed>         $categoryIds
     * @param array<mixed>         $tagIds
     */
    public static function create(array $data, array $categoryIds, array $tagIds): int
    {
        return Database::transaction(static function () use ($data, $categoryIds, $tagIds): int {
            $id = Crud::insert('projects', self::COLUMNS, $data);
            self::syncLinks($id, $categoryIds, $tagIds);
            return $id;
        });
    }

    /**
     * Met à jour le projet et REMPLACE ses liaisons catégories / tags.
     *
     * @param array<string, mixed> $data
     * @param array<mixed>         $categoryIds
     * @param array<mixed>         $tagIds
     */
    public static function update(int $id, array $data, array $categoryIds, array $tagIds): void
    {
        Database::transaction(static function () use ($id, $data, $categoryIds, $tagIds): void {
            // Verrou de la ligne : le projet doit exister.
            if (Database::value('SELECT id FROM projects WHERE id = ? FOR UPDATE', [$id]) === null) {
                return;
            }
            Crud::update('projects', self::COLUMNS, $id, $data);
            self::syncLinks($id, $categoryIds, $tagIds);
        });
    }

    /**
     * Supprime le projet (liaisons et images supprimées en cascade) et renvoie
     * les lignes d'images pour que l'appelant efface les fichiers.
     *
     * @return list<array<string, mixed>>
     */
    public static function delete(int $id): array
    {
        return Database::transaction(static function () use ($id): array {
            $images = ProjectImage::forProject($id);
            Database::execute('DELETE FROM projects WHERE id = ?', [$id]);
            return $images;
        });
    }

    /**
     * Slug libre dérivé de $base : « mon-jeu », puis « mon-jeu-2 », « mon-jeu-3 »…
     */
    public static function uniqueSlug(string $base, ?int $exceptId = null): string
    {
        $slug = Slug::make($base, 130);
        if ($slug === '') {
            $slug = 'projet';
        }
        $candidate = $slug;
        for ($n = 2; self::slugTaken($candidate, $exceptId); $n++) {
            $candidate = $slug . '-' . $n;
        }
        return $candidate;
    }

    /** Inverse un indicateur booléen (liste blanche stricte). */
    public static function toggle(int $id, string $field): void
    {
        $sql = match ($field) {
            'is_published' => 'UPDATE projects SET is_published = 1 - is_published WHERE id = ?',
            'is_featured'  => 'UPDATE projects SET is_featured = 1 - is_featured WHERE id = ?',
            default        => throw new InvalidArgumentException('Champ non modifiable.'),
        };
        Database::execute($sql, [$id]);
    }

    /**
     * Compteurs du tableau de bord. « featured » = projets marqués « mis en avant »
     * (publiés ou non).
     *
     * @return array{game: int, tech: int, published: int, drafts: int, featured: int}
     */
    public static function counts(): array
    {
        $row = Database::fetch(
            "SELECT COALESCE(SUM(section = 'game'), 0) AS game,
                    COALESCE(SUM(section = 'tech'), 0) AS tech,
                    COALESCE(SUM(is_published = 1), 0) AS published,
                    COALESCE(SUM(is_published = 0), 0) AS drafts,
                    COALESCE(SUM(is_featured = 1), 0) AS featured
             FROM projects"
        ) ?? [];
        return [
            'game'      => (int) ($row['game'] ?? 0),
            'tech'      => (int) ($row['tech'] ?? 0),
            'published' => (int) ($row['published'] ?? 0),
            'drafts'    => (int) ($row['drafts'] ?? 0),
            'featured'  => (int) ($row['featured'] ?? 0),
        ];
    }

    // --- Interne -------------------------------------------------------------------

    private static function slugTaken(string $slug, ?int $exceptId): bool
    {
        return Database::value('SELECT 1 FROM projects WHERE slug = ? AND id <> ?', [$slug, $exceptId ?? 0]) !== null;
    }

    /**
     * Remplace les liaisons. Les catégories d'une autre section que celle du
     * projet et les identifiants inexistants sont ignorés (INSERT … SELECT).
     * À appeler dans une transaction.
     *
     * @param array<mixed> $categoryIds
     * @param array<mixed> $tagIds
     */
    private static function syncLinks(int $projectId, array $categoryIds, array $tagIds): void
    {
        $categoryIds = Crud::ids($categoryIds);
        $tagIds      = Crud::ids($tagIds);

        Database::execute('DELETE FROM project_categories WHERE project_id = ?', [$projectId]);
        if ($categoryIds !== []) {
            Database::execute(
                'INSERT INTO project_categories (project_id, category_id)
                 SELECT p.id, c.id FROM projects p
                 JOIN categories c ON c.section = p.section
                 WHERE p.id = ? AND c.id IN (' . Database::placeholders($categoryIds) . ')',
                [$projectId, ...$categoryIds]
            );
        }

        Database::execute('DELETE FROM project_tags WHERE project_id = ?', [$projectId]);
        if ($tagIds !== []) {
            Database::execute(
                'INSERT INTO project_tags (project_id, tag_id)
                 SELECT ?, t.id FROM tags t WHERE t.id IN (' . Database::placeholders($tagIds) . ')',
                [$projectId, ...$tagIds]
            );
        }
    }

    /**
     * Ajoute cover, categories et tags à une liste de projets (3 requêtes au total).
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function hydrate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);

        $categories = self::categoriesFor($ids);

        $tags = [];
        foreach (Database::fetchAll(
            'SELECT pt.project_id, t.id, t.name, t.slug
             FROM project_tags pt JOIN tags t ON t.id = pt.tag_id
             WHERE pt.project_id IN (' . Database::placeholders($ids) . ')
             ORDER BY t.name ASC, t.id ASC',
            $ids
        ) as $t) {
            $tags[(int) $t['project_id']][] = ['id' => (int) $t['id'], 'name' => (string) $t['name'], 'slug' => (string) $t['slug']];
        }

        // Couverture : l'image de plus petite position (puis plus petit id) de chaque projet.
        $covers = [];
        foreach (Database::fetchAll(
            'SELECT i.project_id, i.thumb_filename, i.filename, i.alt_text, i.width, i.height
             FROM project_images i
             WHERE i.project_id IN (' . Database::placeholders($ids) . ')
               AND NOT EXISTS (
                   SELECT 1 FROM project_images j
                   WHERE j.project_id = i.project_id
                     AND (j.position < i.position OR (j.position = i.position AND j.id < i.id))
               )',
            $ids
        ) as $img) {
            $covers[(int) $img['project_id']] = [
                'thumb_filename' => (string) $img['thumb_filename'],
                'filename'       => (string) $img['filename'],
                'alt_text'       => (string) $img['alt_text'],
                'width'          => (int) $img['width'],
                'height'         => (int) $img['height'],
            ];
        }

        foreach ($rows as &$row) {
            $id = (int) $row['id'];
            $row['cover']      = $covers[$id] ?? null;
            $row['categories'] = $categories[$id] ?? [];
            $row['tags']       = $tags[$id] ?? [];
        }
        unset($row);
        return $rows;
    }

    /**
     * Catégories des projets donnés, indexées par id de projet.
     *
     * @param list<int> $ids
     * @return array<int, list<array{id: int, name: string, slug: string}>>
     */
    private static function categoriesFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $result = [];
        foreach (Database::fetchAll(
            'SELECT pc.project_id, c.id, c.name, c.slug
             FROM project_categories pc JOIN categories c ON c.id = pc.category_id
             WHERE pc.project_id IN (' . Database::placeholders($ids) . ')
             ORDER BY c.position ASC, c.name ASC, c.id ASC',
            $ids
        ) as $c) {
            $result[(int) $c['project_id']][] = ['id' => (int) $c['id'], 'name' => (string) $c['name'], 'slug' => (string) $c['slug']];
        }
        return $result;
    }
}
