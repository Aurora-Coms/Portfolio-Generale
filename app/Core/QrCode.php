<?php
/**
 * Générateur de QR code en PHP pur, sans dépendance (norme ISO/IEC 18004).
 *
 * - Mode octet (le texte est encodé tel quel, en UTF-8).
 * - Niveau de correction d'erreur M (environ 15 % de modules récupérables).
 * - Choix automatique de la plus petite version suffisante (1 à 40).
 * - Codes Reed-Solomon sur GF(256) (polynôme primitif 0x11D), entrelacement
 *   des blocs, motifs de position, d'alignement et de synchronisation,
 *   informations de format (BCH 15,5) et de version (BCH 18,6, version ≥ 7).
 * - Les 8 masques sont évalués avec les 4 règles de pénalité ; le meilleur
 *   est retenu.
 *
 * Utilisé pour afficher l'URI otpauth:// de la double authentification :
 * le SVG produit ne contient ni style ni script et peut être inclus en ligne
 * sous une CSP stricte (style-src 'self').
 */

declare(strict_types=1);

namespace App\Core;

final class QrCode
{
    /** Bits du niveau de correction M dans les informations de format. */
    private const ECL_BITS = 0b00;

    /** Codes de correction par bloc, niveau M, index = version. */
    private const ECC_PER_BLOCK = [
        0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26,
        26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28,
    ];

    /** Nombre de blocs de correction, niveau M, index = version. */
    private const NUM_BLOCKS = [
        0, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16,
        17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49,
    ];

    private const COLOR_LIGHT = '#FFFFFF';
    private const COLOR_DARK  = '#13141B';
    private const QUIET_ZONE  = 4;

    /** @var list<int> table exponentielle de GF(256) */
    private static array $exp = [];
    /** @var list<int> table logarithmique de GF(256) */
    private static array $log = [];

    private int $version;
    private int $size;
    /** @var list<list<bool>> modules[ligne][colonne], true = sombre */
    private array $modules = [];
    /** @var list<list<bool>> true = module de fonction (non masquable) */
    private array $isFunction = [];

    /**
     * Matrice du QR code, sans zone de silence.
     *
     * @return list<list<bool>> [ligne][colonne], true = module sombre
     */
    public static function matrix(string $data): array
    {
        return (new self($data))->modules;
    }

