<?php
/**
 * Galerie d'un projet : grande image + miniatures, visionneuse au clic (site.js).
 * Sans JavaScript, chaque lien ouvre l'image en grand.
 *
 * Variables :
 *   $images list   images (format ProjectImage / findPublishedBySlug()['images']) :
 *                  filename, thumb_filename, alt_text, width, height
 *   $title  string titre du projet (texte alternatif de repli)
 *   $id     string (optionnel) identifiant du groupe de la visionneuse, défaut 'galerie'
 */

declare(strict_types=1);

$images = isset($images) && is_array($images) ? array_values($images) : [];
$title  = (string) ($title ?? '');
$group  = (string) ($id ?? 'galerie');

if ($images === []) {
    return;
}

$alt = static function (array $img, int $i) use ($title): string {
    $text = trim((string) ($img['alt_text'] ?? ''));
    return $text !== '' ? $text : $title . ' : capture ' . ($i + 1);
};
$first = $images[0];
$count = count($images);
?>
<div class="gallery">
    <a class="gallery__main" href="<?= e(upload_url((string) $first['filename'])) ?>" data-lightbox="<?= e($group) ?>" data-caption="<?= e($alt($first, 0)) ?>">
        <img src="<?= e(upload_url((string) $first['filename'])) ?>" alt="<?= e($alt($first, 0)) ?>" width="<?= (int) $first['width'] ?>" height="<?= (int) $first['height'] ?>" decoding="async">
        <span class="sr-only"> (agrandir l'image)</span>
    </a>
<?php if ($count > 1): ?>
    <ul class="gallery__thumbs" aria-label="Autres captures">
<?php foreach (array_slice($images, 1, null, true) as $i => $img): ?>
        <li>
            <a class="gallery__thumb" href="<?= e(upload_url((string) $img['filename'])) ?>" data-lightbox="<?= e($group) ?>" data-caption="<?= e($alt($img, $i)) ?>">
                <img src="<?= e(upload_url((string) $img['thumb_filename'])) ?>" alt="<?= e($alt($img, $i)) ?> (agrandir)" width="<?= (int) $img['width'] ?>" height="<?= (int) $img['height'] ?>" loading="lazy" decoding="async">
            </a>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
    <p class="gallery__hint"><?= $count ?> capture<?= $count > 1 ? 's' : '' ?> · sélectionnez une image pour l'agrandir.</p>
</div>
