<?php
/**
 * Tests de sécurité de l'authentification (CLI) : php tests/auth-security-test.php
 *
 * Rejoue les attaques signalées par la revue de sécurité :
 *  - requêtes de connexion et de 2FA envoyées EN PARALLÈLE (la limite doit tenir) ;
 *  - variantes Unicode de l'identifiant (ádmin, ａｄｍｉｎ, caractère invisible…) ;
 *  - temps de réponse compte connu / inconnu ;
 *  - blocage du vrai administrateur par un attaquant (cookie d'appareil) ;
 *  - rejeu simultané d'un même code TOTP ;
 *  - révocation des autres sessions après changement de mot de passe.
 *
 * Nécessite l'extension pcntl (tests de concurrence) et une base dont le nom
 * commence par portfolio_test_ (elle est modifiée).
 */

declare(strict_types=1);

define('PUBLIC_PATH', dirname(__DIR__) . '/public_html');
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();
// Sortie mise en tampon : en CLI, la moindre sortie empêcherait d'ouvrir une
// session PHP dans les processus de test.
ob_start();

use App\Core\Auth;
use App\Core\Config;
use App\Core\Crypto;
use App\Core\Database;
use App\Core\Totp;
use App\Models\Admin;

$db = (string) Config::get('db.name');
if (!str_starts_with($db, 'portfolio_test_')) {
    fwrite(STDERR, "Refus : la base « {$db} » ne commence pas par portfolio_test_.\n");
    exit(1);
}
if (!function_exists('pcntl_fork')) {
    fwrite(STDERR, "Extension pcntl absente : tests de concurrence ignorés.\n");
    exit(0);
}

$failures = 0;
$count = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $count;
    $count++;
    if (!$ok) {
        $failures++;
        echo "ÉCHEC : {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    }
}

/** Remet la base dans un état connu (un admin « admin », table throttle vide). */
function resetDb(): int
{
    Database::execute('DELETE FROM throttle');
    Database::execute('DELETE FROM admin_recovery_codes');
    Database::execute('DELETE FROM admins');
    return Admin::create('admin', 'Bon-Mot-De-Passe-2026');
}

/**
 * Lance $n processus qui exécutent $fn au même instant ; renvoie les résultats.
 *
 * @param callable(int): string $fn
 * @return list<string>
 */
function race(int $n, callable $fn): array
{
    $start = microtime(true) + 0.6;
    $dir = sys_get_temp_dir() . '/pf-race-' . getmypid();
    @mkdir($dir);
    $pids = [];
    for ($i = 0; $i < $n; $i++) {
        $pid = pcntl_fork();
        if ($pid === 0) {
            // Enfant : sa propre connexion à la base (rien n'est partagé).
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            ob_start();
            $wait = $start - microtime(true);
            if ($wait > 0) {
                usleep((int) ($wait * 1e6));
            }
            $result = $fn($i);
            file_put_contents("{$dir}/{$i}", $result);
            ob_end_clean();
            exit(0);
        }
        $pids[] = $pid;
    }
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }
    $results = [];
    foreach (glob("{$dir}/*") ?: [] as $file) {
        $results[] = (string) file_get_contents($file);
        unlink($file);
    }
    rmdir($dir);
    return $results;
}

// Le processus parent ne doit PAS ouvrir de connexion avant les fork : chaque
// test prépare la base dans un enfant dédié.
function inChild(callable $fn): string
{
    return race(1, static fn (): string => (string) $fn())[0] ?? '';
}

// --- 1. Connexions parallèles depuis une même IP ------------------------------
inChild(static fn () => resetDb());
$results = race(30, static function (): string {
    $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
    return Auth::attempt('admin', 'mauvais-mot-de-passe');
});
$verified = count(array_filter($results, static fn ($r) => $r === 'invalid'));
check('30 connexions parallèles : au plus 5 mots de passe vérifiés', $verified <= Auth::IP_MAX_FAILS, "{$verified} vérifiés");
check('30 connexions parallèles : les autres sont bloquées', count(array_filter($results, static fn ($r) => $r === 'locked')) >= 25);

// --- 2. Variantes Unicode de l'identifiant ------------------------------------
$variants = inChild(static function (): string {
    resetDb();
    $_SERVER['REMOTE_ADDR'] = '198.51.100.1';
    $out = [];
    foreach (['ádmin', "admin\u{200B}", 'ａｄｍｉｎ', 'ADMİN', 'ªdmin', 'Admin'] as $i => $name) {
        Database::execute('DELETE FROM throttle');
        $out[] = Auth::attempt($name, 'Bon-Mot-De-Passe-2026');
    }
    return implode(',', $out);
});
check('Variantes Unicode de « admin » refusées', $variants === 'invalid,invalid,invalid,invalid,invalid,invalid', $variants);

