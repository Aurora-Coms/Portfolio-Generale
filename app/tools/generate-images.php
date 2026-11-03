<?php
/**
 * Génère les images fixes du site (LIGNE DE COMMANDE UNIQUEMENT) :
 *   - public_html/assets/img/favicon-32.png        (32 x 32)
 *   - public_html/assets/img/apple-touch-icon.png  (180 x 180)
 *   - public_html/assets/img/og-default.png        (1200 x 630, aperçu LinkedIn)
 *
 *   php app/tools/generate-images.php
 *
 * Nécessite l'extension GD avec FreeType. Le favicon vectoriel
 * (favicon.svg) est écrit à la main : les PNG reprennent le même dessin.
 * Tout est dessiné en résolution double puis réduit (anticrénelage).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!function_exists('imagettftext')) {
    fwrite(STDERR, "Extension GD avec FreeType requise.\n");
    exit(1);
}

$out = dirname(__DIR__, 2) . '/public_html/assets/img';

$fonts = [
    'bold'    => '/usr/share/fonts/truetype/jetbrains-mono/JetBrainsMono-Bold.ttf',
    'regular' => '/usr/share/fonts/truetype/jetbrains-mono/JetBrainsMono-Regular.ttf',
    'sans'    => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
];
foreach ($fonts as $key => $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "Police introuvable : $file\n");
        exit(1);
    }
}

// Jetons de couleur (identiques à site.css).
const C_BG      = 0x13141B;
const C_CHROME  = 0x0F1016;
const C_PANEL   = 0x1A1C25;
const C_PANEL2  = 0x15161E;
const C_BORDER  = 0x272A37;
const C_TEXT    = 0xD5D8E4;
const C_STRONG  = 0xF1F2F7;
const C_SOFT    = 0xABB0C4;
const C_MUTED   = 0x8C91A8;
const C_COMMENT = 0x7D839B;
const C_LINENO  = 0x656B82;
const C_ACCENT  = 0xC3A6FF;
const C_STRING  = 0x9FE0A3;
const C_NUMBER  = 0xF5A66B;

/** Couleur GD à partir d'un entier 0xRRGGBB. */
function col(\GdImage $im, int $rgb): int
{
    return (int) imagecolorallocate($im, ($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255);
}

/** Rectangle plein à coins arrondis. */
function roundedRect(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
{
    $r = max(0, min($r, intdiv($x2 - $x1, 2), intdiv($y2 - $y1, 2)));
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
        imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $color);
    }
}

/** Cadre arrondi d'épaisseur $w (rectangle extérieur couleur, intérieur rempli). */
function roundedFrame(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $w, int $stroke, int $fill): void
{
    roundedRect($im, $x1, $y1, $x2, $y2, $r, $stroke);
    roundedRect($im, $x1 + $w, $y1 + $w, $x2 - $w, $y2 - $w, max(0, $r - $w), $fill);
}

/** Trait épais à extrémités rondes. */
function thickLine(\GdImage $im, float $xa, float $ya, float $xb, float $yb, int $w, int $color): void
{
    $len = hypot($xb - $xa, $yb - $ya);
    if ($len > 0) {
        $nx = -($yb - $ya) / $len * $w / 2;
        $ny = ($xb - $xa) / $len * $w / 2;
        imagefilledpolygon($im, [
            (int) round($xa + $nx), (int) round($ya + $ny),
            (int) round($xb + $nx), (int) round($yb + $ny),
            (int) round($xb - $nx), (int) round($yb - $ny),
            (int) round($xa - $nx), (int) round($ya - $ny),
        ], $color);
    }
    imagefilledellipse($im, (int) round($xa), (int) round($ya), $w, $w, $color);
    imagefilledellipse($im, (int) round($xb), (int) round($yb), $w, $w, $color);
}

/**
 * Symbole « >_ » dans un carré à bordure violette (même dessin que favicon.svg,
 * repère 64 x 64), placé en ($x, $y) avec une taille de $size pixels.
 */
function drawLogo(\GdImage $im, int $x, int $y, int $size, bool $withBackground): void
{
    $k = $size / 64;
    $p = static fn (float $v): int => (int) round($v * $k);
    $accent = col($im, C_ACCENT);
    if ($withBackground) {
        roundedRect($im, $x, $y, $x + $size - 1, $y + $size - 1, $p(14), col($im, C_CHROME));
    }
    roundedFrame($im, $x + $p(5), $y + $p(5), $x + $p(59), $y + $p(59), $p(11), max(2, $p(4)), $accent, col($im, C_BG));
    $w = max(2, $p(5));
    thickLine($im, $x + 19 * $k, $y + 23 * $k, $x + 29 * $k, $y + 32 * $k, $w, $accent);
    thickLine($im, $x + 29 * $k, $y + 32 * $k, $x + 19 * $k, $y + 41 * $k, $w, $accent);
    thickLine($im, $x + 33 * $k, $y + 42 * $k, $x + 46 * $k, $y + 42 * $k, $w, $accent);
}

/** Crée une image en double résolution, appelle $draw, réduit puis enregistre. */
function render(string $file, int $width, int $height, callable $draw, int $scale = 2): void
{
    $big = imagecreatetruecolor($width * $scale, $height * $scale);
    imagealphablending($big, true);
    imageantialias($big, true);
    $draw($big, $scale);

    $final = imagecreatetruecolor($width, $height);
    imagecopyresampled($final, $big, 0, 0, 0, 0, $width, $height, $width * $scale, $height * $scale);
    imagepng($final, $file, 9);
    echo 'Écrit : ' . $file . ' (' . $width . 'x' . $height . ")\n";
}

