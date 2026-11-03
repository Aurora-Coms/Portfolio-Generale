<?php
/**
 * Tests de la double authentification (TOTP) et du générateur de QR code (CLI) :
 *     php tests/totp-test.php
 *
 * Décodage réel des QR codes (recommandé) : le test rend chaque matrice en PNG
 * avec GD puis la fait relire par tests/qr-decode.py (zxing-cpp et OpenCV).
 * Installation des décodeurs dans un dossier dédié, une seule fois :
 *     pip install --target /tmp/claude-0/qrtest zxing-cpp opencv-python-headless
 * Variables d'environnement facultatives :
 *     QR_DECODER_PATH  dossier des décodeurs (défaut : /tmp/claude-0/qrtest)
 *     PYTHON           interpréteur Python (défaut : python3)
 * Si les décodeurs sont absents, le test l'indique (« décodage non vérifié »)
 * et vérifie tout le reste.
 */

declare(strict_types=1);

define('PUBLIC_PATH', dirname(__DIR__) . '/public_html');
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

use App\Core\QrCode;
use App\Core\Totp;

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

// --- Base32 (vecteurs RFC 4648 §10) ------------------------------------------
$vectors = ['' => '', 'f' => 'MY', 'fo' => 'MZXQ', 'foo' => 'MZXW6', 'foob' => 'MZXW6YQ',
    'fooba' => 'MZXW6YTB', 'foobar' => 'MZXW6YTBOI'];
foreach ($vectors as $plain => $encoded) {
    check("base32Encode('{$plain}')", Totp::base32Encode((string) $plain) === $encoded);
    if ($plain !== '') {
        check("base32Decode('{$encoded}')", Totp::base32Decode($encoded) === (string) $plain);
    }
}
check('base32Decode avec remplissage', Totp::base32Decode('MZXW6YQ=') === 'foob');
check('base32Decode minuscules et espaces', Totp::base32Decode(' mzxw 6ytb oi ') === 'foobar');
check('base32Decode caractère invalide', Totp::base32Decode('MZXW1') === null);
check('base32Decode symboles', Totp::base32Decode('!!!!') === null);
check('base32Decode longueur impossible', Totp::base32Decode('MZX') === null);
check('base32Decode vide', Totp::base32Decode('') === null);
check('base32Decode bits de fin non nuls', Totp::base32Decode('MZ') === null);
for ($i = 1; $i <= 40; $i++) {
    $bytes = random_bytes($i);
    if (Totp::base32Decode(Totp::base32Encode($bytes)) !== $bytes) {
        check("base32 aller-retour ({$i} octets)", false);
    }
}
check('base32 aller-retour aléatoire', true);

$s1 = Totp::generateSecret();
$s2 = Totp::generateSecret();
check('generateSecret : 32 caractères base32', preg_match('/^[A-Z2-7]{32}$/', $s1) === 1);
check('generateSecret : 20 octets', strlen((string) Totp::base32Decode($s1)) === 20);
check('generateSecret : aléatoire', $s1 !== $s2);

