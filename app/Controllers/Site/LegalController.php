<?php
/**
 * Mentions légales (LCEN), données personnelles (RGPD) et cookies.
 */

declare(strict_types=1);

namespace App\Controllers\Site;

final class LegalController extends SiteController
{
    /** Date de dernière mise à jour du texte (à modifier en même temps que la vue). */
    private const UPDATED_AT = '2026-11-03';

    public function index(): void
    {
        $this->render('site/legal', [
            'publisher' => setting('legal_publisher', 'Alexandre Derbez'),
            'host'      => setting('legal_host'),
            'updatedAt' => self::UPDATED_AT,
            'meta'      => [
                'title'       => 'Mentions légales',
                'description' => 'Mentions légales du portfolio d\'Alexandre Derbez : éditeur, hébergeur, données personnelles (RGPD), cookies et sécurité.',
                'canonical'   => '/mentions-legales',
            ],
        ]);
    }
}
