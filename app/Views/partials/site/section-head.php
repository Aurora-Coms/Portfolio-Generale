<?php
/**
 * Titre de section façon commentaire : « -- projets/ » au-dessus d'un titre mono.
 *
 * Variables :
 *   $title   string  titre (obligatoire)
 *   $comment string  (optionnel) commentaire, ex. '-- projets/'
 *   $id      string  (optionnel) id du titre, à référencer par aria-labelledby sur la <section>
 *   $level   int     (optionnel) niveau du titre, 2 par défaut (2 à 4)
 *   $actions string  (optionnel) HTML DÉJÀ SÛR affiché à droite (ex. partial('site/project-filters', …))
 */

declare(strict_types=1);

$title   = (string) ($title ?? '');
$comment = (string) ($comment ?? '');
$id      = (string) ($id ?? '');
$level   = max(2, min(4, (int) ($level ?? 2)));
$actions = (string) ($actions ?? '');
?>
<div class="section-head">
    <div>
<?php if ($comment !== ''): ?>
        <p class="section-head__comment" aria-hidden="true"><?= e($comment) ?></p>
<?php endif; ?>
        <h<?= $level ?> class="section-head__title"<?= $id !== '' ? ' id="' . e($id) . '"' : '' ?>><?= e($title) ?></h<?= $level ?>>
    </div>
<?= $actions ?>
</div>
