<?php
/**
 * Parcours : timeline complète (études, stages, autres expériences).
 */

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Models\TimelineEntry;

final class TimelineController extends SiteController
{
    public function index(): void
    {
        $this->render('site/timeline', [
            'entries'     => TimelineEntry::published(),
            'facts'       => $this->facts(),
            'cvAvailable' => $this->cvAvailable(),
            'meta'        => [
                'title'       => 'Parcours',
                'description' => 'Parcours d\'Alexandre Derbez : BTS CIEL option IR, Bac Pro SN option RISC et 6 stages en maintenance informatique (Avignon, CNRS Marseille).',
                'canonical'   => '/parcours',
            ],
        ]);
    }
}