    /**
     * SVG autonome : fond clair, zone de silence de 4 modules, un seul <path>
     * pour les modules sombres. Aucune balise <style> ni attribut style.
     */
    public static function svg(string $data, int $size = 220): string
    {
        $matrix = self::matrix($data);
        $n      = count($matrix);
        $total  = $n + 2 * self::QUIET_ZONE;
        $size   = max(21, $size);

        // Modules sombres regroupés par segments horizontaux pour alléger le chemin.
        $d = '';
        foreach ($matrix as $y => $row) {
            $x = 0;
            while ($x < $n) {
                if (!$row[$x]) {
                    $x++;
                    continue;
                }
                $start = $x;
                while ($x < $n && $row[$x]) {
                    $x++;
                }
                $d .= 'M' . ($start + self::QUIET_ZONE) . ' ' . ($y + self::QUIET_ZONE)
                    . 'h' . ($x - $start) . 'v1h-' . ($x - $start) . 'z';
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '"'
            . ' viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges"'
            . ' role="img" aria-label="QR code de configuration de la double authentification">'
            . '<rect width="' . $total . '" height="' . $total . '" fill="' . self::COLOR_LIGHT . '"/>'
            . '<path fill="' . self::COLOR_DARK . '" d="' . $d . '"/>'
            . '</svg>';
    }

    private function __construct(string $data)
    {
        self::initGaloisField();

        $len = strlen($data);
        $this->version = self::chooseVersion($len);
        $this->size    = $this->version * 4 + 17;

        $row = array_fill(0, $this->size, false);
        $this->modules    = array_fill(0, $this->size, $row);
        $this->isFunction = array_fill(0, $this->size, $row);

        $codewords = $this->addEccAndInterleave($this->encodeData($data));

        $this->drawFunctionPatterns();
        $this->drawCodewords($codewords);

        // Évaluation des 8 masques : on garde celui de pénalité minimale.
        $best = 0;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $this->drawFormatBits($mask);
            $penalty = $this->penalty();
            if ($penalty < $bestPenalty) {
                $best = $mask;
                $bestPenalty = $penalty;
            }
            $this->applyMask($mask); // XOR : seconde application = annulation
        }
        $this->applyMask($best);
        $this->drawFormatBits($best);
    }

    // --- Capacité -------------------------------------------------------------

    /** Plus petite version dont la capacité (mode octet, niveau M) suffit. */
    private static function chooseVersion(int $byteLength): int
    {
        for ($v = 1; $v <= 40; $v++) {
            $countBits = $v <= 9 ? 8 : 16;
            $needed    = 4 + $countBits + 8 * $byteLength;
            if ($byteLength < (1 << $countBits) && $needed <= self::dataCodewords($v) * 8) {
                return $v;
            }
        }
        throw new \InvalidArgumentException('Données trop longues pour un QR code.');
    }

    /** Nombre de modules disponibles pour les données et la correction. */
    private static function rawDataModules(int $v): int
    {
        $result = (16 * $v + 128) * $v + 64;
        if ($v >= 2) {
            $numAlign = intdiv($v, 7) + 2;
            $result  -= (25 * $numAlign - 10) * $numAlign - 55;
            if ($v >= 7) {
                $result -= 36;
            }
        }
        return $result;
    }

    private static function dataCodewords(int $v): int
    {
        return intdiv(self::rawDataModules($v), 8) - self::ECC_PER_BLOCK[$v] * self::NUM_BLOCKS[$v];
    }

    // --- Encodage des données -----------------------------------------------

    /** @return list<int> mots de code de données (avec remplissage) */
    private function encodeData(string $data): array
    {
        $bits = '0100'; // indicateur de mode octet
        $bits .= str_pad(decbin(strlen($data)), $this->version <= 9 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $capacity = self::dataCodewords($this->version) * 8;
        $bits .= str_repeat('0', min(4, $capacity - strlen($bits)));     // terminateur
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);           // alignement sur l'octet

        $bytes = [];
        foreach (str_split($bits, 8) as $byte) {
            $bytes[] = bindec($byte);
        }
        for ($pad = 0xEC; count($bytes) < $capacity / 8; $pad ^= 0xEC ^ 0x11) {
            $bytes[] = $pad; // alternance 0xEC / 0x11
        }
        return $bytes;
    }

    /**
     * Découpe en blocs, calcule la correction Reed-Solomon de chacun puis
     * entrelace données et corrections.
     *
     * @param list<int> $data
     * @return list<int>
     */
    private function addEccAndInterleave(array $data): array
    {
        $v         = $this->version;
        $numBlocks = self::NUM_BLOCKS[$v];
        $eccLen    = self::ECC_PER_BLOCK[$v];
        $raw       = intdiv(self::rawDataModules($v), 8);
        $numShort  = $numBlocks - $raw % $numBlocks;
        $shortLen  = intdiv($raw, $numBlocks) - $eccLen; // données d'un bloc court

        $generator = self::rsGenerator($eccLen);
        $dataBlocks = [];
        $eccBlocks  = [];
        $offset = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $len   = $shortLen + ($i < $numShort ? 0 : 1);
            $block = array_slice($data, $offset, $len);
            $offset += $len;
            $dataBlocks[] = $block;
            $eccBlocks[]  = self::rsRemainder($block, $generator);
        }

        $result = [];
        for ($i = 0; $i <= $shortLen; $i++) {
            foreach ($dataBlocks as $block) {
                if ($i < count($block)) {
                    $result[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $eccLen; $i++) {
            foreach ($eccBlocks as $block) {
                $result[] = $block[$i];
            }
        }
        return $result;
    }

    // --- Reed-Solomon sur GF(256) ----------------------------------------------

    private static function initGaloisField(): void
    {
        if (self::$exp !== []) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /**
     * Polynôme générateur (x - a^0)(x - a^1)…(x - a^(n-1)), coefficients du
     * degré n-1 au degré 0 (le coefficient dominant 1 est implicite).
     *
     * @return list<int>
     */
    private static function rsGenerator(int $degree): array
    {
        $poly = array_fill(0, $degree, 0);
        $poly[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $poly[$j] = self::gfMul($poly[$j], $root) ^ ($poly[$j + 1] ?? 0);
            }
            $root = self::gfMul($root, 0x02);
        }
        return $poly;
    }

    /**
     * Reste de la division du message par le générateur (= mots de correction).
     *
     * @param list<int> $data
     * @param list<int> $generator
     * @return list<int>
     */
    private static function rsRemainder(array $data, array $generator): array
    {
        $degree = count($generator);
        $result = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ $result[0];
            array_shift($result);
            $result[] = 0;
            for ($i = 0; $i < $degree; $i++) {
                $result[$i] ^= self::gfMul($generator[$i], $factor);
            }
        }
        return $result;
    }

    // --- Motifs de fonction --------------------------------------------------

    private function setFunction(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x]    = $dark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        $n = $this->size;

        // Motifs de synchronisation (ligne 6 et colonne 6).
        for ($i = 0; $i < $n; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }

        // Motifs de position (avec séparateurs) dans trois coins.
        $this->drawFinder(3, 3);
        $this->drawFinder($n - 4, 3);
        $this->drawFinder(3, $n - 4);

        // Motifs d'alignement (sauf ceux qui chevauchent un motif de position).
        $positions = $this->alignmentPositions();
        $count = count($positions);
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $count; $j++) {
                $corner = ($i === 0 && $j === 0) || ($i === 0 && $j === $count - 1) || ($i === $count - 1 && $j === 0);
                if (!$corner) {
                    $this->drawAlignment($positions[$i], $positions[$j]);
                }
            }
        }

