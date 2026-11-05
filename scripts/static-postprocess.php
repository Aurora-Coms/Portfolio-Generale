<?php
/**
 * Post-traitement de l'export statique (GitHub Pages).
 *
 *   php scripts/static-postprocess.php <dossier> <chemin_de_base>
 *   ex. php scripts/static-postprocess.php _site /Portfolio-Generale
 *
 * Sur GitHub Pages, un site de projet est servi sous un sous-chemin
 * (https://compte.github.io/Depot/). Les liens internes du site commencent par
 * « / » : on leur ajoute ce préfixe dans tous les fichiers HTML. Les liens
 * absolus (https://…, //…) et les ancres ne sont pas modifiés.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc < 3) {
    fwrite(STDERR, "Usage : php scripts/static-postprocess.php <dossier> <chemin_de_base>\n");
    exit(1);
}

$dir  = rtrim($argv[1], '/');
$base = rtrim($argv[2], '/');
if ($base !== '' && !preg_match('#^/[A-Za-z0-9._~/-]*$#', $base)) {
    fwrite(STDERR, "Chemin de base invalide : {$base}\n");
    exit(1);
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
$count = 0;
foreach ($files as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') {
        continue;
    }
    $html = (string) file_get_contents($file->getPathname());

    // Attributs d'URL dont la valeur commence par « / » (mais pas « // »).
    $html = (string) preg_replace_callback(
        '#\b(href|src|action|poster|srcset|data-[a-z-]+)="/(?!/)#',
        static fn (array $m): string => $m[1] . '="' . $base . '/',
        $html
    );
    // srcset : les candidats suivants (« …, /chemin 2x »).
    $html = (string) preg_replace_callback(
        '#\bsrcset="([^"]*)"#',
        static fn (array $m): string => 'srcset="' . preg_replace('#(,\s*)/(?!/)#', '$1' . $base . '/', $m[1]) . '"',
        $html
    );

    file_put_contents($file->getPathname(), $html);
    $count++;
}

echo "{$count} page(s) HTML adaptée(s) au chemin « {$base}/ ».\n";
