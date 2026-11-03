<?php
/**
 * À propos : présentation, « en bref », centres d'intérêt, appel à l'action.
 */

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Models\Interest;

final class AboutController extends SiteController
{
    public function index(): void
    {
        $internship = $this->internshipWanted() ? $this->internshipSentence() : '';

        $this->render('site/about', [
            'aboutText'   => setting('about_text'),
            'interests'   => Interest::all(),
            'facts'       => $this->facts(),
            'internship'  => $internship,
            'cvAvailable' => $this->cvAvailable(),
            'meta'        => [
                'title'       => 'À propos',
                'description' => 'Alexandre Derbez, étudiant en BTS CIEL option IR à Avignon : parcours, passion pour les réseaux et la cybersécurité, jeux Roblox en Lua et VTT.',
                'canonical'   => '/a-propos',
            ],
        ]);
    }
}
