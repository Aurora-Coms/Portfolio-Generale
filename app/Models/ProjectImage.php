<?php
/**
 * Images d'un projet. L'image de plus petite position est la couverture.
 *
 * Les positions sont maintenues compactes (0..n-1) par add(), delete(),
 * moveUp(), moveDown() et makeCover().
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ProjectImage
{
    /** Longueur maximale du texte alternatif (colonne alt_text). */
    private const ALT_MAX = 200;

    /** @return list<array<string, mixed>> */
    public static function forProject(int $projectId): array
    {
        return Database::fetchAll(
            'SELECT * FROM project_images WHERE project_id = ? ORDER BY position ASC, id ASC',
            [$projectId]
        );
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM project_images WHERE id = ?', [$id]);
    }

    /**
     * Enregistre une image produite par ImageProcessor::processUpload(), en
     * dernière position.
     *
     * @param array{filename: string, thumb_filename: string, width: int, height: int} $file
     */
    public static function add(int $projectId, array $file, string $alt): int
    {
        return Database::transaction(static function () use ($projectId, $file, $alt): int {
            // Verrou sur le projet : deux ajouts simultanés n'obtiennent pas la même position.
            Database::value('SELECT id FROM projects WHERE id = ? FOR UPDATE', [$projectId]);
            $position = (int) Database::value(
                'SELECT COALESCE(MAX(position) + 1, 0) FROM project_images WHERE project_id = ?',
                [$projectId]
            );
            return Database::insert(
                'INSERT INTO project_images (project_id, filename, thumb_filename, alt_text, width, height, position)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $projectId,
                    (string) $file['filename'],
                    (string) $file['thumb_filename'],
                    self::cleanAlt($alt),
                    (int) $file['width'],
                    (int) $file['height'],
                    $position,
                ]
            );
        });
    }

    public static function updateAlt(int $id, string $alt): void
    {
        Database::execute('UPDATE project_images SET alt_text = ? WHERE id = ?', [self::cleanAlt($alt), $id]);
    }

    /**
     * Supprime l'image et renvoie la ligne supprimée (pour effacer les fichiers),
     * ou null si elle n'existe pas. Les positions restantes sont renumérotées.
     *
     * @return array<string, mixed>|null
     */
    public static function delete(int $id): ?array
    {
        return Database::transaction(static function () use ($id): ?array {
            $row = Database::fetch('SELECT * FROM project_images WHERE id = ? FOR UPDATE', [$id]);
            if ($row === null) {
                return null;
            }
            Database::execute('DELETE FROM project_images WHERE id = ?', [$id]);
            $remaining = array_map(
                static fn (array $r): int => (int) $r['id'],
                self::lockedList((int) $row['project_id'])
            );
            self::renumber((int) $row['project_id'], $remaining);
            return $row;
        });
    }

    /** Échange l'image avec sa voisine précédente. */
    public static function moveUp(int $id): void
    {
        self::move($id, -1);
    }

    /** Échange l'image avec sa voisine suivante. */
    public static function moveDown(int $id): void
    {
        self::move($id, 1);
    }

    /** Place l'image en tête (couverture) puis renumérote 0..n-1. */
    public static function makeCover(int $id): void
    {
        Database::transaction(static function () use ($id): void {
            $projectId = self::projectOf($id);
            if ($projectId === null) {
                return;
            }
            $order = array_map(static fn (array $r): int => (int) $r['id'], self::lockedList($projectId));
            $order = array_values(array_filter($order, static fn (int $other): bool => $other !== $id));
            array_unshift($order, $id);
            self::renumber($projectId, $order);
        });
    }

    public static function countForProject(int $projectId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM project_images WHERE project_id = ?', [$projectId]);
    }

    // --- Interne -------------------------------------------------------------------

    /** Déplace l'image d'un cran ($step = -1 vers le haut, +1 vers le bas). */
    private static function move(int $id, int $step): void
    {
        Database::transaction(static function () use ($id, $step): void {
            $projectId = self::projectOf($id);
            if ($projectId === null) {
                return;
            }
            $order = array_map(static fn (array $r): int => (int) $r['id'], self::lockedList($projectId));
            $index = array_search($id, $order, true);
            if ($index === false) {
                return;
            }
            $target = $index + $step;
            if ($target < 0 || $target >= count($order)) {
                return; // déjà en tête ou en dernier
            }
            [$order[$index], $order[$target]] = [$order[$target], $order[$index]];
            self::renumber($projectId, $order);
        });
    }

    private static function projectOf(int $id): ?int
    {
        $value = Database::value('SELECT project_id FROM project_images WHERE id = ?', [$id]);
        return $value === null ? null : (int) $value;
    }

    /**
     * Images du projet, verrouillées jusqu'à la fin de la transaction.
     *
     * @return list<array<string, mixed>>
     */
    private static function lockedList(int $projectId): array
    {
        return Database::fetchAll(
            'SELECT id, position FROM project_images WHERE project_id = ? ORDER BY position ASC, id ASC FOR UPDATE',
            [$projectId]
        );
    }

    /**
     * Écrit les positions 0..n-1 dans l'ordre donné (seules les lignes qui changent).
     *
     * @param list<int> $orderedIds
     */
    private static function renumber(int $projectId, array $orderedIds): void
    {
        $current = [];
        foreach (self::lockedList($projectId) as $r) {
            $current[(int) $r['id']] = (int) $r['position'];
        }
        foreach ($orderedIds as $position => $imageId) {
            if (($current[$imageId] ?? null) !== $position) {
                Database::execute(
                    'UPDATE project_images SET position = ? WHERE id = ? AND project_id = ?',
                    [$position, $imageId, $projectId]
                );
            }
        }
    }

    private static function cleanAlt(string $alt): string
    {
        return mb_substr(trim($alt), 0, self::ALT_MAX);
    }
}
