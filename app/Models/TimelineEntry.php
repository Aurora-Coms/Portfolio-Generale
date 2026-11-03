<?php
/**
 * Entrées du parcours (études, stages, autres). end_date NULL = « en cours ».
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class TimelineEntry
{
    /** Colonnes modifiables (liste blanche). */
    private const COLUMNS = [
        'type', 'title', 'organization', 'location', 'start_date', 'end_date',
        'date_precision', 'description', 'is_published', 'position',
    ];

    /**
     * Entrées publiées, de la plus récente à la plus ancienne.
     *
     * @return list<array<string, mixed>>
     */
    public static function published(): array
    {
        return Database::fetchAll(
            'SELECT * FROM timeline_entries WHERE is_published = 1
             ORDER BY start_date DESC, position ASC, id DESC'
        );
    }

    /**
     * Toutes les entrées (brouillons compris), même tri.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return Database::fetchAll('SELECT * FROM timeline_entries ORDER BY start_date DESC, position ASC, id DESC');
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM timeline_entries WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $d */
    public static function create(array $d): int
    {
        return Crud::insert('timeline_entries', self::COLUMNS, $d);
    }

    /** @param array<string, mixed> $d */
    public static function update(int $id, array $d): void
    {
        Crud::update('timeline_entries', self::COLUMNS, $id, $d);
    }

    public static function delete(int $id): bool
    {
        return Database::execute('DELETE FROM timeline_entries WHERE id = ?', [$id]) > 0;
    }
}
