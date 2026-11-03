<?php
/**
 * Téléversement sécurisé du CV (PDF).
 *
 * Le fichier est stocké HORS du web (app/storage/cv/cv.pdf) et n'est servi que
 * par la route /cv, avec les bons en-têtes. Vérifications : erreur d'upload,
 * is_uploaded_file, taille (5 Mo), type MIME réel, signature « %PDF- »,
 * extension .pdf.
 */

declare(strict_types=1);

namespace App\Core;

final class PdfUpload
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public static function path(): string
    {
        return STORAGE_PATH . '/cv/cv.pdf';
    }

    /**
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @throws UploadException
     */
    public static function store(array $file): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw UploadException::fromCode($file['error']);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new UploadException('Fichier refusé.');
        }
        if ($file['size'] <= 0 || $file['size'] > self::MAX_BYTES) {
            throw new UploadException('Le CV ne doit pas dépasser 5 Mo.');
        }
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
            throw new UploadException('Le CV doit être un fichier .pdf.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 5);
        if ($mime !== 'application/pdf' || $head !== '%PDF-') {
            throw new UploadException('Ce fichier n\'est pas un PDF valide.');
        }
        $dir = dirname(self::path());
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new UploadException('Le dossier de stockage du CV n\'est pas accessible en écriture.');
        }
        if (!move_uploaded_file($file['tmp_name'], self::path())) {
            throw new UploadException('Impossible d\'enregistrer le CV.');
        }
        @chmod(self::path(), 0640);
    }

    public static function exists(): bool
    {
        return is_file(self::path());
    }

    public static function delete(): void
    {
        if (self::exists()) {
            unlink(self::path());
        }
    }
}