        // Zones réservées aux informations de format (remplies plus tard).
        $this->drawFormatBits(0);
        $this->drawVersion();
    }

    /** Motif de position 7×7 centré en ($cx, $cy), entouré d'un séparateur clair. */
    private function drawFinder(int $cx, int $cy): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x < 0 || $y < 0 || $x >= $this->size || $y >= $this->size) {
                    continue;
                }
                $dist = max(abs($dx), abs($dy));
                $this->setFunction($x, $y, $dist !== 2 && $dist !== 4);
            }
        }
    }

    /** Motif d'alignement 5×5 centré en ($cx, $cy). */
    private function drawAlignment(int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunction($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    /** @return list<int> coordonnées des centres des motifs d'alignement */
    private function alignmentPositions(): array
    {
        $v = $this->version;
        if ($v === 1) {
            return [];
        }
        $numAlign = intdiv($v, 7) + 2;
        $step = $v === 32 ? 26 : intdiv($v * 4 + $numAlign * 2 + 1, $numAlign * 2 - 2) * 2;
        $result = [];
        for ($i = 0, $pos = $this->size - 7; $i < $numAlign - 1; $i++, $pos -= $step) {
            array_unshift($result, $pos);
        }
        array_unshift($result, 6);
        return $result;
    }

    /** Informations de format : niveau de correction + masque, code BCH(15,5). */
    private function drawFormatBits(int $mask): void
    {
        $data = (self::ECL_BITS << 3) | $mask;
        $rem  = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $n = $this->size;

        // Première copie, autour du motif de position en haut à gauche.
        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, self::bit($bits, $i));
        }
        $this->setFunction(8, 7, self::bit($bits, 6));
        $this->setFunction(8, 8, self::bit($bits, 7));
        $this->setFunction(7, 8, self::bit($bits, 8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, self::bit($bits, $i));
        }

        // Seconde copie, répartie entre les deux autres motifs de position.
        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($n - 1 - $i, 8, self::bit($bits, $i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, $n - 15 + $i, self::bit($bits, $i));
        }
        $this->setFunction(8, $n - 8, true); // module toujours sombre
    }

    /** Informations de version (version ≥ 7), code BCH(18,6). */
    private function drawVersion(): void
    {
        if ($this->version < 7) {
            return;
        }
        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($this->version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $dark = self::bit($bits, $i);
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunction($a, $b, $dark);
            $this->setFunction($b, $a, $dark);
        }
    }

    private static function bit(int $value, int $i): bool
    {
        return (($value >> $i) & 1) === 1;
    }

    // --- Placement des données et masques ------------------------------------

    /** Placement en zigzag par colonnes de deux modules, de bas en haut puis de haut en bas. */
    private function drawCodewords(array $codewords): void
    {
        $n = $this->size;
        $totalBits = count($codewords) * 8;
        $i = 0;
        for ($right = $n - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5; // la colonne 6 est le motif de synchronisation
            }
            $upward = (($right + 1) & 2) === 0;
            for ($vert = 0; $vert < $n; $vert++) {
                $y = $upward ? $n - 1 - $vert : $vert;
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    if (!$this->isFunction[$y][$x] && $i < $totalBits) {
                        $this->modules[$y][$x] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                    // Les éventuels bits de reste restent clairs (false).
                }
            }
        }
    }

    /** Applique (ou annule, par XOR) un masque sur les modules de données. */
    private function applyMask(int $mask): void
    {
        $n = $this->size;
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    7 => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
                };
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    /** Somme des 4 règles de pénalité de la norme. */
    private function penalty(): int
    {
        $n = $this->size;
        $m = $this->modules;
        $result = 0;

        // Lignes et colonnes sous forme de chaînes « 0/1 ».
        $lines = [];
        for ($y = 0; $y < $n; $y++) {
            $row = '';
            $col = '';
            for ($x = 0; $x < $n; $x++) {
                $row .= $m[$y][$x] ? '1' : '0';
                $col .= $m[$x][$y] ? '1' : '0';
            }
            $lines[] = $row;
            $lines[] = $col;
        }

        foreach ($lines as $line) {
            // Règle 1 : suites d'au moins 5 modules de même couleur.
            if (preg_match_all('/0{5,}|1{5,}/', $line, $runs)) {
                foreach ($runs[0] as $run) {
                    $result += 3 + strlen($run) - 5;
                }
            }
            // Règle 3 : motifs sombre/clair/sombre/clair/sombre de proportions
            // 1:1:3:1:1, précédés ou suivis d'au moins 4 unités claires
            // (l'extérieur du symbole compte comme clair).
            $result += 40 * self::finderLikeCount($line, $n);
        }

        // Règle 2 : carrés 2×2 de même couleur.
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                    $result += 3;
                }
            }
        }

        // Règle 4 : proportion de modules sombres éloignée de 50 %.
        $dark = 0;
        foreach ($m as $row) {
            $dark += count(array_filter($row));
        }
        $total = $n * $n;
        $k = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;
        $result += max(0, $k) * 10;

        return $result;
    }

    /** Nombre de motifs 1:1:3:1:1 bordés de clair dans une ligne « 0/1 ». */
    private static function finderLikeCount(string $line, int $n): int
    {
        // Bordure claire virtuelle de chaque côté ; les suites alternent
        // donc clair (index pair) / sombre (index impair).
        $border = str_repeat('0', $n);
        preg_match_all('/0+|1+/', $border . $line . $border, $m);
        $runs  = array_map('strlen', $m[0]);
        $count = 0;
        $last  = count($runs) - 6;
        for ($i = 1; $i <= $last; $i += 2) {
            $u = $runs[$i];
            if ($runs[$i + 1] !== $u || $runs[$i + 2] !== 3 * $u || $runs[$i + 3] !== $u || $runs[$i + 4] !== $u) {
                continue;
            }
            $before = $runs[$i - 1];
            $after  = $runs[$i + 5];
            $count += ($before >= 4 * $u && $after >= $u ? 1 : 0) + ($after >= 4 * $u && $before >= $u ? 1 : 0);
        }
        return $count;
    }
}
