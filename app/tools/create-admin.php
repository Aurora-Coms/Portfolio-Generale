<?php
/**
 * Création d'un compte administrateur EN LIGNE DE COMMANDE (alternative à
 * public_html/setup-admin.php, par exemple en SSH chez Hostinger).
 *
 *   php app/tools/create-admin.php                       crée un compte
 *   php app/tools/create-admin.php --reset               change le mot de passe d'un compte existant
 *   php app/tools/create-admin.php --reset --disable-2fa  idem + désactive la double authentification
 *   php app/tools/create-admin.php --help                aide
 *
 * Sécurité :
 *  - refuse de s'exécuter ailleurs qu'en ligne de commande ;
 *  - mot de passe saisi sans écho (stty -echo), l'affichage du terminal est
 *    TOUJOURS restauré (fin normale, erreur, Ctrl+C) ;
 *  - mêmes règles que le site : identifiant [a-zA-Z0-9._-]{3,50},
 *    Admin::passwordProblem, confirmation identique ;
 *  - après --reset, toutes les sessions ouvertes sont fermées.
 *
 * Sans terminal (entrée redirigée : « … < fichier » ou tube), les lignes sont
 * lues telles quelles sur l'entrée standard (pratique pour les tests).
 *
 * Codes de sortie : 0 succès, 1 erreur, 2 mauvaise utilisation.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    // Ne doit jamais être servi par le web (app/ est de toute façon hors de public_html).
    http_response_code(403);
    exit;
}

// -----------------------------------------------------------------------------
//  Options
// -----------------------------------------------------------------------------
$args    = array_slice($argv ?? [], 1);
$known   = ['--reset', '--disable-2fa', '--help', '-h'];
$unknown = array_diff($args, $known);

$usage = <<<TXT
Création ou réinitialisation d'un compte administrateur du portfolio.

Utilisation :
  php app/tools/create-admin.php                        créer un compte
  php app/tools/create-admin.php --reset                changer le mot de passe d'un compte existant
  php app/tools/create-admin.php --reset --disable-2fa  idem, et désactiver la double authentification
                                                        (téléphone perdu ET codes de secours perdus)

Règles : identifiant de 3 à 50 caractères (lettres sans accent, chiffres, . _ -),
mot de passe d'au moins 12 caractères ne contenant pas l'identifiant.

TXT;

if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    fwrite(STDOUT, $usage);
    exit(0);
}
if ($unknown !== []) {
    fwrite(STDERR, 'Option inconnue : ' . implode(' ', $unknown) . "\n\n" . $usage);
    exit(2);
}
$reset      = in_array('--reset', $args, true);
$disable2fa = in_array('--disable-2fa', $args, true);
if ($disable2fa && !$reset) {
    fwrite(STDERR, "L'option --disable-2fa s'utilise avec --reset.\n\n" . $usage);
    exit(2);
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Models\Admin;

// En ligne de commande, pas de page HTML d'erreur : un message clair suffit.
set_exception_handler(static function (Throwable $e): void {
    cli_restore_echo();
    fwrite(STDERR, "\nErreur : " . $e->getMessage() . "\n");
    exit(1);
});

// -----------------------------------------------------------------------------
//  Saisie au terminal
// -----------------------------------------------------------------------------

/** Réglages du terminal sauvegardés avant « stty -echo » (null = rien à restaurer). */
$GLOBALS['cli_stty_saved'] = null;

/** L'entrée standard est-elle un vrai terminal (et non un fichier ou un tube) ? */
function cli_is_tty(): bool
{
    if (function_exists('stream_isatty')) {
        return stream_isatty(STDIN);
    }
    return function_exists('posix_isatty') && posix_isatty(STDIN);
}

/** Exécute une commande stty sur le terminal courant ; null en cas d'échec. */
function cli_stty(string $arguments): ?string
{
    if (!function_exists('shell_exec')) {
        return null; // fonction désactivée par l'hébergeur
    }
    $output = @shell_exec('stty ' . $arguments . ' 2>/dev/null; echo "#$?"');
    if (!is_string($output) || !preg_match('/#(\d+)\s*$/', $output, $m) || $m[1] !== '0') {
        return null;
    }
    return trim((string) preg_replace('/#\d+\s*$/', '', $output));
}

