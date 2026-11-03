<?php
/**
 * Point d'entrée unique du site (toutes les URL arrivent ici via .htaccess).
 * Le code de l'application est dans ../app, hors de la partie publique.
 */

declare(strict_types=1);

define('PUBLIC_PATH', __DIR__);

require dirname(__DIR__) . '/app/bootstrap.php';

App\Core\App::run();
