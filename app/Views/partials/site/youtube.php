<?php
/**
 * Lecteur YouTube en « façade » : aucune requête vers YouTube avant le clic.
 * Au clic, site.js insère l'iframe youtube-nocookie.com (autorisée par la CSP).
 *
 * Variables :
 *   $id    string  identifiant YouTube (11 caractères, validé par Validator::youtubeId)
 *   $title string  titre de la vidéo (nom accessible du bouton et de l'iframe)
 */

declare(strict_types=1);

$id    = (string) ($id ?? '');
$title = (string) ($title ?? 'Vidéo');

if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
    return;
}
?>
<div class="video" data-video>
    <div class="video__frame">
        <button type="button" class="video__facade" data-youtube="<?= e($id) ?>" data-title="<?= e($title) ?>">
            <span class="video__play"><?= icon('play', 28) ?></span>
            <span class="video__label">Lire la vidéo<span class="sr-only"> : <?= e($title) ?></span></span>
        </button>
    </div>
    <p class="video__notice">La vidéo est hébergée par YouTube (youtube-nocookie.com) et ne se charge qu'après un clic. <a href="https://www.youtube.com/watch?v=<?= e($id) ?>" rel="noopener noreferrer">Ouvrir sur YouTube</a></p>
</div>
