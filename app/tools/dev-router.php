<?php
/**
 * Routeur pour le serveur PHP intégré (DÉVELOPPEMENT LOCAL UNIQUEMENT).
 *
 *   php -S 127.0.0.1:8080 -t public_html app/tools/dev-router.php
 *
 * Le serveur intégré ignore les fichiers .htaccess ; ce script reproduit leur
 * comportement pour tester le site dans les mêmes conditions qu'en ligne :
 *  - mêmes en-têtes de sécurité (lus directement dans public_html/.htaccess) ;
 *  - fichiers cachés, sensibles et scripts PHP bloqués ;
 *  - dossier uploads limité aux images au nom généré par le serveur.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
    exit("Ce script ne sert qu'au serveur de développement PHP.\n");
}

$public = realpath(__DIR__ . '/../../public_html');
$path   = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// En-têtes de sécurité : copiés depuis .htaccess (une seule source de vérité).
$htaccess = (string) file_get_contents($public . '/.htaccess');
if (preg_match_all('/^\s*Header always set ([A-Za-z-]+) "(.*)"\s*$/m', $htaccess, $m, PREG_SET_ORDER)) {
    foreach ($m as $h) {
        if ($h[1] === 'Strict-Transport-Security') {
            continue; // inutile en HTTP local
        }
        // upgrade-insecure-requests casserait le site en HTTP local.
        header($h[1] . ': ' . str_replace('; upgrade-insecure-requests', '', $h[2]));
    }
}
header_remove('X-Powered-By');

$deny = static function (): never {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("403 Interdit\n");
};

// Règles équivalentes au .htaccess.
if (preg_match('#(^|/)\.(?!well-known/)#', $path)) {
    $deny();
}
if (preg_match('#^/(app|storage)(/|$)#i', $path)) {
    $deny();
}
if (preg_match('/\.(sql|bak|old|orig|save|swp|log|ini|sh|md|lock|dist|env|example|tar|gz|zip)$/i', $path)) {
    $deny();
}
if (preg_match('/\.(php\d?|phtml|phar)$/i', $path) && !in_array($path, ['/index.php', '/setup-admin.php'], true)) {
    $deny();
}
if (str_starts_with($path, '/uploads/')) {
    if (!preg_match('#^/uploads/projects/[a-f0-9]{32}(-thumb)?\.(webp|jpg)$#', $path)) {
        $deny();
    }
    header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; style-src \'none\'; sandbox');
}

if ($path === '/setup-admin.php' && is_file($public . '/setup-admin.php')) {
    require $public . '/setup-admin.php';
    return true;
}

// Fichiers statiques existants : servis avec le bon type MIME.
$file = realpath($public . $path);
if ($file !== false && is_file($file) && str_starts_with($file, $public . DIRECTORY_SEPARATOR) && !str_ends_with($file, '.php')) {
    $types = [
        'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
        'woff2' => 'font/woff2', 'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'txt' => 'text/plain; charset=utf-8',
        'xml' => 'application/xml', 'webmanifest' => 'application/manifest+json',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($file));
    readfile($file);
    return true;
}

// Sinon : contrôleur frontal.
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $public . '/index.php';
return true;
