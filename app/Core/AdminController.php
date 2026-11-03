<?php
/**
 * Contrôleur de base de l'administration.
 *
 * Le constructeur exige une session administrateur valide : tout contrôleur
 * qui hérite de cette classe est protégé automatiquement.
 * Les pages d'admin ne sont jamais mises en cache ni indexées.
 */

declare(strict_types=1);

namespace App\Core;

abstract class AdminController
{
    public function __construct()
    {
        header('Cache-Control: no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        Auth::requireLogin();
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = [], int $status = 200): void
    {
        $data['meta'] = ($data['meta'] ?? []) + ['robots' => 'noindex'];
        View::render($view, $data, 'layouts/admin', $status);
    }

    protected function notFound(): never
    {
        throw new HttpException(404, 'Élément introuvable.');
    }

    /** Redirection après une action réussie, avec message. */
    protected function success(string $path, string $message): never
    {
        Session::flash('success', $message);
        redirect($path);
    }

    protected function error(string $path, string $message): never
    {
        Session::flash('error', $message);
        redirect($path);
    }
}
