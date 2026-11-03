<?php
/**
 * Markdown restreint et SÛR pour les textes des projets / de l'à-propos.
 *
 * Principe de sécurité : le texte est d'abord ENTIÈREMENT échappé
 * (htmlspecialchars), puis seules quelques syntaxes connues sont transformées
 * en balises. Aucun HTML saisi ne peut donc passer.
 *
 * Syntaxe prise en charge :
 *   ### Titre / #### Sous-titre        **gras**   *italique*   `code`
 *   - liste à puces / 1. liste numérotée
 *   [texte](https://lien)  (http, https, mailto ou chemin interne « /… » uniquement)
 *   ``` bloc de code ```
 *   Ligne vide = nouveau paragraphe ; retour à la ligne simple = <br>.
 */

declare(strict_types=1);

namespace App\Core;

final class Markdown
{
    public static function render(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // 1. Blocs de code ``` … ``` mis de côté (aucune transformation dedans).
        $codeBlocks = [];
        $text = preg_replace_callback('/^```[a-z0-9_-]*\n(.*?)\n```$/ms', static function (array $m) use (&$codeBlocks): string {
            $codeBlocks[] = '<pre><code>' . e($m[1]) . '</code></pre>';
            return "\n\x1A" . (count($codeBlocks) - 1) . "\x1A\n";
        }, $text) ?? $text;

        $html   = [];
        $blocks = preg_split('/\n{2,}/', trim($text)) ?: [];

        foreach ($blocks as $block) {
            $block = trim($block, "\n");
            if ($block === '') {
                continue;
            }
            if (preg_match('/^\x1A(\d+)\x1A$/', $block, $m)) {
                $html[] = $codeBlocks[(int) $m[1]] ?? '';
                continue;
            }
            $lines = explode("\n", $block);

            // Titres
            if (count($lines) === 1 && preg_match('/^(#{3,4})\s+(.+)$/', $lines[0], $m)) {
                $level  = strlen($m[1]);
                $html[] = "<h{$level}>" . self::inline($m[2]) . "</h{$level}>";
                continue;
            }

            // Listes (toutes les lignes commencent par « - », « * » ou « 1. »)
            if (self::allMatch($lines, '/^\s*[-*]\s+/')) {
                $html[] = '<ul>' . self::items($lines, '/^\s*[-*]\s+/') . '</ul>';
                continue;
            }
            if (self::allMatch($lines, '/^\s*\d+[.)]\s+/')) {
                $html[] = '<ol>' . self::items($lines, '/^\s*\d+[.)]\s+/') . '</ol>';
                continue;
            }

            // Paragraphe
            $html[] = '<p>' . implode('<br>', array_map([self::class, 'inline'], $lines)) . '</p>';
        }

        return implode("\n", $html);
    }

    /** Version texte brut (pour les résumés / balises meta). */
    public static function plain(?string $text): string
    {
        if ($text === null) {
            return '';
        }
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/[*_`#>]+/', '', $text) ?? $text;
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    /** @param list<string> $lines */
    private static function allMatch(array $lines, string $pattern): bool
    {
        foreach ($lines as $line) {
            if (!preg_match($pattern, $line)) {
                return false;
            }
        }
        return true;
    }

    /** @param list<string> $lines */
    private static function items(array $lines, string $pattern): string
    {
        $out = '';
        foreach ($lines as $line) {
            $out .= '<li>' . self::inline((string) preg_replace($pattern, '', $line, 1)) . '</li>';
        }
        return $out;
    }

    /** Transformations en ligne, appliquées sur du texte DÉJÀ échappé. */
    private static function inline(string $text): string
    {
        $text = e($text);

        // Code en ligne : mis de côté pour ne pas interpréter son contenu.
        $codes = [];
        $text = preg_replace_callback('/`([^`]+)`/', static function (array $m) use (&$codes): string {
            $codes[] = '<code>' . $m[1] . '</code>';
            return "\x1B" . (count($codes) - 1) . "\x1B";
        }, $text) ?? $text;

        // Liens [texte](url) : schémas autorisés uniquement.
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static function (array $m): string {
            $url = $m[2]; // déjà échappé (& => &amp;, " => &quot;)
            if (!preg_match('#^(https?://|mailto:|/(?!/))#i', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                return $m[0];
            }
            $external = preg_match('#^https?://#i', $url) === 1;
            return '<a href="' . $url . '"' . ($external ? ' rel="noopener noreferrer"' : '') . '>' . $m[1] . '</a>';
        }, $text) ?? $text;

        $text = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<![*\w])\*(?=\S)(.+?)(?<=\S)\*(?![*\w])/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/(?<![_\w])_(?=\S)(.+?)(?<=\S)_(?![_\w])/', '<em>$1</em>', $text) ?? $text;

        return preg_replace_callback('/\x1B(\d+)\x1B/', static fn (array $m): string => $codes[(int) $m[1]] ?? '', $text) ?? $text;
    }
}
