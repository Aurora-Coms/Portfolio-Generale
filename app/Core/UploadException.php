<?php
/**
 * Erreur de téléversement, avec un message lisible par l'utilisateur.
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class UploadException extends RuntimeException
{
    public static function fromCode(int $code): self
    {
        return new self(match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier est trop volumineux.',
            UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a été reçu que partiellement. Réessayez.',
            UPLOAD_ERR_NO_FILE    => 'Aucun fichier n\'a été envoyé.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'Le serveur n\'a pas pu enregistrer le fichier.',
            default               => 'Erreur inconnue lors de l\'envoi du fichier.',
        });
    }
}
