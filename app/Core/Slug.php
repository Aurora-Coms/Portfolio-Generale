<?php
/**
 * Génération de « slugs » pour les URL : « Portail de la TVA » => « portail-de-la-tva ».
 */

declare(strict_types=1);

namespace App\Core;

final class Slug
{
    public static function make(string $text, int $max = 140): string
    {
        $text = trim($text);
        if (class_exists(\Transliterator::class)) {
            $t = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
            $text = $t !== null ? (string) $t->transliterate($text) : $text;
        } else {
            // Sans intl : table de correspondance d'abord (iconv dépend de la
            // locale du serveur et peut produire « r-seau » pour « Réseau »).
            $text = strtr(mb_strtolower($text, 'UTF-8'), [
                'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ç' => 'c',
                'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
                'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
                'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
                'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
            ]);
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            $text = strtolower($converted !== false ? $converted : $text);
        }
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');
        if (strlen($text) > $max) {
            $text = rtrim(substr($text, 0, $max), '-');
        }
        return $text;
    }
}
