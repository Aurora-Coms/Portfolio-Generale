<?php
/**
 * Exception portant un code HTTP (404, 403, 405, 413, 429…).
 * Elle est interceptée par ErrorHandler qui affiche la page d'erreur adaptée.
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    /**
     * @param int    $status  Code HTTP.
     * @param string $message Message lisible, affiché à l'utilisateur (jamais de détail technique).
     */
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }
}