// --- HOTP (vecteurs RFC 4226 annexe D) et TOTP (RFC 6238 annexe B, SHA1) ------
$rfcSecret = Totp::base32Encode('12345678901234567890');
$hotp = ['755224', '287082', '359152', '969429', '338314', '254676', '287922', '162583', '399871', '520489'];
foreach ($hotp as $counter => $expected) {
    check("HOTP RFC 4226 compteur {$counter}", Totp::code($rfcSecret, $counter) === $expected);
}
$totp = [59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471',
    1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'];
foreach ($totp as $time => $expected8) {
    $step = Totp::currentStep($time);
    check("TOTP RFC 6238 T={$time}", Totp::code($rfcSecret, $step) === substr($expected8, -6));
}
check('currentStep pas de 30 s', Totp::currentStep(59) === 1 && Totp::currentStep(60) === 2);
$thrown = false;
try {
    Totp::code('pas-du-base32!', 1);
} catch (InvalidArgumentException) {
    $thrown = true;
}
check('code() refuse un secret invalide', $thrown);

// --- Vérification, fenêtre et anti-rejeu --------------------------------------
// Évite de franchir une limite de pas pendant les vérifications.
if (time() % 30 >= 27) {
    sleep(31 - time() % 30);
}
$secret = Totp::generateSecret();
$now = Totp::currentStep();
check('verify code courant', Totp::verify($secret, Totp::code($secret, $now)) === $now);
check('verify pas précédent (dérive)', Totp::verify($secret, Totp::code($secret, $now - 1)) === $now - 1);
check('verify pas suivant (dérive)', Totp::verify($secret, Totp::code($secret, $now + 1)) === $now + 1);
check('verify hors fenêtre (-2)', Totp::verify($secret, Totp::code($secret, $now - 2)) === null);
check('verify hors fenêtre (+2)', Totp::verify($secret, Totp::code($secret, $now + 2)) === null);
check('verify fenêtre 0', Totp::verify($secret, Totp::code($secret, $now - 1), null, 0) === null);
check('verify fenêtre 2', Totp::verify($secret, Totp::code($secret, $now - 2), null, 2) === $now - 2);
check('anti-rejeu : même pas refusé', Totp::verify($secret, Totp::code($secret, $now), $now) === null);
check('anti-rejeu : pas plus ancien refusé', Totp::verify($secret, Totp::code($secret, $now - 1), $now - 1) === null);
check('anti-rejeu : pas suivant accepté', Totp::verify($secret, Totp::code($secret, $now), $now - 1) === $now);
check('anti-rejeu : pas futur après usage', Totp::verify($secret, Totp::code($secret, $now + 1), $now) === $now + 1);
check('verify secret en minuscules', Totp::verify(strtolower($secret), Totp::code($secret, $now)) === $now);
$good = Totp::code($secret, $now);
$wrong = str_pad((string) (((int) $good + 1) % 1000000), 6, '0', STR_PAD_LEFT);
check('verify mauvais code', Totp::verify($secret, $wrong) === null);
foreach (['', '12345', '1234567', '12345a', ' ' . $good, $good . "\n", $good . ' ', '-12345', '+12345',
    '١٢٣٤٥٦', '12 345', '0x1234'] as $bad) {
    check('verify rejette ' . json_encode($bad), Totp::verify($secret, $bad) === null);
}
check('verify secret invalide', Totp::verify('pas-du-base32!', $good) === null);
check('verify secret vide', Totp::verify('', $good) === null);

check('provisioningUri', Totp::provisioningUri('JBSWY3DPEHPK3PXP', 'admin@exemple.fr', 'Portfolio Alexandre Derbez')
    === 'otpauth://totp/Portfolio%20Alexandre%20Derbez:admin%40exemple.fr?secret=JBSWY3DPEHPK3PXP'
    . '&issuer=Portfolio%20Alexandre%20Derbez&algorithm=SHA1&digits=6&period=30');

// --- QR code : versions et capacités (mode octet, niveau M) ------------------
// Capacités officielles (ISO/IEC 18004, tableau 7) en octets, niveau M.
$capacityM = [1 => 14, 26, 42, 62, 84, 106, 122, 152, 180, 213, 251, 287, 331, 362, 412, 450, 504, 560, 624, 666,
    711, 779, 857, 911, 997, 1059, 1125, 1190, 1264, 1370, 1452, 1538, 1628, 1722, 1809, 1911, 1989, 2099, 2213, 2331];
$cases = [];
foreach ($capacityM as $v => $cap) {
    $data = substr(str_repeat('otpauth://totp/Portfolio:admin?secret=ABCDEFGHIJKLMNOP&x=', 50), 0, $cap);
    $m = QrCode::matrix($data);
    if (count($m) !== 4 * $v + 17 || count($m[0]) !== 4 * $v + 17) {
        check("QR version {$v} pour {$cap} octets", false);
    }
    if ($v < 40 && count(QrCode::matrix($data . 'x')) !== 4 * ($v + 1) + 17) {
        check("QR version " . ($v + 1) . ' pour ' . ($cap + 1) . ' octets', false);
    }
    $cases["capacité max version {$v}"] = $data;
}
check('QR versions 1 à 40 conformes aux capacités officielles', true);
$thrown = false;
try {
    QrCode::matrix(str_repeat('a', 2332));
} catch (InvalidArgumentException) {
    $thrown = true;
}
check('QR données trop longues : exception', $thrown);

$uri = Totp::provisioningUri(Totp::generateSecret(), 'admin', 'Portfolio Alexandre Derbez');
$cases = [
    'courte' => 'A',
    'url' => 'https://alexandre-derbez.fr/projets',
    'URI otpauth réaliste' => $uri,
    '~150 octets' => substr(str_repeat('Stage BTS CIEL IR du 18 mai au 26 juin 2026 · ', 4), 0, 150),
    'accents UTF-8' => 'Électronique, réseaux & sécurité : déjà vu ? Ça marche ! « guillemets » 2FA ✓ 🚲',
    'binaire' => "\x00\x01\x02\xFF\xFE" . 'fin',
] + $cases;
// Chaîne vide : version 1 valide (les décodeurs ignorent un contenu vide, d'où l'absence de décodage).
check('QR chaîne vide : version 1', count(QrCode::matrix('')) === 21);
check('QR URI otpauth tient en version ≤ 10', count(QrCode::matrix($uri)) <= 57);

// --- SVG -----------------------------------------------------------------------
$svg = QrCode::svg($uri, 220);
check('SVG sans « style »', stripos($svg, 'style') === false);
check('SVG sans « <script »', stripos($svg, '<script') === false);
check('SVG un seul <path>', substr_count($svg, '<path') === 1);
check('SVG fond clair', str_contains($svg, '<rect') && str_contains($svg, 'fill="#FFFFFF"'));
check('SVG modules sombres', str_contains($svg, 'fill="#13141B"'));
check('SVG crispEdges', str_contains($svg, 'shape-rendering="crispEdges"'));
check('SVG accessible', str_contains($svg, 'role="img"')
    && str_contains($svg, 'aria-label="QR code de configuration de la double authentification"'));
check('SVG taille', str_contains($svg, 'width="220" height="220"'));
$xml = simplexml_load_string($svg);
check('SVG XML valide', $xml !== false);
// Le chemin redonne exactement la matrice (zone de silence de 4 modules comprise).
$matrix = QrCode::matrix($uri);
$n = count($matrix);
check('SVG viewBox avec zone de silence', str_contains($svg, 'viewBox="0 0 ' . ($n + 8) . ' ' . ($n + 8) . '"'));
$rebuilt = array_fill(0, $n, array_fill(0, $n, false));
$pathOk = true;
if ($xml !== false && preg_match_all('/M(\d+) (\d+)h(\d+)v1h-(\d+)z/', (string) $xml->path['d'], $segs, PREG_SET_ORDER)) {
    foreach ($segs as [, $x, $y, $w, $w2]) {
        if ($w !== $w2) {
            $pathOk = false;
        }
        for ($i = 0; $i < (int) $w; $i++) {
            $rebuilt[(int) $y - 4][(int) $x - 4 + $i] = true;
        }
    }
} else {
    $pathOk = false;
}
check('SVG chemin fidèle à la matrice', $pathOk && $rebuilt === $matrix);

// --- QR code : décodage réel ---------------------------------------------------
/** Rend la matrice en PNG : modules de 8 px, zone de silence de 4 modules. */
function qr_png(array $matrix, string $file): void
{
    $scale = 8;
    $n = count($matrix);
    $px = ($n + 8) * $scale;
    $img = imagecreatetruecolor($px, $px);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0x13, 0x14, 0x1B);
    imagefilledrectangle($img, 0, 0, $px - 1, $px - 1, $white);
    foreach ($matrix as $y => $row) {
        foreach ($row as $x => $dark) {
            if ($dark) {
                imagefilledrectangle($img, ($x + 4) * $scale, ($y + 4) * $scale,
                    ($x + 5) * $scale - 1, ($y + 5) * $scale - 1, $black);
            }
        }
    }
    imagepng($img, $file);
    imagedestroy($img);
}

