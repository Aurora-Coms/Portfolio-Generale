<?php
/**
 * Base commune des contrôleurs du site public.
 *
 * Regroupe les petites règles d'affichage partagées par plusieurs pages
 * (période du stage recherché, disponibilité du CV, liens externes sûrs).
 */

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\PdfUpload;
use DateTimeImmutable;

abstract class SiteController extends Controller
{
    /** Le CV (PDF) a-t-il été envoyé depuis l'administration ? */
    protected function cvAvailable(): bool
    {
        return PdfUpload::exists();
    }

    /** Le bandeau « Recherche de stage » est-il activé ? */
    protected function internshipWanted(): bool
    {
        return setting('banner_enabled') === '1';
    }

    /**
     * Période du stage recherché, sans préposition : « 18 mai au 26 juin 2026 »
     * (l'année n'est écrite qu'une fois si elle est identique), « 18 mai 2026 »
     * si seule la date de début est connue, '' si aucune date valide.
     */
    protected function internshipPeriod(): string
    {
        $start = self::validDate(setting('internship_start'));
        $end   = self::validDate(setting('internship_end'));
        if ($start === null) {
            return '';
        }
        $from = date_fr($start);
        if ($end === null || $end < $start) {
            return $from;
        }
        $to = date_fr($end);
        if (substr($start, 0, 4) === substr($end, 0, 4)) {
            $from = (string) (preg_replace('/\s\d{4}$/', '', $from) ?? $from);
        }
        return $from . ' au ' . $to;
    }

    /**
     * Phrase de disponibilité : « du 18 mai au 26 juin 2026 », « à partir du 18 mai 2026 ».
     */
    protected function internshipSentence(): string
    {
        $period = $this->internshipPeriod();
        if ($period === '') {
            return '';
        }
        return str_contains($period, ' au ') ? 'du ' . $period : 'à partir du ' . $period;
    }

    /**
     * Bloc « en bref » (partial site/facts) : formation, lycée, diplôme, ville
     * et stage recherché (si le bandeau de stage est activé).
     *
     * @return list<array{label:string, value:string}>
     */
    protected function facts(): array
    {
        $facts = [
            ['label' => 'formation', 'value' => 'BTS CIEL option IR (1re année)'],
            ['label' => 'lycee',     'value' => 'Campus St Jean-Baptiste de La Salle'],
            ['label' => 'diplome',   'value' => 'Bac Pro SN option RISC'],
        ];
        $location = setting('location');
        if ($location !== '') {
            $facts[] = ['label' => 'ville', 'value' => $location];
        }
        $internship = $this->internshipWanted() ? $this->internshipSentence() : '';
        if ($internship !== '') {
            $facts[] = ['label' => 'stage', 'value' => 'recherché ' . $internship];
        }
        return $facts;
    }

    /** URL externe venant de la base : http(s) uniquement, sinon ''. */
    protected static function safeUrl(?string $url): string
    {
        $url = trim((string) $url);
        return preg_match('#^https?://[^\s"<>]+$#i', $url) ? $url : '';
    }

    /** Date AAAA-MM-JJ valide, sinon null. */
    private static function validDate(string $value): ?string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value ? $value : null;
    }
}
