<?php
/**
 * Rendu des vues (templates PHP dans app/Views).
 *
 * Toute donnée affichée dans une vue DOIT passer par e() (échappement HTML),
 * sauf le HTML produit par md() (Markdown déjà échappé) ou par un partial.
 *
 *   View::render('site/home', ['projects' => $p, 'meta' => ['title' => 'Accueil']]);
 *   View::render('admin/tags/index', [...], 'layouts/admin');
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    /**
     * Affiche une vue dans un layout.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/site', int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo self::renderToString($template, $data, $layout);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function renderToString(string $template, array $data = [], ?string $layout = 'layouts/site'): string
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, $data + ['content' => $content]);
    }

    /**
     * Rendu d'un fragment (« partial ») réutilisable.
     *
     * @param array<string, mixed> $data
     */
    public static function partial(string $name, array $data = []): string
    {
        return self::capture('partials/' . $name, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function capture(string $template, array $data): string
    {
        // Sécurité : pas de « ../ » ni de caractère exotique dans un nom de vue.
        if (!preg_match('#^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$#', $template)) {
            throw new RuntimeException('Nom de vue invalide : ' . $template);
        }
        $file = APP_PATH . '/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Vue introuvable : ' . $template);
        }

        $level = ob_get_level();
        ob_start();
        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