$tmp = sys_get_temp_dir() . '/qr-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0700);
$files = [];
foreach (array_keys($cases) as $i => $label) {
    $files[$label] = "{$tmp}/qr-{$i}.png";
    qr_png(QrCode::matrix($cases[$label]), $files[$label]);
}

$decoderPath = getenv('QR_DECODER_PATH') ?: '/tmp/claude-0/qrtest';
$python = getenv('PYTHON') ?: 'python3';
$cmd = 'QR_DECODER_PATH=' . escapeshellarg($decoderPath) . ' ' . escapeshellarg($python) . ' -I '
    . escapeshellarg(__DIR__ . '/qr-decode.py') . ' ' . implode(' ', array_map('escapeshellarg', $files)) . ' 2>/dev/null';
$out = shell_exec($cmd);
$report = is_string($out) ? json_decode($out, true) : null;

if (!is_array($report) || !isset($report['results'])) {
    echo "AVERTISSEMENT : décodage non vérifié (décodeurs Python absents, voir l'en-tête du fichier).\n";
} else {
    $labels = array_keys($files);
    $zxing = in_array('zxing-cpp', $report['decoders'], true);
    $cvOk = 0;
    $cvTotal = 0;
    foreach ($report['results'] as $i => $res) {
        $label = $labels[$i];
        $expected = bin2hex($cases[$label]);
        if ($zxing) {
            check("décodage zxing-cpp : {$label}", ($res['zxing-cpp'] ?? null) === $expected);
            check("niveau de correction M : {$label}", ($res['zxing-ec'] ?? null) === 'M');
        }
        // OpenCV : second décodeur indépendant, exigé sur les cas « réalistes »
        // (texte UTF-8 valide, versions ≤ 10) ; simple statistique au-delà.
        $realistic = !str_starts_with($label, 'capacité') && mb_check_encoding($cases[$label], 'UTF-8');
        if ($realistic) {
            check("décodage OpenCV : {$label}", ($res['opencv'] ?? null) === $expected);
        } else {
            $cvTotal++;
            $cvOk += ($res['opencv'] ?? null) === $expected ? 1 : 0;
        }
    }
    echo 'Décodage réel : ' . count($report['results']) . ' QR codes, décodeurs : ' . implode(', ', $report['decoders'])
        . " (OpenCV sur les cas de capacité maximale : {$cvOk}/{$cvTotal}).\n";
}
array_map('unlink', $files);
rmdir($tmp);

echo $failures === 0 ? "OK : {$count} tests réussis.\n" : "{$failures} échec(s) sur {$count}.\n";
exit($failures === 0 ? 0 : 1);
