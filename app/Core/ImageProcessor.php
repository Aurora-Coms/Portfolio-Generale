<?php
/**
 * Traitement sécurisé des images envoyées pour les projets.
 *
 * Étapes de sécurité :
 *  1. vérification de l'erreur d'upload et de is_uploaded_file() ;
 *  2. taille maximale (8 Mo) ;
 *  3. type MIME RÉEL lu dans le contenu (finfo), pas celui envoyé par le navigateur ;
 *  4. extension autorisée ET cohérente avec le type réel ;
 *  5. dimensions contrôlées (protection contre les « bombes de décompression ») ;
 *  6. RÉ-ENCODAGE complet avec GD : tout contenu caché (code PHP, métadonnées
 *     EXIF/GPS…) est supprimé, seuls les pixels sont conservés ;
 *  7. nom de fichier aléatoire généré par le serveur (le nom d'origine est ignoré).
 *
 * Deux fichiers sont produits : une grande version (1600 px max) et une
 * miniature 800×450 (16:9) recadrée, en WebP (ou JPEG si WebP indisponible).
 */

declare(strict_types=1);

namespace App\Core;

use GdImage;

final class ImageProcessor
{
    public const MAX_BYTES     = 8 * 1024 * 1024;
    public const MAX_PIXELS    = 40_000_000;
    public const MAX_SIDE      = 10_000;
    public const LARGE_MAX     = 1600;
    public const THUMB_W       = 800;
    public const THUMB_H       = 450;
    private const ALLOWED = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ];

    /**
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @return array{filename:string, thumb_filename:string, width:int, height:int}
     * @throws UploadException
     */
    public static function processUpload(array $file, string $destDir): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw UploadException::fromCode($file['error']);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new UploadException('Fichier refusé.');
        }
        return self::processFile($file['tmp_name'], $file['name'], $file['size'], $destDir);
    }

    /**
     * Traite un fichier local (utilisé par processUpload et par les tests).
     *
     * @return array{filename:string, thumb_filename:string, width:int, height:int}
     * @throws UploadException
     */
    public static function processFile(string $path, string $originalName, int $size, string $destDir): array
    {
        if ($size <= 0 || $size > self::MAX_BYTES || filesize($path) > self::MAX_BYTES) {
            throw new UploadException('L\'image ne doit pas dépasser 8 Mo.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || !isset(self::ALLOWED[$mime])) {
            throw new UploadException('Format non autorisé : envoyez une image JPEG, PNG ou WebP.');
        }
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED[$mime], true)) {
            throw new UploadException('L\'extension du fichier ne correspond pas à son contenu.');
        }

        $info = @getimagesize($path);
        if ($info === false || $info['mime'] !== $mime) {
            throw new UploadException('Ce fichier n\'est pas une image valide.');
        }
        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width > self::MAX_SIDE || $height > self::MAX_SIDE || $width * $height > self::MAX_PIXELS) {
            throw new UploadException('Les dimensions de l\'image sont trop grandes.');
        }

        @ini_set('memory_limit', '512M');
        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
        };
        if (!$source instanceof GdImage) {
            throw new UploadException('Impossible de lire cette image.');
        }

        if ($mime === 'image/jpeg') {
            $source = self::applyExifOrientation($source, $path);
        }

        if (!is_dir($destDir) || !is_writable($destDir)) {
            imagedestroy($source);
            throw new UploadException('Le dossier des images n\'est pas accessible en écriture.');
        }

        $useWebp = function_exists('imagewebp');
        $ext     = $useWebp ? 'webp' : 'jpg';
        $base    = bin2hex(random_bytes(16));
        $large   = $base . '.' . $ext;
        $thumb   = $base . '-thumb.' . $ext;

        try {
            $largeImg = self::resizeWithin($source, self::LARGE_MAX);
            $thumbImg = self::coverCrop($source, self::THUMB_W, self::THUMB_H);
            self::save($largeImg, $destDir . '/' . $large, $useWebp);
            self::save($thumbImg, $destDir . '/' . $thumb, $useWebp);
            $result = [
                'filename'       => $large,
                'thumb_filename' => $thumb,
                'width'          => imagesx($largeImg),
                'height'         => imagesy($largeImg),
            ];
            imagedestroy($largeImg);
            imagedestroy($thumbImg);
        } finally {
            imagedestroy($source);
        }
        return $result;
    }

    /** Supprime les fichiers d'une image (noms vérifiés : aucune traversée de dossier). */
    public static function deleteFiles(string $destDir, string ...$filenames): void
    {
        foreach ($filenames as $name) {
            if (preg_match('/^[a-f0-9]{32}(?:-thumb)?\.(?:webp|jpg)$/', $name) && is_file($destDir . '/' . $name)) {
                unlink($destDir . '/' . $name);
            }
        }
    }

    private static function resizeWithin(GdImage $src, int $max): GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $ratio = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));
        $dst = self::canvas($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }

    private static function coverCrop(GdImage $src, int $tw, int $th): GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = max($tw / $w, $th / $h);
        $cw = (int) round($tw / $scale);
        $ch = (int) round($th / $scale);
        $cx = (int) max(0, floor(($w - $cw) / 2));
        $cy = (int) max(0, floor(($h - $ch) / 2));
        $dst = self::canvas($tw, $th);
        imagecopyresampled($dst, $src, 0, 0, $cx, $cy, $tw, $th, min($cw, $w), min($ch, $h));
        return $dst;
    }

    private static function canvas(int $w, int $h): GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 21, 22, 30, 127));
        return $img;
    }

    private static function save(GdImage $img, string $path, bool $webp): void
    {
        if ($webp) {
            $ok = imagewebp($img, $path, 82);
        } else {
            // JPEG sans transparence : fond sombre du site.
            $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
            imagefill($flat, 0, 0, imagecolorallocate($flat, 21, 22, 30));
            imagealphablending($flat, true);
            imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            $ok = imagejpeg($flat, $path, 85);
            imagedestroy($flat);
        }
        if (!$ok) {
            throw new UploadException('Impossible d\'enregistrer l\'image.');
        }
        @chmod($path, 0644);
    }

    /** Redresse les photos de téléphone (orientation EXIF), puis l'EXIF est perdu au ré-encodage. */
    private static function applyExifOrientation(GdImage $img, string $path): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $rotated = match ($orientation) {
            3       => imagerotate($img, 180, 0),
            6       => imagerotate($img, -90, 0),
            8       => imagerotate($img, 90, 0),
            default => null,
        };
        if ($rotated instanceof GdImage) {
            imagedestroy($img);
            return $rotated;
        }
        return $img;
    }
}
