<?php
/**
 * Téléchargement du CV (PDF stocké hors du web, dans app/storage/cv).
 */

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\HttpException;
use App\Core\PdfUpload;

final class CvController extends SiteController
{
    public function download(): void
    {
        $file = PdfUpload::path();
        if (!PdfUpload::exists() || !is_readable($file)) {
            throw new HttpException(404, 'Le CV n\'est pas encore disponible.');
        }
        $size = filesize($file);

        // Rien ne doit précéder le fichier (tampon de sortie éventuel vidé).
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="CV-Alexandre-Derbez.pdf"');
        if ($size !== false) {
            header('Content-Length: ' . $size);
        }
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($file);
    }
}