/** Coupe l'écho du terminal. Renvoie false si c'est impossible. */
function cli_disable_echo(): bool
{
    $saved = cli_stty('-g');
    if ($saved === null || $saved === '' || !preg_match('/^[0-9a-fA-F:]+$/', $saved)) {
        return false;
    }
    // Mémorisé AVANT de couper l'écho : un Ctrl+C immédiat le restaure quand même.
    $GLOBALS['cli_stty_saved'] = $saved;
    if (cli_stty('-echo') === null) {
        $GLOBALS['cli_stty_saved'] = null;
        return false;
    }
    return true;
}

/** Restaure l'écho du terminal s'il avait été coupé (appel sans effet sinon). */
function cli_restore_echo(): void
{
    $saved = $GLOBALS['cli_stty_saved'] ?? null;
    if (is_string($saved)) {
        $GLOBALS['cli_stty_saved'] = null;
        cli_stty(escapeshellarg($saved));
        fwrite(STDOUT, "\n");
    }
}

// Restauration garantie : fin du script, erreur fatale, Ctrl+C, arrêt du processus.
register_shutdown_function('cli_restore_echo');
$GLOBALS['cli_signals'] = function_exists('pcntl_async_signals') && function_exists('pcntl_signal');
if ($GLOBALS['cli_signals']) {
    pcntl_async_signals(true);
    foreach ([SIGINT => 130, SIGTERM => 143, SIGHUP => 129] as $signal => $code) {
        // Troisième argument false : un appel système interrompu n'est pas relancé.
        pcntl_signal($signal, static function () use ($code): void {
            cli_restore_echo();
            fwrite(STDERR, "\nInterrompu : aucun changement n'a été enregistré.\n");
            exit($code);
        }, false);
    }
}

/**
 * Attend qu'une ligne complète soit disponible au terminal.
 *
 * On n'appelle pas directement fgets() : bloqué dans read(), PHP ne traiterait
 * Ctrl+C qu'après la touche Entrée. stream_select(), lui, est interrompu par le
 * signal, et le gestionnaire ci-dessus s'exécute aussitôt (écho restauré).
 */
function cli_wait_for_line(): void
{
    if (!cli_is_tty()) {
        return;
    }
    $write = null;
    $except = null;
    do {
        $read = [STDIN];
        $ready = @stream_select($read, $write, $except, 0, 250000);
    } while ($ready === 0 || $ready === false);
}

/** Lit une ligne (sans le saut de ligne final). Termine proprement en fin d'entrée. */
function cli_read_line(): string
{
    cli_wait_for_line();
    $line = fgets(STDIN);
    if ($line === false) {
        cli_restore_echo();
        fwrite(STDERR, "\nSaisie interrompue : aucun changement n'a été enregistré.\n");
        exit(1);
    }
    if (!cli_is_tty()) {
        fwrite(STDOUT, "\n"); // entrée redirigée : la réponse n'est pas affichée, on passe à la ligne
    }
    return rtrim($line, "\r\n");
}

function cli_ask(string $question): string
{
    fwrite(STDOUT, $question);
    return cli_read_line();
}

/** Saisie masquée (mot de passe). */
function cli_ask_hidden(string $question): string
{
    fwrite(STDOUT, $question);
    if (!cli_is_tty()) {
        return cli_read_line(); // entrée redirigée : rien ne s'affiche de toute façon
    }
    if (!cli_disable_echo()) {
        fwrite(STDERR, "\nImpossible de masquer la saisie sur ce terminal (stty indisponible).\n");
        $answer = cli_ask('Continuer avec une saisie VISIBLE ? (o/N) ');
        if (!in_array(mb_strtolower(trim($answer)), ['o', 'oui'], true)) {
            fwrite(STDERR, "Abandon : aucun changement n'a été enregistré.\n");
            exit(1);
        }
        fwrite(STDOUT, $question);
        return cli_read_line();
    }
    if (!$GLOBALS['cli_signals']) {
        // Sans l'extension pcntl, Ctrl+C ne peut pas être intercepté.
        fwrite(STDERR, "\n(Si vous interrompez la saisie avec Ctrl+C, tapez « stty echo » puis Entrée pour réafficher le texte.)\n");
        fwrite(STDOUT, $question);
    }
    try {
        return cli_read_line();
    } finally {
        cli_restore_echo();
    }
}

