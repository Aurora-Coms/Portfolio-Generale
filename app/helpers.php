<?php
/**
 * Fonctions utilitaires disponibles partout (vues comprises).
 */

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Markdown;
use App\Core\Session;
use App\Core\View;
use App\Models\Setting;

/**
 * Échappement HTML : À UTILISER POUR TOUTE DONNÉE AFFICHÉE.
 * (protège contre les failles XSS, y compris dans les attributs).
 */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Markdown restreint (déjà échappé) : à afficher SANS e(). */
function md(?string $text): string
{
    return Markdown::render($text);
}

/** Champ caché contenant le jeton CSRF, à placer dans chaque <form method="post">. */
function csrf_field(): string
{
    return Csrf::field();
}

/** URL absolue (Open Graph, sitemap, e-mails). */
function abs_url(string $path = '/'): string
{
    return rtrim((string) Config::get('base_url'), '/') . '/' . ltrim($path, '/');
}

/** Chemin d'un fichier statique, avec numéro de version pour le cache. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = PUBLIC_PATH . '/assets/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '0';
    return '/assets/' . $path . '?v=' . $version;
}

/** URL publique d'une image de projet. */
function upload_url(string $filename): string
{
    return '/uploads/projects/' . rawurlencode($filename);
}

/**
 * Mode « site statique » (export HTML pour GitHub Pages, sans PHP en ligne) :
 * le formulaire de contact, le CV et l'administration n'existent pas.
 */
function static_site(): bool
{
    return Config::get('static_site') === true;
}

/** Valeur d'un réglage du site. */
function setting(string $key, string $default = ''): string
{
    return Setting::get($key, $default);
}

/** Rendu d'un fragment de vue (app/Views/partials/…). */
function partial(string $name, array $data = []): string
{
    return View::partial($name, $data);
}

/**
 * Redirection interne (chemins commençant par « / » uniquement : pas de
 * redirection ouverte vers un site externe).
 */
function redirect(string $path, int $status = 303): never
{
    // Refus de « //domaine », « /\domaine » et de TOUT caractère de contrôle :
    // les navigateurs retirent tabulations et retours à la ligne, ce qui
    // transformerait « /<TAB>/domaine » en « //domaine » (redirection ouverte).
    if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path)) {
        $path = '/';
    }
    header('Location: ' . $path, true, $status);
    exit;
}

/**
 * Messages flash à afficher (et effacés ensuite).
 *
 * @return list<array{type:string, message:string}>
 */
function flashes(): array
{
    return Session::pullFlashes();
}

/**
 * Ancienne valeur d'un champ (après une erreur de validation).
 *
 * @param array<string, mixed> $old
 */
function old(array $old, string $key, string $default = ''): string
{
    $value = $old[$key] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
}

/** La page courante commence-t-elle par ce chemin ? (menu actif) */
function is_current(string $prefix): bool
{
    $path = App\Core\Request::path();
    return $prefix === '/' ? $path === '/' : ($path === $prefix || str_starts_with($path, $prefix . '/'));
}

/** Noms des mois en français (sans dépendre de l'extension intl). */
function month_fr(int $month): string
{
    return ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août',
        'septembre', 'octobre', 'novembre', 'décembre'][$month - 1] ?? '';
}

/**
 * Date en français selon une précision : 'day' => « 18 mai 2026 »,
 * 'month' => « mai 2026 », 'year' => « 2026 ».
 */
function date_fr(?string $date, string $precision = 'day'): string
{
    if ($date === null || $date === '') {
        return '';
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));
    if ($d === false) {
        return '';
    }
    return match ($precision) {
        'year'  => $d->format('Y'),
        'month' => month_fr((int) $d->format('n')) . ' ' . $d->format('Y'),
        default => (int) $d->format('j') . ($d->format('j') === '1' ? 'er' : '') . ' ' . month_fr((int) $d->format('n')) . ' ' . $d->format('Y'),
    };
}

/** Période : « 2025 → en cours », « mai 2023 → juin 2023 », « 2024 ». */
function period_fr(?string $start, ?string $end, string $precision = 'month'): string
{
    $from = date_fr($start, $precision);
    if ($end === null || $end === '') {
        return $from . ' → en cours';
    }
    $to = date_fr($end, $precision);
    return $from === $to ? $from : $from . ' → ' . $to;
}

/** Date et heure courtes : « 03/11/2026 à 14:05 ». */
function datetime_fr(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }
    $d = date_create_immutable($datetime);
    return $d ? $d->format('d/m/Y \à H:i') : '';
}

/** Libellé d'un niveau de compétence (1 à 5). */
function level_label(int $level): string
{
    return [1 => 'Notions', 2 => 'Bases', 3 => 'Intermédiaire', 4 => 'Avancé', 5 => 'Maîtrise'][$level] ?? '';
}

