<?php
/**
 * Import d'un fichier SQL (database.sql) par PHP, pour les hébergements où
 * l'on ne peut pas passer par phpMyAdmin (installation automatisée).
 *
 * Le fichier est découpé en requêtes en respectant les chaînes ('…', "…",
 * `…`, avec échappements) et les commentaires (-- …, # …, /* … *\/), puis
 * chaque requête est exécutée. Utilisé uniquement par public_html/setup-admin.php,
 * derrière le jeton d'installation, et seulement sur une base VIDE.
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class SqlImporter
{
    /**
     * Découpe un script SQL en requêtes individuelles (sans les commentaires).
     *
     * @return list<string>
     */
    public static function split(string $sql): array
    {
        $statements = [];
        $current    = '';
        $length     = strlen($sql);
        $quote      = null; // caractère de chaîne en cours (' " `) ou null

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\' && $quote !== '`') {
                    // Caractère échappé : copié tel quel.
                    $current .= $next;
                    $i++;
                } elseif ($char === $quote) {
                    if ($next === $quote) {
                        // Guillemet doublé ('') : toujours dans la chaîne.
                        $current .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            // Commentaires hors chaîne : ignorés.
            if (($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) || $char === '#') {
                $end = strpos($sql, "\n", $i);
                $i   = $end === false ? $length : $end;
                $current .= "\n";
                continue;
            }
            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    throw new RuntimeException('Commentaire /* non fermé dans le script SQL.');
                }
                $i = $end + 1;
                $current .= ' ';
                continue;
            }

            if ($char === '\'' || $char === '"' || $char === '`') {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if ($char === ';') {
                if (trim($current) !== '') {
                    $statements[] = trim($current);
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if ($quote !== null) {
            throw new RuntimeException('Chaîne non fermée dans le script SQL.');
        }
        if (trim($current) !== '') {
            $statements[] = trim($current);
        }
        return $statements;
    }

    /**
     * Exécute un fichier SQL complet. Renvoie le nombre de requêtes exécutées.
     */
    public static function importFile(string $path): int
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Fichier SQL introuvable.');
        }
        $statements = self::split((string) file_get_contents($path));
        $pdo = Database::pdo();
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        return count($statements);
    }

    /** La base est-elle vide (aucune table) ? */
    public static function databaseIsEmpty(): bool
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        ) === 0;
    }
}
