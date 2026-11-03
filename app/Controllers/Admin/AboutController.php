<?php
/**
 * Texte de la page « À propos » (réglage about_text, Markdown) et ville
 * affichée (réglage location).
 *
 *   GET  /admin/a-propos  edit
 *   POST /admin/a-propos  update
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminController;
use App\Core\Validator;
use App\Models\Setting;

final class AboutController extends AdminController
{
    public const TEXT_MAX = 10000;

    public function edit(): void
    {
        $this->form([
            'about_text' => Setting::get('about_text'),
            'location'   => Setting::get('location'),
        ]);
    }

    public function update(): void
    {
        $v = new Validator($_POST);
        $text     = $v->text('about_text', 'Texte de présentation', max: self::TEXT_MAX);
        $location = $v->string('location', 'Ville', max: 100);

        if ($v->fails()) {
            $this->form($_POST, $v->errors(), 422);
            return;
        }

        Setting::setMany([
            'about_text' => $text ?? '',
            'location'   => $location ?? '',
        ]);
        $this->success('/admin/a-propos', 'Page « À propos » enregistrée.');
    }

    /**
     * @param array<string, mixed>  $old
     * @param array<string, string> $errors
     */
    private function form(array $old, array $errors = [], int $status = 200): void
    {
        $this->render('admin/about/edit', [
            'old'     => $old,
            'errors'  => $errors,
            // Aperçu : toujours le texte ENREGISTRÉ (celui que voient les visiteurs).
            'saved'   => Setting::get('about_text'),
            'max'     => self::TEXT_MAX,
            'meta'    => ['title' => 'À propos'],
        ], $status);
    }
}
