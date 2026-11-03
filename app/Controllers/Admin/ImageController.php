<?php
/**
 * Images d'un projet : envoi multiple, texte alternatif, ordre, couverture,
 * suppression.
 *
 * Sécurité : chaque fichier passe par ImageProcessor::processUpload (type réel,
 * taille, dimensions, ré-encodage complet, nom aléatoire). Le nom d'origine
 * n'est jamais utilisé pour enregistrer le fichier ; il n'apparaît que dans
 * les messages, nettoyé et échappé à l'affichage.
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminController;
use App\Core\ImageProcessor;
use App\Core\Request;
use App\Core\Session;
use App\Core\UploadException;
use App\Core\Validator;
use App\Models\Project;
use App\Models\ProjectImage;
use Throwable;

final class ImageController extends AdminController
{
    /** Nombre maximal de fichiers par envoi. */
    public const MAX_FILES = 10;

    /** Longueur maximale du texte alternatif (colonne alt_text). */
    private const ALT_MAX = 200;

    /** GET /admin/projets/{id}/images */
    public function index(string $id): void
    {
        $project = $this->projectOr404($id);
        $this->renderIndex($project);
    }

    /** POST /admin/projets/{id}/images */
    public function upload(string $id): void
    {
        $project   = $this->projectOr404($id);
        $projectId = (int) $project['id'];
        $back      = '/admin/projets/' . $projectId . '/images';

        $files = Request::files('images');
        if ($files === []) {
            $this->error($back, 'Choisissez au moins une image à envoyer.');
        }
        if (count($files) > self::MAX_FILES) {
            $this->error($back, self::MAX_FILES . ' images au maximum par envoi (vous en avez choisi ' . count($files) . ') : aucune n\'a été enregistrée.');
        }

        $v   = new Validator($_POST);
        $alt = $v->string('alt_text', 'Texte alternatif par défaut', max: self::ALT_MAX);
        if ($v->fails()) {
            $this->error($back, $v->errors()['alt_text']);
        }
        $alt ??= mb_substr((string) $project['title'], 0, self::ALT_MAX);

        $dir      = self::uploadDir();
        $added    = 0;
        $refused  = [];
        foreach ($files as $file) {
            try {
                $stored = ImageProcessor::processUpload($file, $dir);
            } catch (UploadException $e) {
                $refused[] = [self::displayName($file['name']), $e->getMessage()];
                continue;
            }
            try {
                ProjectImage::add($projectId, $stored, $alt);
                $added++;
            } catch (Throwable $e) {
                // Fichiers produits mais non enregistrés en base : on les efface.
                ImageProcessor::deleteFiles($dir, $stored['filename'], $stored['thumb_filename']);
                error_log('[images] Enregistrement impossible : ' . $e::class . ' ' . $e->getMessage());
                $refused[] = [self::displayName($file['name']), 'Erreur interne lors de l\'enregistrement. Réessayez.'];
            }
        }

        if ($added > 0) {
            Session::flash('success', $added > 1 ? $added . ' images ajoutées.' : 'Image ajoutée.');
        }
        foreach ($refused as [$name, $reason]) {
            Session::flash('error', '« ' . $name . ' » refusé : ' . $reason);
        }
        redirect($back);
    }

    /** POST /admin/images/{id} : texte alternatif */
    public function update(string $id): void
    {
        [$image, $project] = $this->imageOr404($id);

        $v   = new Validator($_POST);
        $alt = $v->string('alt_text', 'Texte alternatif', max: self::ALT_MAX);
        if ($v->fails()) {
            // Formulaire réaffiché avec la saisie et l'erreur sur la bonne vignette.
            $this->renderIndex($project, [(int) $image['id'] => [
                'error' => $v->errors()['alt_text'],
                'value' => is_string($_POST['alt_text'] ?? null) ? $_POST['alt_text'] : '',
            ]], 422);
            return;
        }

        ProjectImage::updateAlt((int) $image['id'], $alt ?? '');
        $this->success(self::backTo($project), $alt === null
            ? 'Texte alternatif vidé : le site utilisera « ' . $project['title'] . ' : capture N ».'
            : 'Texte alternatif enregistré.');
    }

    /** POST /admin/images/{id}/monter */
    public function moveUp(string $id): void
    {
        [$image, $project] = $this->imageOr404($id);
        ProjectImage::moveUp((int) $image['id']);
        $this->success(self::backTo($project), 'Image déplacée vers le haut.');
    }

    /** POST /admin/images/{id}/descendre */
    public function moveDown(string $id): void
    {
        [$image, $project] = $this->imageOr404($id);
        ProjectImage::moveDown((int) $image['id']);
        $this->success(self::backTo($project), 'Image déplacée vers le bas.');
    }

    /** POST /admin/images/{id}/couverture */
    public function makeCover(string $id): void
    {
        [$image, $project] = $this->imageOr404($id);
        ProjectImage::makeCover((int) $image['id']);
        $this->success(self::backTo($project), 'Nouvelle image de couverture enregistrée.');
    }

    /** POST /admin/images/{id}/supprimer */
    public function destroy(string $id): void
    {
        [$image, $project] = $this->imageOr404($id);
        $row = ProjectImage::delete((int) $image['id']);
        if ($row !== null) {
            ImageProcessor::deleteFiles(self::uploadDir(), (string) $row['filename'], (string) $row['thumb_filename']);
        }
        $this->success(self::backTo($project), 'Image supprimée.');
    }

    // --- Interne -------------------------------------------------------------------------

    /**
     * @param array<string, mixed>                              $project
     * @param array<int, array{error: string, value: string}>   $altErrors
     */
    private function renderIndex(array $project, array $altErrors = [], int $status = 200): void
    {
        $this->render('admin/images/index', [
            'project'   => $project,
            'images'    => ProjectImage::forProject((int) $project['id']),
            'altErrors' => $altErrors,
            'limits'    => self::limits(),
            'meta'      => ['title' => 'Images de « ' . $project['title'] . ' »'],
        ], $status);
    }

    /** @return array<string, mixed> */
    private function projectOr404(string $id): array
    {
        $project = Project::find((int) $id);
        if ($project === null) {
            $this->notFound();
        }
        return $project;
    }

    /**
     * Image ET projet existants (une image orpheline n'est jamais modifiée).
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function imageOr404(string $id): array
    {
        $image = ProjectImage::find((int) $id);
        if ($image === null) {
            $this->notFound();
        }
        $project = Project::find((int) $image['project_id']);
        if ($project === null) {
            $this->notFound();
        }
        return [$image, $project];
    }

    /** @param array<string, mixed> $project */
    private static function backTo(array $project): string
    {
        return '/admin/projets/' . (int) $project['id'] . '/images';
    }

    private static function uploadDir(): string
    {
        return PUBLIC_PATH . '/uploads/projects';
    }

    /**
     * Nom d'origine présentable dans un message : sans chemin ni caractère de
     * contrôle, tronqué. (Il est de toute façon échappé à l'affichage.)
     */
    private static function displayName(string $name): string
    {
        // Octets invalides remplacés AVANT les expressions /u (qui échoueraient sinon).
        if (!mb_check_encoding($name, 'UTF-8')) {
            $name = (string) mb_convert_encoding($name, 'UTF-8', 'UTF-8');
        }
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name);
        $parts = explode('/', str_replace('\\', '/', $name));
        $name = trim((string) end($parts));
        return $name === '' ? 'fichier sans nom' : str_limit($name, 60);
    }

    /**
     * Limites réelles d'envoi (configuration PHP du serveur comprise).
     *
     * @return array{perFile: int, total: int, files: int}
     */
    private static function limits(): array
    {
        $perFile = min(ImageProcessor::MAX_BYTES, self::iniBytes((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX);
        $total   = self::iniBytes((string) ini_get('post_max_size'));
        $files   = min(self::MAX_FILES, (int) ini_get('max_file_uploads') ?: self::MAX_FILES);
        return ['perFile' => $perFile, 'total' => $total, 'files' => $files];
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $num = (int) $value;
        return match (strtolower(substr($value, -1))) {
            'g'     => $num * 1024 ** 3,
            'm'     => $num * 1024 ** 2,
            'k'     => $num * 1024,
            default => $num,
        };
    }
}
