<?php
/**
 * Outils internes partagés par les modèles (INSERT / UPDATE sur liste blanche).
 *
 * Les noms de colonnes ne viennent JAMAIS des clés de $data : on parcourt la
 * liste blanche fournie par le modèle (constante de classe) et on ne retient
 * que les colonnes présentes dans $data. Les valeurs sont toujours liées
 * en paramètres. Les noms de table et de colonne sont en plus contrôlés par
 * une expression régulière (défense en profondeur).
 *
 * @internal Réservé aux classes de App\Models.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;

final class Crud
{
    /**
     * Insère une ligne et renvoie son identifiant.
     *
     * @param list<string>          $allowed colonnes autorisées
     * @param array<string, mixed>  $data
     */
    public static function insert(string $table, array $allowed, array $data): int
    {
        $values = self::pick($allowed, $data);
        if ($values === []) {
            throw new InvalidArgumentException('Aucune colonne autorisée à insérer.');
        }
        $columns = array_keys($values);
        $sql = 'INSERT INTO ' . self::identifier($table)
             . ' (' . implode(', ', $columns) . ') VALUES (' . Database::placeholders($columns) . ')';
        return Database::insert($sql, array_values($values));
    }

    /**
     * Met à jour une ligne par identifiant. Renvoie le nombre de lignes modifiées.
     *
     * @param list<string>          $allowed
     * @param array<string, mixed>  $data
     */
    public static function update(string $table, array $allowed, int $id, array $data): int
    {
        $values = self::pick($allowed, $data);
        if ($values === []) {
            return 0;
        }
        $sets = [];
        foreach (array_keys($values) as $column) {
            $sets[] = $column . ' = ?';
        }
        $sql = 'UPDATE ' . self::identifier($table) . ' SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $params = array_values($values);
        $params[] = $id;
        return Database::execute($sql, $params);
    }

    /**
     * Ne garde que les colonnes de la liste blanche présentes dans $data
     * (dans l'ordre de la liste blanche). Les booléens deviennent 0/1.
     *
     * @param list<string>         $allowed
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function pick(array $allowed, array $data): array
    {
        $values = [];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $value = $data[$column];
                $values[self::identifier($column)] = is_bool($value) ? (int) $value : $value;
            }
        }
        return $values;
    }

    /**
     * Liste d'identifiants propres : entiers strictement positifs, sans doublon.
     * Accepte des entiers ou des chaînes de chiffres (données de formulaire).
     *
     * @param array<mixed> $ids
     * @return list<int>
     */
    public static function ids(array $ids): array
    {
        $clean = [];
        foreach ($ids as $value) {
            if (is_int($value) && $value > 0) {
                $clean[] = $value;
            } elseif (is_string($value) && preg_match('/^[1-9]\d{0,9}$/', $value) === 1) {
                $clean[] = (int) $value;
            }
        }
        return array_values(array_unique($clean));
    }

    /** Borne un entier entre $min et $max. */
    public static function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }

    /** Vérifie qu'un identifiant SQL est un nom simple (lettres minuscules et « _ »). */
    private static function identifier(string $name): string
    {
        if (preg_match('/^[a-z_]{1,64}$/', $name) !== 1) {
            throw new InvalidArgumentException('Identifiant SQL invalide.');
        }
        return $name;
    }
}
