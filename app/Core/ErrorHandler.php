<?php
/**
 * Gestion centralisée des erreurs.
 *
 * - Production : aucune erreur PHP n'est affichée ; tout est journalisé dans
 *   app/storage/logs/php-errors.log et le visiteur voit une page générique.
 * - Développement : le détail de l'exception est affiché pour déboguer.
 */

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        $dev = Config::isDev();

        error_reporting(E_ALL);
        ini_set('display_errors', $dev ? '1' : '0');
        ini_set('display_startup_errors', $dev ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('html_errors', '0');

        $logDir = STORAGE_PATH . '/logs';
        if (is_dir($logDir) && is_writable($logDir)) {
            ini_set('error_log', $logDir . '/php-errors.log');
        }

        // Les « warnings » et « notices » deviennent des exceptions : aucun
        // comportement silencieusement incorrect. Les DÉPRÉCIATIONS, elles,
        // sont seulement journalisées en production : passer à une version de
        // PHP plus récente dans hPanel ne doit pas casser le site.
        set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($dev): bool {
            if (!(error_reporting() & $severity)) {
                return false; // erreur masquée volontairement avec @
            }
            if (!$dev && ($severity & (E_DEPRECATED | E_USER_DEPRECATED)) !== 0) {
                error_log(sprintf('[deprecated] %s in %s:%d', $message, $file, $line));
                return true;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handle']);

        // Erreurs fatales (mémoire épuisée, temps dépassé…) : pas d'exception,
        // mais on affiche quand même une page d'erreur au lieu d'une page vide.
        register_shutdown_function(static function () use ($dev): void {
            $err = error_get_last();
            if ($err === null || !($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
                return;
            }
            error_log(sprintf('[fatal] %s in %s:%d', $err['message'], $err['file'], $err['line']));
            if ($dev) {
                return; // display_errors affiche déjà le détail
            }
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
                header('Cache-Control: no-store');
            }
            echo self::minimalPage(500);
        });
    }

    /**
     * Point de sortie de toute exception non interceptée.
     */
    public static function handle(Throwable $e): void
    {
        $status = $e instanceof HttpException ? $e->status : 500;

        if ($status >= 500) {
            error_log(sprintf(
                '[%s] %s: %s in %s:%d',
                date('c'),
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }

        // Si des données ont déjà été envoyées, on ne peut plus changer le code.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store');
        }

        $message = $e instanceof HttpException ? $e->getMessage() : '';

        try {
            $path    = Request::path();
            $isAdmin = str_starts_with($path, '/admin') || $path === '/setup-admin.php';
            echo View::renderToString('site/errors/error', [
                'status'  => $status,
                'message' => $message,
                'detail'  => Config::isDev() && !($e instanceof HttpException)
                    ? $e::class . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()
                    : null,
                'meta'    => ['title' => self::title($status), 'robots' => 'noindex'],
            ], $isAdmin ? 'layouts/admin-auth' : 'layouts/site');
        } catch (Throwable $renderError) {
            // Dernier recours (ex. base de données indisponible) : page minimale.
            error_log('[error-page] ' . $renderError->getMessage());
            echo self::minimalPage($status);
        }
    }

    /**
     * Page de dernier recours, sans base de données ni gabarit. Aucun style
     * en ligne (interdit par la CSP) : elle utilise la feuille du site.
     */
    private static function minimalPage(int $status): string
    {
        $title = e(self::title($status));
        return '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex"><title>' . $title . '</title>'
            . '<link rel="stylesheet" href="/assets/css/site.css"></head>'
            . '<body><main class="container section"><h1>' . $title . '</h1>'
            . '<p><a href="/">Retour à l\'accueil</a></p></main></body></html>';
    }

    public static function title(int $status): string
    {
        return match ($status) {
            400     => 'Requête invalide',
            403     => 'Accès refusé',
            404     => 'Page introuvable',
            405     => 'Méthode non autorisée',
            413     => 'Fichier trop volumineux',
            419     => 'Session expirée',
            429     => 'Trop de tentatives',
            503     => 'Service indisponible',
            default => 'Erreur interne',
        };
    }
}
