<?php
/**
 * Tests unitaires du noyau (CLI) : php tests/core-test.php
 * Nécessite la base locale (app/config.php en mode development).
 */

declare(strict_types=1);

define('PUBLIC_PATH', dirname(__DIR__) . '/public_html');
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

use App\Core\Crypto;
use App\Core\Markdown;
use App\Core\Slug;
use App\Core\Throttle;
use App\Core\Validator;

$failures = 0;
$count = 0;
function check(string $label, bool $ok): void
{
    global $failures, $count;
    $count++;
    if (!$ok) {
        $failures++;
        echo "ÉCHEC : {$label}\n";
    }
}

// --- Échappement ---------------------------------------------------------
check('e() échappe < > " \' &', e('<script>"\'&') === '&lt;script&gt;&quot;&apos;&amp;');

// --- Markdown sûr ----------------------------------------------------------
$xss = Markdown::render('<img src=x onerror=alert(1)> **gras** [x](javascript:alert(1)) [ok](https://exemple.fr/?a=1&b=2)');
check('Markdown : aucune balise img', !str_contains($xss, '<img'));
check('Markdown : gras', str_contains($xss, '<strong>gras</strong>'));
check('Markdown : lien javascript refusé', !str_contains($xss, 'href="javascript'));
check('Markdown : lien https accepté', str_contains($xss, '<a href="https://exemple.fr/?a=1&amp;b=2" rel="noopener noreferrer">ok</a>'));
check('Markdown : liste', Markdown::render("- un\n- deux") === '<ul><li>un</li><li>deux</li></ul>');
check('Markdown : liste numérotée', Markdown::render("1. un\n2. deux") === '<ol><li>un</li><li>deux</li></ol>');
check('Markdown : titre h3', Markdown::render('### Titre') === '<h3>Titre</h3>');
check('Markdown : code en ligne non interprété', Markdown::render('`**x**`') === '<p><code>**x**</code></p>');
check('Markdown : bloc de code', str_contains(Markdown::render("```lua\nlocal x = \"<b>\"\n```"), '<pre><code>local x = &quot;&lt;b&gt;&quot;</code></pre>'));
check('Markdown : paragraphes + br', Markdown::render("a\nb\n\nc") === "<p>a<br>b</p>\n<p>c</p>");
check('Markdown : attribut cassé impossible', !str_contains(Markdown::render('[x](https://a.fr/"onmouseover="alert(1))'), '"onmouseover'));
check('Markdown : protocol-relative refusé', !str_contains(Markdown::render('[x](//evil.com)'), 'href'));
check('Markdown : italique', Markdown::render('un *mot* ici') === '<p>un <em>mot</em> ici</p>');

check('Markdown : lien « /\\domaine » non lié', !str_contains(Markdown::render('[x](/\\evil.com)'), 'href'));
check('Markdown : lien interne sans rel', Markdown::render('[ok](/contact)') === '<p><a href="/contact">ok</a></p>');
check('Markdown : lien avec tabulation refusé', !str_contains(Markdown::render("[x](/\t/evil.com)"), 'href'));

// --- Slug --------------------------------------------------------------------
check('Slug accents', Slug::make('Portail du TVA') === 'portail-du-tva');
check('Slug spéciaux', Slug::make('  Sci-fi & temporel !! ') === 'sci-fi-temporel');
check('Slug é è ç', Slug::make('Création d\'un réel OS') === 'creation-d-un-reel-os');

// --- Validator ---------------------------------------------------------------
$v = new Validator(['t' => "  Bonjour\x00 ", 'e' => 'pas-un-mail', 'n' => '7', 'u' => 'javascript:alert(1)',
    'y' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10', 'y2' => 'https://youtu.be/dQw4w9WgXcQ', 'd' => '2026-02-30',
    'ids' => ['1', '2', 'x', '0', '2'], 'b' => '1']);
check('Validator string nettoyé', $v->string('t', 'T') === 'Bonjour');
check('Validator email invalide', $v->email('e', 'E') === null);
check('Validator int hors bornes', $v->int('n', 'N', min: 1, max: 5) === null);
check('Validator url javascript refusée', $v->url('u', 'U') === null);
check('Validator youtube watch', $v->youtubeId('y', 'Y') === 'dQw4w9WgXcQ');
check('Validator youtube court', $v->youtubeId('y2', 'Y') === 'dQw4w9WgXcQ');
check('Validator date invalide', $v->date('d', 'D') === null);
check('Validator ids', $v->ids('ids') === [1, 2]);
check('Validator bool', $v->bool('b') && !$v->bool('absent'));
check('Validator erreurs', count($v->errors()) === 4);

// --- Crypto ------------------------------------------------------------------
$enc = Crypto::encrypt('JBSWY3DPEHPK3PXP', 'totp');
check('Crypto aller-retour', Crypto::decrypt($enc, 'totp') === 'JBSWY3DPEHPK3PXP');
check('Crypto mauvais contexte', Crypto::decrypt($enc, 'autre') === null);
$raw = base64_decode($enc);
$raw[20] = chr(ord($raw[20]) ^ 1);
check('Crypto message altéré refusé', Crypto::decrypt(base64_encode($raw), 'totp') === null);

// --- Throttle (base de données) ---------------------------------------------
Throttle::clear('test', '1.2.3.4');
for ($i = 0; $i < 3; $i++) {
    Throttle::hit('test', '1.2.3.4');
}
check('Throttle compte', Throttle::count('test', '1.2.3.4', 60) === 3);
check('Throttle tooMany', Throttle::tooMany('test', '1.2.3.4', 3, 60) && !Throttle::tooMany('test', '1.2.3.4', 4, 60));
check('Throttle clé hachée', strlen(Throttle::hash('test', '1.2.3.4')) === 64);
Throttle::clear('test', '1.2.3.4');
check('Throttle clear', Throttle::count('test', '1.2.3.4', 60) === 0);

// --- Dates -------------------------------------------------------------------
check('date_fr jour', date_fr('2026-05-18') === '18 mai 2026');
check('date_fr 1er', date_fr('2026-05-01') === '1er mai 2026');
check('period_fr en cours', period_fr('2025-09-01', null, 'year') === '2025 → en cours');
check('str_limit', str_limit('Un texte assez long pour être coupé', 15) === 'Un texte assez…');

echo $failures === 0 ? "OK : {$count} tests réussis.\n" : "{$failures} échec(s) sur {$count}.\n";
exit($failures === 0 ? 0 : 1);
