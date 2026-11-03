<?php
/**
 * Réglages du site (table clé/valeur « settings »).
 *
 * Clés utilisées : voir Setting::DEFAULTS.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Setting
{
    /** Valeurs par défaut si une clé est absente de la base. */
    public const DEFAULTS = [
        'site_name'          => 'Alexandre Derbez',
        'site_class'         => 'BTS CIEL · IR',
        'hero_kicker'        => '-- Bonjour, je suis',
        'hero_title'         => 'Étudiant BTS CIEL option IR · Cybersécurité & Réseaux',
        'hero_tagline'       => '',
        'banner_enabled'     => '1',
        'banner_title'       => 'Recherche de stage',
        'banner_text'        => 'en développement informatique / réseaux',
        'internship_start'   => '2026-05-18',
        'internship_end'     => '2026-06-26',
        'about_text'         => '',
        'location'           => 'Avignon (84)',
        'linkedin_url'       => '',
        'github_url'         => '',
        'contact_email'      => '',
        'meta_description'   => '',
        'cv_updated_at'      => '',
        'legal_publisher'    => 'Alexandre Derbez',
        'legal_host'         => "Hostinger International Ltd.\n61 Lordou Vironos Street, 6023 Larnaca, Chypre\nhttps://www.hostinger.fr",
    ];

    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /** @return array<string, string> */
    public static function all(): array
    {
        if (self::$cache === null) {
            $rows = Database::fetchAll('SELECT setting_key, setting_value FROM settings');
            $values = [];
            foreach ($rows as $row) {
                $values[(string) $row['setting_key']] = (string) $row['setting_value'];
            }
            self::$cache = $values + self::DEFAULTS;
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        return self::all()[$key] ?? $default;
    }

    /**
     * Enregistre plusieurs réglages d'un coup.
     *
     * @param array<string, string> $values
     */
    public static function setMany(array $values): void
    {
        Database::transaction(static function () use ($values): void {
            foreach ($values as $key => $value) {
                Database::execute(
                    'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = ?',
                    [$key, $value, $value]
                );
            }
        });
        self::$cache = null;
    }
}