// --- 3. Temps de réponse : compte connu / inconnu -----------------------------
$timing = inChild(static function (): string {
    resetDb();
    $measure = static function (string $user): float {
        $best = INF;
        for ($i = 0; $i < 3; $i++) {
            Database::execute('DELETE FROM throttle');
            $_SERVER['REMOTE_ADDR'] = '192.0.2.' . random_int(1, 250);
            $t = microtime(true);
            Auth::attempt($user, 'mauvais-mot-de-passe');
            $best = min($best, microtime(true) - $t);
        }
        return $best;
    };
    return $measure('admin') . ',' . $measure('compte-inexistant');
});
[$known, $unknown] = array_map('floatval', explode(',', $timing));
check('Temps de réponse similaire (compte inconnu / connu < 1,5)', $unknown / max($known, 1e-6) < 1.5, sprintf('%.3f s / %.3f s', $unknown, $known));

// --- 4. Un attaquant ne bloque pas le vrai administrateur ----------------------
$dos = inChild(static function (): string {
    resetDb();
    // L'attaquant épuise le compteur du compte depuis plusieurs IP.
    for ($i = 0; $i < Auth::USER_MAX_FAILS + 2; $i++) {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.' . (100 + $i);
        Auth::attempt('admin', 'essai-' . $i);
    }
    $_SERVER['REMOTE_ADDR'] = '198.51.100.50';
    $sansCookie = Auth::attempt('admin', 'Bon-Mot-De-Passe-2026');
    // Appareil déjà reconnu : cookie signé valide (comme après une connexion réussie).
    $payload = Admin::findByUsername('admin')['id'] . '.' . time() . '.' . bin2hex(random_bytes(8));
    $_COOKIE['pf_device'] = $payload . '.' . hash_hmac('sha256', 'device|' . $payload, Config::appKey());
    $avecCookie = Auth::attempt('admin', 'Bon-Mot-De-Passe-2026');
    $_COOKIE['pf_device'] = $payload . '.' . str_repeat('0', 64);
    Database::execute("DELETE FROM throttle WHERE action = 'login_ip'");
    $fauxCookie = Auth::attempt('admin', 'Bon-Mot-De-Passe-2026');
    return "{$sansCookie},{$avecCookie},{$fauxCookie}";
});
check('Compte attaqué : verrou sans appareil reconnu', str_starts_with($dos, 'locked,'), $dos);
check('Compte attaqué : le vrai administrateur (appareil reconnu) se connecte', explode(',', $dos)[1] === 'ok', $dos);
check('Compte attaqué : un cookie d\'appareil falsifié ne passe pas', explode(',', $dos)[2] === 'locked', $dos);

// --- 5. Rejeu simultané d'un même code TOTP -----------------------------------
$secret = Totp::generateSecret();
inChild(static function () use ($secret): string {
    $id = resetDb();
    Admin::enableTotp($id, Crypto::encrypt($secret, 'totp'), 0);
    return '';
});
$code = Totp::code($secret, Totp::currentStep());
$results = race(10, static function () use ($code): string {
    $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
    App\Core\Session::set('_2fa_pending', ['id' => (int) Admin::findByUsername('admin')['id'], 'at' => time()]);
    return Auth::attemptSecondFactor($code);
});
$accepted = count(array_filter($results, static fn ($r) => $r === 'ok'));
// Au plus une fois : si les 10 requêtes arrivent exactement ensemble, la
// limitation peut même toutes les refuser (comportement sûr par défaut).
check('Même code TOTP envoyé 10 fois en parallèle : accepté au plus une fois', $accepted <= 1, "{$accepted} acceptés");

// --- 6. Révocation des autres sessions ----------------------------------------
$revoke = inChild(static function (): string {
    $id = resetDb();
    $_COOKIE['pfsess'] = 'test';
    $_SERVER['HTTP_USER_AGENT'] = 'test-agent';
    App\Core\Session::set('_admin', ['id' => $id, 'auth_at' => time(), 'last_seen' => time(), 'ua' => hash('sha256', 'test-agent'), 'version' => 1]);
    $avant = Auth::user() !== null ? 'connecte' : 'deconnecte';
    Admin::bumpSessionVersion($id); // changement de mot de passe depuis un autre appareil
    $_SESSION['_admin']['last_seen'] = time();
    $reflection = new ReflectionProperty(Auth::class, 'user');
    $reflection->setValue(null, null);
    $apres = Auth::user() !== null ? 'connecte' : 'deconnecte';
    return "{$avant},{$apres}";
});
check('Session ouverte avant un changement de mot de passe : fermée', $revoke === 'connecte,deconnecte', $revoke);

inChild(static fn () => resetDb() > 0 ? Database::execute('DELETE FROM admins') : 0);
echo $failures === 0 ? "OK : {$count} tests réussis.\n" : "{$failures} échec(s) sur {$count}.\n";
ob_end_flush();
exit($failures === 0 ? 0 : 1);
