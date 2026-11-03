<?php
/**
 * Point d'entrée applicatif : charge les routes et traite la requête.
 */

declare(strict_types=1);

namespace App\Core;

final class App
{
    public static function run(): void
    {
        header_remove('X-Powered-By');

        $router = new Router();
        (static function (Router $router): void {
            require APP_PATH . '/routes.php';
        })($router);

        $router->dispatch(Request::method(), Request::path());
    }
}
