<?php
/**
 * Accès à la base de données via PDO.
 *
 * Règle d'or : TOUTES les requêtes passent par des requêtes préparées
 * (paramètres « ? » ou « :nom »). Jamais de concaténation de données
 * utilisateur dans une chaîne SQL.
 *
 * Exemples :
 *   Database::fetchAll('SELECT * FROM tags ORDER BY name');
 *   Database::fetch('SELECT * FROM projects WHERE slug = ?', [$slug]);
 *   $id = Database::insert('INSERT INTO tags (name, slug) VALUES (?, ?)', [$n, $s]);
 *   Database::transaction(function () { ... });
 */

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                (string) Config::get('db.host', 'localhost'),
                (int) Config::get('db.port', 3306),
                (string) Config::get('db.name', ''),
                (string) Config::get('db.charset', 'utf8mb4')
            );
            self::$pdo = new PDO($dsn, (string) Config::get('db.user', ''), (string) Config::get('db.pass', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // toute erreur SQL lève une exception
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                   // vraies requêtes préparées côté serveur
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            // Même fuseau horaire que PHP (Europe/Paris) pour les dates par défaut,
            // et mode strict : MySQL refuse les données invalides au lieu de les tronquer.
            // (ensemble valide sur MySQL 8 et MariaDB 10.4+, sans avertissement 3135)
            self::$pdo->exec("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            $tz = self::$pdo->prepare('SET time_zone = ?');
            $tz->execute([(new \DateTimeImmutable())->format('P')]);
        }
        return self::$pdo;
    }

    /**
     * Prépare et exécute une requête. Les entiers et null sont liés avec
     * le bon type (indispensable pour LIMIT/OFFSET).
     *
     * @param array<int|string, mixed> $params
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Première ligne, ou null.
     *
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Première colonne de la première ligne, ou null.
     *
     * @param array<int|string, mixed> $params
     */
    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /**
     * Exécute un INSERT et renvoie l'identifiant créé.
     *
     * @param array<int|string, mixed> $params
     */
    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Exécute un UPDATE / DELETE et renvoie le nombre de lignes touchées.
     *
     * @param array<int|string, mixed> $params
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Exécute $callback dans une transaction (annulée en cas d'exception).
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * L'exception signale-t-elle une valeur déjà utilisée (index UNIQUE) ?
     * Code 1062 (ER_DUP_ENTRY), identique sous MySQL et MariaDB. Le SQLSTATE
     * 23000 seul ne suffit pas : MariaDB l'utilise aussi pour les CHECK.
     */
    public static function isDuplicateKey(\PDOException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    /**
     * Génère « ?, ?, ? » pour une clause IN (...) préparée.
     *
     * @param array<mixed> $values
     */
    public static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, max(1, count($values)), '?'));
    }
}