/** Texte TTF ; renvoie la largeur dessinée. */
function text(\GdImage $im, string $font, float $size, int $x, int $baseline, int $color, string $str): int
{
    $box = imagettftext($im, $size, 0, $x, $baseline, $color, $font, $str);
    return $box !== false ? $box[2] - $box[0] : 0;
}

// --- Icônes ------------------------------------------------------------------
render($out . '/favicon-32.png', 32, 32, static function (\GdImage $im, int $s): void {
    imagefill($im, 0, 0, col($im, C_CHROME));
    drawLogo($im, 0, 0, 32 * $s, false);
}, 8);

render($out . '/apple-touch-icon.png', 180, 180, static function (\GdImage $im, int $s): void {
    // iOS arrondit lui-même les coins : fond plein, logo centré avec marge.
    imagefill($im, 0, 0, col($im, C_CHROME));
    $size = 150 * $s;
    drawLogo($im, 15 * $s, 15 * $s, $size, false);
}, 4);

// --- Image d'aperçu Open Graph (LinkedIn) ------------------------------------
render($out . '/og-default.png', 1200, 630, static function (\GdImage $im, int $s) use ($fonts): void {
    $px = static fn (float $v): int => (int) round($v * $s);

    imagefill($im, 0, 0, col($im, C_BG));

    // Barre de titre / onglets de fichier.
    imagefilledrectangle($im, 0, 0, $px(1200), $px(52), col($im, C_CHROME));
    imagefilledrectangle($im, 0, $px(52), $px(1200), $px(53), col($im, C_BORDER));
    foreach ([0xFF8F8F, 0xF5D76B, 0x9FE0A3] as $i => $dot) {
        imagefilledellipse($im, $px(30 + $i * 22), $px(26), $px(12), $px(12), col($im, $dot));
    }
    imagefilledrectangle($im, $px(110), $px(10), $px(290), $px(53), col($im, C_BG));
    imagefilledrectangle($im, $px(110), $px(10), $px(290), $px(12), col($im, C_ACCENT));
    text($im, $fonts['regular'], 15 * $s, $px(132), $px(38), col($im, C_STRONG), 'portfolio.lua');
    text($im, $fonts['regular'], 15 * $s, $px(316), $px(38), col($im, C_MUTED), 'projets/');

    // Gouttière des numéros de ligne.
    imagefilledrectangle($im, 0, $px(53), $px(64), $px(588), col($im, C_PANEL2));
    for ($n = 1; $n <= 11; $n++) {
        $label = (string) $n;
        $w = (int) (imagettfbbox(14 * $s, 0, $fonts['regular'], $label)[2] ?? 0);
        text($im, $fonts['regular'], 14 * $s, $px(46) - $w, $px(110 + ($n - 1) * 44), col($im, C_LINENO), $label);
    }

    // Logo + commentaire.
    drawLogo($im, $px(104), $px(92), $px(104), false);
    text($im, $fonts['regular'], 22 * $s, $px(236), $px(134), col($im, C_COMMENT), '-- portfolio');
    text($im, $fonts['regular'], 22 * $s, $px(236), $px(176), col($im, C_MUTED), 'BTS CIEL · IR · Avignon');

    // Nom (gros, mono gras).
    text($im, $fonts['bold'], 68 * $s, $px(100), $px(306), col($im, C_STRONG), 'Alexandre Derbez');

    // Sous-titre (accent).
    text($im, $fonts['bold'], 27 * $s, $px(102), $px(372), col($im, C_ACCENT), 'Étudiant BTS CIEL option IR');
    text($im, $fonts['regular'], 27 * $s, $px(102), $px(416), col($im, C_TEXT), 'Cybersécurité & Réseaux');

    // Recherche de stage : pastille façon code.
    roundedRect($im, $px(100), $px(456), $px(1100), $px(524), $px(10), col($im, C_PANEL));
    imagefilledrectangle($im, $px(100), $px(462), $px(104), $px(518), col($im, C_STRING));
    $x = $px(128);
    $x += text($im, $fonts['bold'], 22 * $s, $x, $px(500), col($im, C_STRONG), 'Recherche de stage');
    $x += text($im, $fonts['regular'], 22 * $s, $x, $px(500), col($im, C_COMMENT), ' : ');
    text($im, $fonts['regular'], 22 * $s, $x, $px(500), col($im, C_STRING), '18 mai au 26 juin 2026');

    // Barre d'état.
    imagefilledrectangle($im, 0, $px(588), $px(1200), $px(630), col($im, C_CHROME));
    imagefilledrectangle($im, 0, $px(588), $px(1200), $px(589), col($im, C_BORDER));
    $x = $px(30);
    $x += text($im, $fonts['regular'], 15 * $s, $x, $px(615), col($im, C_STRING), '● disponible · stage de mai à juin 2026');
    $x += $px(36);
    $x += text($im, $fonts['regular'], 15 * $s, $x, $px(615), col($im, C_MUTED), 'Lua');
    $x += $px(28);
    text($im, $fonts['regular'], 15 * $s, $x, $px(615), col($im, C_MUTED), 'UTF-8');
    $right = 'Réseaux · Systèmes · Roblox Studio';
    $w = (int) (imagettfbbox(15 * $s, 0, $fonts['regular'], $right)[2] ?? 0);
    text($im, $fonts['regular'], 15 * $s, $px(1170) - $w, $px(615), col($im, C_MUTED), $right);
}, 2);
