<?php
/**
 * Images d'un projet : envoi multiple et gestion des vignettes.
 *
 * Variables :
 *   $project   array  Project::find()
 *   $images    list   ProjectImage::forProject() (ordre d'affichage ; la première = couverture)
 *   $altErrors array  id d'image => ['error' => message, 'value' => saisie] (après une erreur)
 *   $limits    array  ['perFile' => octets, 'total' => octets, 'files' => nombre]
 */

declare(strict_types=1);

$project   = isset($project) && is_array($project) ? $project : [];
$images    = isset($images) && is_array($images) ? $images : [];
$altErrors = isset($altErrors) && is_array($altErrors) ? $altErrors : [];
$limits    = isset($limits) && is_array($limits) ? $limits : [];

$id      = (int) ($project['id'] ?? 0);
$title   = (string) ($project['title'] ?? '');
$count   = count($images);
$perFile = (int) ($limits['perFile'] ?? 0);
$total   = (int) ($limits['total'] ?? 0);
$maxFiles = (int) ($limits['files'] ?? 10);

$mo = static function (int $bytes): string {
    $value = $bytes / (1024 * 1024);
    return str_replace('.', ',', (string) round($value, $value < 10 && floor($value) !== $value ? 1 : 0)) . ' Mo';
};

$hint = 'JPEG, PNG ou WebP · ' . $mo($perFile) . ' maximum par image · ' . $maxFiles . ' images maximum par envoi.';
if ($total > 0 && $total < $perFile * $maxFiles) {
    $hint .= ' Taille totale d\'un envoi : ' . $mo($total) . ' au plus.';
}

$actions = '<a class="btn btn--secondary" href="/admin/projets/' . $id . '/modifier">' . icon('edit', 16) . 'Modifier le projet</a>';
if ((int) ($project['is_published'] ?? 0) === 1) {
    $actions .= '<a class="btn btn--secondary" href="/projet/' . e((string) $project['slug']) . '" target="_blank" rel="noopener noreferrer">'
        . icon('external', 16) . 'Voir sur le site<span class="sr-only"> (nouvel onglet)</span></a>';
}
?>
<?= partial('admin/page-header', [
    'comment' => '-- projets/' . (string) ($project['slug'] ?? '') . '/images',
    'title'   => 'Images du projet',
    'lead'    => $title,
    'back'    => ['href' => '/admin/projets', 'label' => 'Projets'],
    'actions' => $actions,
]) ?>

<div class="stack-lg">
<section class="card card--pad stack" aria-labelledby="titre-envoi">
    <h2 class="section-title" id="titre-envoi">Ajouter des images</h2>
    <form class="form" method="post" action="/admin/projets/<?= $id ?>/images" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php /* Indication pour PHP seulement : la vraie vérification de taille est faite par le serveur. */ ?>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= $perFile ?>">
        <div class="form-row form-row--2">
            <?= partial('site/field', [
                'name'     => 'images[]',
                'type'     => 'file',
                'label'    => 'Images',
                'required' => true,
                'hint'     => $hint,
                'attrs'    => [
                    'multiple'       => true,
                    'accept'         => 'image/jpeg,image/png,image/webp',
                    'data-file-preview' => true,
                    'data-max-size'  => (string) $perFile,
                    'data-max-files' => (string) $maxFiles,
                ],
            ]) ?>
            <?= partial('site/field', [
                'name'      => 'alt_text',
                'id'        => 'f-alt-defaut',
                'label'     => 'Texte alternatif par défaut',
                'optional'  => true,
                'maxlength' => 200,
                'placeholder' => $title,
                'hint'      => 'Décrit l\'image pour les personnes qui ne la voient pas. Vide : le titre du projet. Modifiable ensuite image par image.',
            ]) ?>
        </div>
        <p class="form__note">Chaque image est ré-encodée par le serveur : les métadonnées (EXIF, position GPS) sont supprimées, la taille est réduite à 1600 px et une miniature 16:9 est créée pour les cartes.</p>
        <div class="form__actions">
            <button class="btn btn--primary" type="submit" data-busy-label="Envoi en cours…"><?= icon('image', 16) ?>Envoyer les images</button>
        </div>
    </form>
</section>

<section class="stack-sm" aria-labelledby="titre-images">
    <h2 class="section-title" id="titre-images">Images enregistrées <span class="text-muted">(<?= $count ?>)</span></h2>
<?php if ($images === []): ?>
    <?= partial('site/empty-state', [
        'icon'    => 'image',
        'comment' => '-- 0 image',
        'title'   => 'Aucune image pour ce projet',
        'text'    => 'Sans image, la carte du projet affiche « capture à venir ». La première image envoyée devient la couverture.',
    ]) ?>
<?php else: ?>
    <p class="form__note">La première image est la couverture (cartes du site et aperçu de partage). Utilisez les flèches pour changer l'ordre.</p>
    <ul class="thumb-grid">
<?php foreach ($images as $i => $img):
    $imgId = (int) $img['id'];
    $altError = '';
    if (isset($altErrors[$imgId])) {
        $altError = (string) ($altErrors[$imgId]['error'] ?? '');
        $img['alt_text'] = (string) ($altErrors[$imgId]['value'] ?? $img['alt_text']);
    }
?>
        <?= partial('admin/image-card', [
            'image'   => $img,
            'number'  => $i + 1,
            'isCover' => $i === 0,
            'isFirst' => $i === 0,
            'isLast'  => $i === $count - 1,
            'error'   => $altError,
        ]) ?>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
</section>
</div>