function cli_confirm(string $question): bool
{
    return in_array(mb_strtolower(trim(cli_ask($question . ' (o/N) '))), ['o', 'oui'], true);
}

function cli_fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

// -----------------------------------------------------------------------------
//  Programme
// -----------------------------------------------------------------------------
$interactive = cli_is_tty();
$attempts    = $interactive ? 3 : 1; // sans terminal : pas de nouvelle tentative

try {
    $count = Admin::count();
} catch (PDOException $e) {
    cli_fail("Connexion à la base de données impossible : vérifiez la section db de app/config.php\n"
        . 'et que database.sql a été importé. Détail : ' . $e->getMessage());
}

fwrite(STDOUT, $reset
    ? "Réinitialisation du mot de passe d'un compte administrateur.\n"
    : "Création d'un compte administrateur (" . $count . " compte(s) existant(s)).\n");

// 1. Identifiant
$admin = null;
for ($i = 1; ; $i++) {
    $username = trim(cli_ask('Identifiant : '));
    if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
        $problem = 'Identifiant invalide : 3 à 50 caractères, lettres sans accent, chiffres, point, tiret ou tiret bas.';
    } else {
        $admin = Admin::findByUsername($username); // comparaison insensible à la casse (collation)
        if ($reset && $admin === null) {
            $problem = 'Aucun compte ne porte cet identifiant.';
        } elseif (!$reset && $admin !== null) {
            $problem = 'Ce compte existe déjà : relancez avec --reset pour changer son mot de passe.';
        } else {
            break;
        }
    }
    fwrite(STDERR, $problem . "\n");
    if ($i >= $attempts) {
        exit(1);
    }
}

if (!$reset && $count > 0 && !cli_confirm('Un administrateur existe déjà. Créer un compte supplémentaire ?')) {
    cli_fail("Abandon : aucun compte créé.");
}

// 2. Mot de passe + confirmation
for ($i = 1; ; $i++) {
    $password     = cli_ask_hidden('Mot de passe (12 caractères minimum, saisie masquée) : ');
    $confirmation = cli_ask_hidden('Confirmation : ');
    $problem = Admin::passwordProblem($password, $username);
    if ($problem === null && !hash_equals($password, $confirmation)) {
        $problem = 'Les deux mots de passe ne sont pas identiques.';
    }
    if ($problem === null) {
        break;
    }
    fwrite(STDERR, $problem . "\n");
    if ($i >= $attempts) {
        exit(1);
    }
}

// 3. Enregistrement
if ($reset) {
    $id = (int) $admin['id'];
    Admin::updatePassword($id, $password);
    fwrite(STDOUT, 'Mot de passe du compte « ' . $admin['username'] . " » mis à jour.\n");

    if ($disable2fa) {
        Admin::disableTotp($id);
        fwrite(STDOUT, "Double authentification désactivée (codes de secours supprimés) : réactivez-la dans « Mon compte ».\n");
    }

    // Un mot de passe réinitialisé = toutes les sessions ouvertes sont fermées :
    // la version de session du compte change (efficace même si les sessions
    // sont stockées ailleurs), et les fichiers de session locaux sont supprimés.
    try {
        Admin::bumpSessionVersion($id);
    } catch (\Throwable) {
        // Base pas encore migrée (colonne session_version absente) : la
        // suppression des fichiers ci-dessous suffit.
    }
    $closed = 0;
    foreach (glob(STORAGE_PATH . '/sessions/sess_*') ?: [] as $file) {
        if (preg_match('/^sess_[A-Za-z0-9,-]+$/', basename($file)) && @unlink($file)) {
            $closed++;
        }
    }
    fwrite(STDOUT, $closed . " session(s) fermée(s) : reconnectez-vous sur " . abs_url('/admin/connexion') . "\n");
} else {
    $id = Admin::create($username, $password);
    fwrite(STDOUT, 'Compte « ' . $username . ' » créé (n° ' . $id . ").\n");
    fwrite(STDOUT, 'Connectez-vous sur ' . abs_url('/admin/connexion') . ", puis activez la double authentification (Mon compte).\n");
    if (is_file(PUBLIC_PATH . '/setup-admin.php')) {
        fwrite(STDOUT, "Rappel : supprimez public_html/setup-admin.php et videz setup_token dans app/config.php.\n");
    }
}
exit(0);
