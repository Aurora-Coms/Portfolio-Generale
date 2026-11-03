<?php
/**
 * Réglages du site (textes d'accueil, bandeau de stage, liens, référencement,
 * mentions légales) et CV au format PDF.
 *
 *   GET  /admin/reglages              edit
 *   POST /admin/reglages              update
 *   POST /admin/reglages/cv           uploadCv
 *   POST /admin/reglages/cv/supprimer deleteCv
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminController;
use App\Core\PdfUpload;
use App\Core\Request;
use App\Core\UploadException;
use App\Core\Validator;
use App\Models\Setting;

final class SettingsController extends AdminController
{
    /** Clés enregistrées par le formulaire principal (liste blanche). */
    private const KEYS = [
        'site_name', 'site_class', 'hero_kicker', 'hero_title', 'hero_tagline',
        'banner_enabled', 'banner_title', 'banner_text', 'internship_start', 'internship_end',
        'linkedin_url', 'github_url', 'contact_email',
        'meta_description',
        'legal_publisher', 'legal_host',
    ];

    public const META_MAX = 170;

    public function edit(): void
    {
        $this->form(self::current());
    }

    public function update(): void
    {
        [$values, $errors] = $this->validate($_POST);
        if ($errors !== []) {
            $this->form($_POST, $errors, '', 422);
            return;
        }
        Setting::setMany($values);
        $this->success('/admin/reglages', 'Réglages enregistrés.');
    }

    public function uploadCv(): void
    {
        $files = Request::files('cv');
        try {
            if ($files === []) {
                throw new UploadException('Choisissez un fichier PDF à envoyer.');
            }
            PdfUpload::store($files[0]);
        } catch (UploadException $e) {
            // Message prévu pour l'utilisateur (jamais un détail technique).
            $this->form(self::current(), [], $e->getMessage(), 422);
            return;
        }
        Setting::setMany(['cv_updated_at' => date('Y-m-d')]);
        $this->success('/admin/reglages#cv', 'CV mis en ligne. Il est téléchargeable à l\'adresse /cv.');
    }

    public function deleteCv(): void
    {
        PdfUpload::delete();
        Setting::setMany(['cv_updated_at' => '']);
        $this->success('/admin/reglages#cv', 'CV supprimé du site.');
    }

    // -------------------------------------------------------------------------

    /**
     * Valeurs actuelles des réglages du formulaire.
     *
     * @return array<string, string>
     */
    private static function current(): array
    {
        $all = Setting::all();
        $values = [];
        foreach (self::KEYS as $key) {
            $values[$key] = (string) ($all[$key] ?? '');
        }
        return $values;
    }

    /**
     * @param array<string, mixed>  $old
     * @param array<string, string> $errors
     */
    private function form(array $old, array $errors = [], string $cvError = '', int $status = 200): void
    {
        $exists = PdfUpload::exists();
        $this->render('admin/settings/edit', [
            'old'     => $old,
            'errors'  => $errors,
            'cv'      => [
                'exists'     => $exists,
                'size'       => $exists ? (int) filesize(PdfUpload::path()) : 0,
                'updated_at' => Setting::get('cv_updated_at'),
                'max'        => PdfUpload::MAX_BYTES,
            ],
            'cvError' => $cvError,
            'metaMax' => self::META_MAX,
            'meta'    => ['title' => 'Réglages'],
        ], $status);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function validate(array $input): array
    {
        $v = new Validator($input);
        $s = [];

        // Accueil
        $s['site_name']    = $v->string('site_name', 'Nom affiché', required: true, max: 80);
        $s['site_class']   = $v->string('site_class', 'Formation (sous le nom)', max: 60);
        $s['hero_kicker']  = $v->string('hero_kicker', 'Accroche', max: 60);
        $s['hero_title']   = $v->string('hero_title', 'Titre', required: true, max: 150);
        $s['hero_tagline'] = $v->string('hero_tagline', 'Présentation courte', max: 300);

        // Bandeau de stage
        $enabled               = $v->bool('banner_enabled');
        $s['banner_enabled']   = $enabled ? '1' : '0';
        $s['banner_title']     = $v->string('banner_title', 'Titre du bandeau', required: $enabled, max: 80);
        $s['banner_text']      = $v->string('banner_text', 'Texte du bandeau', max: 200);
        $s['internship_start'] = $v->date('internship_start', 'Début du stage');
        $s['internship_end']   = $v->date('internship_end', 'Fin du stage');
        if ($s['internship_start'] !== null && $s['internship_end'] !== null && $s['internship_end'] < $s['internship_start']) {
            $v->addError('internship_end', 'La fin du stage ne peut pas être avant son début.');
        }
        if ($s['internship_end'] !== null && $s['internship_start'] === null && !isset($v->errors()['internship_start'])) {
            $v->addError('internship_start', 'Indiquez aussi la date de début du stage.');
        }

        // Liens et contact (http(s) uniquement : jamais javascript:, data:…)
        $s['linkedin_url']  = $v->url('linkedin_url', 'Profil LinkedIn');
        $s['github_url']    = $v->url('github_url', 'Profil GitHub');
        $s['contact_email'] = $v->email('contact_email', 'Adresse e-mail de contact', required: false);
        self::requireHost($v, 'linkedin_url', $s['linkedin_url'], 'linkedin.com', 'L\'adresse doit être un profil LinkedIn, ex. https://www.linkedin.com/in/votre-nom');
        self::requireHost($v, 'github_url', $s['github_url'], 'github.com', 'L\'adresse doit être un profil GitHub, ex. https://github.com/votre-pseudo');

        // Référencement
        $s['meta_description'] = $v->string('meta_description', 'Description pour les moteurs de recherche', max: self::META_MAX);

        // Mentions légales
        $s['legal_publisher'] = $v->string('legal_publisher', 'Éditeur du site', required: true, max: 150);
        $s['legal_host']      = $v->text('legal_host', 'Hébergeur', required: true, max: 1000);

        // Toujours des chaînes (null => '').
        $values = [];
        foreach (self::KEYS as $key) {
            $values[$key] = (string) ($s[$key] ?? '');
        }
        return [$values, $v->errors()];
    }

    /** Vérifie qu'une URL déjà validée pointe bien vers le domaine attendu (ou un sous-domaine). */
    private static function requireHost(Validator $v, string $field, ?string $url, string $domain, string $message): void
    {
        if ($url === null) {
            return;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host !== $domain && !str_ends_with($host, '.' . $domain)) {
            $v->addError($field, $message);
        }
    }
}
