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