function section_label(string $section): string
{
    return $section === 'game' ? 'Projets de jeux' : 'Projets techniques';
}

function status_label(string $status): string
{
    return match ($status) {
        'done'    => 'Terminé',
        'planned' => 'Projet futur',
        default   => 'En cours',
    };
}

/** Classe CSS du badge de statut d'un projet (badge--done, badge--progress, badge--planned). */
function status_badge(string $status): string
{
    return match ($status) {
        'done'    => 'done',
        'planned' => 'planned',
        default   => 'progress',
    };
}

function timeline_type_label(string $type): string
{
    return ['education' => 'Études', 'internship' => 'Stage', 'other' => 'Autre'][$type] ?? '';
}

/** Coupe un texte proprement (sans couper un mot) avec « … ». */
function str_limit(string $text, int $max): string
{
    $text = trim($text);
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max);
    $space = mb_strrpos($cut, ' ');
    return rtrim($space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,;:.") . '…';
}

/**
 * Icône SVG en ligne (trait), parmi un jeu fixe. Décorative par défaut.
 * Les noms inconnus renvoient une chaîne vide.
 */
function icon(string $name, int $size = 18, string $class = 'icon'): string
{
    $paths = [
        'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
        'arrow'    => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
        'arrow-left' => '<path d="M19 12H5"/><path d="m11 18-6-6 6-6"/>',
        'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/>',
        'menu'     => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
        'close'    => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
        'file'     => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/>',
        'external' => '<path d="M14 4h6v6"/><path d="M10 14 20 4"/><path d="M20 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h5"/>',
        'play'     => '<path d="M7 4v16l13-8z"/>',
        'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'linkedin' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 10v7"/><path d="M8 7v.01"/><path d="M12 17v-4a2 2 0 0 1 4 0v4"/><path d="M12 10v7"/>',
        'github'   => '<path d="M9 19c-4 1.5-4-2-6-2.5"/><path d="M15 21v-3.5a3 3 0 0 0-.9-2.3c3-.3 6-1.5 6-6.5a5 5 0 0 0-1.4-3.5 4.6 4.6 0 0 0-.1-3.5s-1.1-.3-3.6 1.4a12.4 12.4 0 0 0-6.5 0C6 1.4 4.9 1.7 4.9 1.7a4.6 4.6 0 0 0-.1 3.5A5 5 0 0 0 3.4 8.7c0 5 3 6.2 6 6.5a3 3 0 0 0-.9 2.3V21"/>',
        'network'  => '<rect x="9" y="3" width="6" height="5" rx="1"/><rect x="3" y="16" width="6" height="5" rx="1"/><rect x="15" y="16" width="6" height="5" rx="1"/><path d="M12 8v4"/><path d="M6 16v-2h12v2"/>',
        'server'   => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><path d="M7 7.5h.01"/><path d="M7 16.5h.01"/>',
        'code'     => '<path d="m8 8-4 4 4 4"/><path d="m16 8 4 4-4 4"/><path d="m14 4-4 16"/>',
        'shield'   => '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'tools'    => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z"/>',
        'chip'     => '<rect x="6" y="6" width="12" height="12" rx="1.5"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>',
        'terminal' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m7 9 3 3-3 3"/><path d="M13 15h4"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.5 2.5 3.8 5.5 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.5-3.8-9s1.3-6.5 3.8-9z"/>',
        'bike'     => '<circle cx="5.5" cy="17" r="3.5"/><circle cx="18.5" cy="17" r="3.5"/><path d="M5.5 17 9 9h5l4.5 8"/><path d="M9 9 8 6H6"/><path d="m12 17 2-8"/>',
        'gamepad'  => '<rect x="2" y="7" width="20" height="11" rx="4"/><path d="M7 11v3M5.5 12.5h3"/><path d="M15.5 12h.01M18 14h.01"/>',
        'book'     => '<path d="M4 5a2 2 0 0 1 2-2h14v16H6a2 2 0 0 0-2 2z"/><path d="M4 19V5"/>',
        'music'    => '<circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/><path d="M9 18V5l12-2v13"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'check'    => '<path d="m5 12 5 5 9-10"/>',
        'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'trash'    => '<path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/>',
        'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'star'     => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
        'up'       => '<path d="m6 15 6-6 6 6"/>',
        'down'     => '<path d="m6 9 6 6 6-6"/>',
        'logout'   => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M10 17l-5-5 5-5"/><path d="M5 12h11"/>',
        'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
        'folder'   => '<path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'tag'      => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><path d="M8 8h.01"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'home'     => '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    return '<svg class="' . e($class) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}
