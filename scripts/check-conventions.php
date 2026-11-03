<?php
/**
 * Contrôle automatique des règles impératives du projet (docs/ARCHITECTURE.md §2).
 * Outil de développement et d'intégration continue : jamais déployé.
 *
 *     php scripts/check-conventions.php
 *
 * Vérifie :
 *  1. Rédaction : aucun tiret long « — » ni tiret moyen « – » dans app/Views/
 *     ni dans database.sql (textes visibles et données de départ ; les lignes
 *     de commentaire SQL « -- … » de database.sql ne sont pas affichées et
 *     sont donc ignorées).
 *  2. CSP stricte, dans app/Views/ :
 *     - aucun attribut style="…" ni balise <style> ;
 *     - aucun <script> exécutable en ligne : seuls sont admis les scripts
 *       externes (<script src="…">) et les données JSON-LD
 *       (<script type="application/ld+json">) ;
 *     - aucun gestionnaire d'événement en ligne (onclick=, onload=…) ;
 *     - aucune URL « javascript: » dans href, src, action ou formaction.
 *
 * Code de sortie : 0 si tout est conforme, 1 sinon. Dans GitHub Actions, chaque
 * problème est aussi signalé comme annotation (visible dans la pull request).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Script réservé à la ligne de commande.\n");
}

$racine  = dirname(__DIR__);
$github  = getenv('GITHUB_ACTIONS') === 'true';
$erreurs = 0;

/** Affiche un problème (format « fichier:ligne: message » + annotation GitHub). */
function signaler(string $fichier, int $ligne, string $message): void
{
    global $erreurs, $github;
    $erreurs++;
    echo "{$fichier}:{$ligne}: {$message}\n";
    if ($github) {
        $echappe = static fn (string $s): string => strtr($s, ['%' => '%25', "\r" => '%0D', "\n" => '%0A', ':' => '%3A', ',' => '%2C']);
        echo '::error file=' . $echappe($fichier) . ',line=' . $ligne . ',title=Conventions::'
            . strtr($message, ['%' => '%25', "\r" => '%0D', "\n" => '%0A']) . "\n";
    }
}

/** Numéro de ligne (1, 2…) correspondant à une position dans le texte. */
function ligne_de(string $texte, int $position): int
{
    return substr_count($texte, "\n", 0, $position) + 1;
}

/** Cherche les tirets interdits ; $ignorer(ligne) permet d'écarter des lignes. */
function verifier_tirets(string $relatif, string $texte, ?callable $ignorer = null): void
{
    foreach (preg_split('/\R/u', $texte) ?: [] as $i => $ligne) {
        if ($ignorer !== null && $ignorer($ligne)) {
            continue;
        }
        foreach (['—' => 'tiret long « — »', '–' => 'tiret moyen « – »'] as $tiret => $nom) {
            if (str_contains($ligne, $tiret)) {
                signaler($relatif, $i + 1, "{$nom} interdit dans les textes : utiliser « · » ou « : »");
            }
        }
    }
}

/** Règles de la CSP stricte appliquées à une vue. */
function verifier_csp(string $relatif, string $texte): void
{
    $regles = [
        // style="…" (mais pas data-style=, ni une variable PHP $style = …)
        '/(?<![\w$-])style\s*=/i'                                   => 'attribut style="…" interdit (CSP) : utiliser une classe CSS',
        '/<style\b/i'                                               => 'balise <style> interdite (CSP) : utiliser assets/css/*.css',
        // onclick=, onload=… (mais pas une variable PHP $onlyUsed = …)
        '/(?<![\w$-])on[a-z]{3,}\s*=\s*["\'<]/i'                    => 'gestionnaire d\'événement en ligne interdit (CSP) : utiliser assets/js/*.js',
        '/\b(?:href|src|action|formaction)\s*=\s*["\']?\s*javascript:/i' => 'URL « javascript: » interdite (CSP)',
    ];
    foreach ($regles as $motif => $message) {
        if (preg_match_all($motif, $texte, $trouves, PREG_OFFSET_CAPTURE)) {
            foreach ($trouves[0] as [, $position]) {
                signaler($relatif, ligne_de($texte, $position), $message);
            }
        }
    }

    // <script …> : seuls les scripts externes (src=) et le JSON-LD sont admis.
    // Les attributs peuvent contenir du PHP (balise courte d'affichage) et tenir sur
    // plusieurs lignes : le motif saute les blocs PHP avant de chercher le « > » final.
    if (preg_match_all('/<script\b((?:<\?.*?\?>|[^>])*)>/is', $texte, $balises, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        foreach ($balises as $balise) {
            $attributs = $balise[1][0];
            $externe   = preg_match('/(?:^|\s)src\s*=/i', $attributs) === 1;
            $jsonLd    = preg_match('/(?:^|\s)type\s*=\s*(["\']?)application\/ld\+json\1(?:\s|$)/i', $attributs) === 1;
            if (!$externe && !$jsonLd) {
                signaler($relatif, ligne_de($texte, $balise[0][1]),
                    'script en ligne interdit (CSP) : utiliser <script src="…" defer> et assets/js/*.js');
            }
        }
    }
}

// --- Vues : app/Views/** -----------------------------------------------------------

$dossierVues = $racine . '/app/Views';
if (!is_dir($dossierVues)) {
    fwrite(STDERR, "Dossier introuvable : app/Views\n");
    exit(1);
}
$vues = [];
$parcours = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dossierVues, FilesystemIterator::SKIP_DOTS));
foreach ($parcours as $fichier) {
    if ($fichier->isFile()) {
        $vues[] = $fichier->getPathname();
    }
}
sort($vues);

foreach ($vues as $chemin) {
    $relatif = substr($chemin, strlen($racine) + 1);
    $texte   = (string) file_get_contents($chemin);
    verifier_tirets($relatif, $texte);
    verifier_csp($relatif, $texte);
}

// --- Données de départ : database.sql -------------------------------------------------

$sql = $racine . '/database.sql';
if (!is_file($sql)) {
    fwrite(STDERR, "Fichier introuvable : database.sql\n");
    exit(1);
}
verifier_tirets('database.sql', (string) file_get_contents($sql),
    static fn (string $ligne): bool => str_starts_with(ltrim($ligne), '--'));

// --- Bilan ----------------------------------------------------------------------------------

$total = count($vues) + 1;
if ($erreurs > 0) {
    echo "{$erreurs} problème(s) dans {$total} fichiers vérifiés (règles : docs/ARCHITECTURE.md §2).\n";
    exit(1);
}
echo "OK : {$total} fichiers conformes (tirets, styles et scripts en ligne).\n";
exit(0);
