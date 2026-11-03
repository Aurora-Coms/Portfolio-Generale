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
        // comportement silencieusement incorrect.
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false; // erreur masquée volontairement avec @
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handle']);
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
            $isAdmin = str_starts_with(Request::path(), '/admin');
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
            echo '<!doctype html><html lang="fr"><meta charset="utf-8"><title>'
                . e(self::title($status)) . '</title><body style="background:#13141B;color:#D5D8E4;font-family:sans-serif;padding:2rem">'
                . '<h1>' . e(self::title($status)) . '</h1><p><a style="color:#C3A6FF" href="/">Retour à l\'accueil</a></p></body></html>';
        }
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
