<?php
/**
 * Démarrage de l'application.
 *
 * Chargé par public_html/index.php (et par les scripts d'outillage). Il :
 *  - définit les chemins de base ;
 *  - enregistre l'autoloader (namespace App\ => dossier app/) ;
 *  - charge la configuration ;
 *  - installe la gestion des erreurs (rien n'est affiché en production).
 */

declare(strict_types=1);

use App\Core\Config;
use App\Core\ErrorHandler;

define('APP_PATH', __DIR__);
define('STORAGE_PATH', APP_PATH . '/storage');
if (!defined('PUBLIC_PATH')) {
    // Emplacement par défaut : public_html est le voisin de app/.
    define('PUBLIC_PATH', dirname(__DIR__) . '/public_html');
}

// Autoloader minimal : App\Core\Database => app/Core/Database.php
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, 4));
    // Sécurité : on n'accepte que des noms de classes « normaux ».
    if (!preg_match('#^[A-Za-z0-9_/]+$#', $relative)) {
        return;
    }
    $file = APP_PATH . '/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/helpers.php';

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

// Avant même la configuration : ne jamais afficher d'erreur par défaut.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$configFile = APP_PATH . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Site en maintenance : la configuration est absente (app/config.php).\n");
}

Config::load(require $configFile);

ErrorHandler::register();
