<?php
/**
 * Contrôleur de base (site public).
 */

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = [], int $status = 200): void
    {
        View::render($view, $data, 'layouts/site', $status);
    }

    protected function notFound(string $message = ''): never
    {
        throw new HttpException(404, $message);
    }
}
