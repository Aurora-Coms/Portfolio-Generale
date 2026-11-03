<?php
/**
 * Formulaire de projet (création et modification).
 *
 * Variables :
 *   $project     ?array  projet modifié (Project::find) ou null en création
 *   $old         array   valeurs à afficher (ligne du projet ou saisie après erreur)
 *   $errors      array   champ => message
 *   $categoryIds list    catégories cochées
 *   $tagIds      list    tags cochés
 *   $categories  list    Category::all()
 *   $tags        list    Tag::all()
 *   $sections    array   valeur => libellé
 *   $statuses    array   valeur => libellé
 */

declare(strict_types=1);

$project     = isset($project) && is_array($project) ? $project : null;
$old         = isset($old) && is_array($old) ? $old : [];
$errors      = isset($errors) && is_array($errors) ? $errors : [];
$categoryIds = isset($categoryIds) && is_array($categoryIds) ? $categoryIds : [];
$tagIds      = isset($tagIds) && is_array($tagIds) ? $tagIds : [];
$categories  = isset($categories) && is_array($categories) ? $categories : [];
$tags        = isset($tags) && is_array($tags) ? $tags : [];
$sections    = isset($sections) && is_array($sections) ? $sections : [];
$statuses    = isset($statuses) && is_array($statuses) ? $statuses : [];

$isEdit = $project !== null;
$id     = $isEdit ? (int) $project['id'] : 0;
$action = $isEdit ? '/admin/projets/' . $id : '/admin/projets';
$err    = static fn (string $field): string => (string) ($errors[$field] ?? '');

$categoryOptions = array_map(static fn (array $c): array => [
    'value'   => (int) $c['id'],
    'label'   => (string) $c['name'] . ((string) $c['section'] === 'game' ? ' (jeux)' : ' (technique)'),
    'section' => (string) $c['section'],
], $categories);

$tagOptions = array_map(static fn (array $t): array => [
    'value' => (int) $t['id'],
    'label' => (string) $t['name'],
], $tags);

$headerActions = '';
if ($isEdit) {
    $headerActions .= '<a class="btn btn--secondary" href="/admin/projets/' . $id . '/images">' . icon('image', 16) . 'Images</a>';
    if ((int) $project['is_published'] === 1) {
        $headerActions .= '<a class="btn btn--secondary" href="/projet/' . e((string) $project['slug']) . '" target="_blank" rel="noopener noreferrer">'
            . icon('external', 16) . 'Voir sur le site<span class="sr-only"> (nouvel onglet)</span></a>';
    }
}
?>
<?= partial('admin/page-header', [
    'comment' => $isEdit ? '-- projets/' . (string) $project['slug'] : '-- projets/nouveau',
    'title'   => $isEdit ? 'Modifier le projet' : 'Nouveau projet',
    'lead'    => $isEdit ? (string) $project['title'] : 'Le projet reste un brouillon tant que vous ne le publiez pas. Les images s\'ajoutent à l\'étape suivante.',
    'back'    => ['href' => '/admin/projets', 'label' => 'Projets'],
    'actions' => $headerActions,
]) ?>

<form class="form" method="post" action="<?= e($action) ?>" novalidate data-focus-error>
    <?= csrf_field() ?>
<?php if ($errors !== []): ?>
    <?= partial('site/alert', [
        'type'    => 'error',
        'title'   => 'Le projet n\'a pas été enregistré',
        'message' => count($errors) > 1 ? count($errors) . ' champs sont à corriger ci-dessous.' : 'Un champ est à corriger ci-dessous.',
    ]) ?>
<?php endif; ?>

    <section class="card card--pad stack" aria-labelledby="fs-infos">
        <h2 class="section-title" id="fs-infos">Informations</h2>
        <div class="form-section">
            <div class="form-row form-row--3">
                <?= partial('site/field', ['name' => 'section', 'type' => 'select', 'label' => 'Section', 'required' => true,
                    'options' => $sections, 'value' => old($old, 'section', 'game'), 'error' => $err('section')]) ?>
                <?= partial('site/field', ['name' => 'status', 'type' => 'select', 'label' => 'Statut', 'required' => true,
                    'options' => $statuses, 'value' => old($old, 'status', 'in_progress'), 'error' => $err('status')]) ?>
                <?= partial('site/field', ['name' => 'project_date', 'type' => 'date', 'label' => 'Date', 'optional' => true,
                    'hint' => 'Sert au tri et à l\'affichage (mois et année).',
                    'value' => old($old, 'project_date'), 'error' => $err('project_date')]) ?>
            </div>
            <div class="form-row form-row--2">
                <?= partial('site/field', ['name' => 'title', 'label' => 'Titre', 'required' => true, 'maxlength' => 120, 'counter' => true,
                    'value' => old($old, 'title'), 'error' => $err('title')]) ?>
                <?= partial('site/field', ['name' => 'slug', 'label' => 'Adresse (slug)', 'optional' => true, 'maxlength' => 130, 'class' => 'input--mono',
                    'hint' => $isEdit && (int) $project['is_published'] === 1
                        ? 'Attention : modifier l\'adresse d\'un projet publié casse les liens déjà partagés.'
                        : 'Lettres minuscules, chiffres et tirets. Vide : créée depuis le titre.',
                    'value' => old($old, 'slug'), 'error' => $err('slug'),
                    'attrs' => ['data-slug-source' => '#f-title', 'autocapitalize' => 'none', 'spellcheck' => 'false', 'autocomplete' => 'off']]) ?>
            </div>
            <?= partial('site/field', ['name' => 'summary', 'type' => 'textarea', 'rows' => 3, 'label' => 'Résumé', 'required' => true,
                'maxlength' => 300, 'counter' => true, 'hint' => 'Une ou deux phrases, affichées sur la carte du projet.',
                'value' => old($old, 'summary'), 'error' => $err('summary')]) ?>
        </div>
    </section>

    <section class="card card--pad stack" aria-labelledby="fs-classement">
        <h2 class="section-title" id="fs-classement">Classement</h2>
        <div class="form-section">
            <?= partial('admin/checkbox-group', [
                'name'          => 'category_ids',
                'legend'        => 'Catégories',
                'options'       => $categoryOptions,
                'checked'       => $categoryIds,
                'sectionSelect' => '#f-section',
                'hint'          => 'Seules les catégories de la section choisie sont enregistrées.',
                'empty'         => 'Aucune catégorie : créez-en dans « Catégories ».',
                'error'         => $err('category_ids'),
            ]) ?>
            <?= partial('admin/checkbox-group', [
                'name'    => 'tag_ids',
                'legend'  => 'Tags (technologies)',
                'options' => $tagOptions,
                'checked' => $tagIds,
                'empty'   => 'Aucun tag pour le moment : ajoutez-en ci-dessous.',
                'error'   => $err('tag_ids'),
            ]) ?>
            <?= partial('site/field', ['name' => 'new_tags', 'label' => 'Nouveaux tags', 'optional' => true, 'maxlength' => 1000,
                'hint' => 'Séparés par des virgules, ex. : Lua, Roblox Studio. Un tag existant du même nom est réutilisé (50 caractères maximum par tag).',
                'value' => old($old, 'new_tags'), 'error' => $err('new_tags'), 'attrs' => ['autocomplete' => 'off']]) ?>
        </div>
    </section>

    <section class="card card--pad stack" aria-labelledby="fs-contenu">
        <h2 class="section-title" id="fs-contenu">Contenu de la page projet</h2>
        <?= partial('admin/markdown-help') ?>
        <div class="form-section">
            <?= partial('site/field', ['name' => 'context', 'type' => 'textarea', 'label' => 'Contexte', 'optional' => true,
                'maxlength' => 10000, 'counter' => true, 'class' => 'textarea--lg',
                'hint' => 'Pourquoi ce projet, pour qui, avec quelles contraintes. Markdown accepté.',
                'value' => old($old, 'context'), 'error' => $err('context')]) ?>
            <?= partial('site/field', ['name' => 'work_done', 'type' => 'textarea', 'label' => 'Ce que j\'ai fait', 'optional' => true,
                'maxlength' => 10000, 'counter' => true, 'class' => 'textarea--lg',
                'hint' => 'Réalisation, choix techniques, fonctionnement. Markdown accepté.',
                'value' => old($old, 'work_done'), 'error' => $err('work_done')]) ?>
            <?= partial('site/field', ['name' => 'learnings', 'type' => 'textarea', 'label' => 'Ce que j\'ai appris', 'optional' => true,
                'maxlength' => 10000, 'counter' => true,
                'hint' => 'Compétences acquises, difficultés surmontées. Markdown accepté.',
                'value' => old($old, 'learnings'), 'error' => $err('learnings')]) ?>
        </div>
    </section>

    <section class="card card--pad stack" aria-labelledby="fs-medias">
        <h2 class="section-title" id="fs-medias">Vidéo et lien</h2>
        <div class="form-section">
            <?= partial('site/field', ['name' => 'youtube_id', 'label' => 'Vidéo YouTube', 'optional' => true, 'maxlength' => 255, 'class' => 'input--mono',
                'hint' => 'Collez l\'adresse de la vidéo (https://youtu.be/…) ou son identifiant de 11 caractères. Elle ne se charge qu\'au clic du visiteur.',
                'value' => old($old, 'youtube_id'), 'error' => $err('youtube_id'),
                'attrs' => ['inputmode' => 'url', 'autocapitalize' => 'none', 'spellcheck' => 'false', 'autocomplete' => 'off']]) ?>
            <div class="form-row form-row--2">
                <?= partial('site/field', ['name' => 'external_url', 'type' => 'url', 'label' => 'Lien externe', 'optional' => true, 'maxlength' => 255,
                    'placeholder' => 'https://', 'hint' => 'Page du jeu sur Roblox, dépôt GitHub… (adresse en https://).',
                    'value' => old($old, 'external_url'), 'error' => $err('external_url'), 'attrs' => ['autocomplete' => 'off']]) ?>
                <?= partial('site/field', ['name' => 'external_label', 'label' => 'Libellé du lien', 'optional' => true, 'maxlength' => 60, 'counter' => true,
                    'hint' => 'Texte du bouton, ex. : Jouer sur Roblox.',
                    'value' => old($old, 'external_label'), 'error' => $err('external_label')]) ?>
            </div>
        </div>
    </section>

    <section class="card card--pad stack" aria-labelledby="fs-publication">
        <h2 class="section-title" id="fs-publication">Publication</h2>
        <div class="form-section">
            <?= partial('admin/switch', ['name' => 'is_published', 'label' => 'Publié', 'checked' => old($old, 'is_published') === '1',
                'hint' => 'Visible sur le site public. Sinon : brouillon, visible uniquement ici.']) ?>
            <?= partial('admin/switch', ['name' => 'is_featured', 'label' => 'Mis en avant', 'checked' => old($old, 'is_featured') === '1',
                'hint' => 'Affiché dans « Projets mis en avant » sur l\'accueil (s\'il est publié).']) ?>
            <div class="form-row form-row--3">
                <?= partial('site/field', ['name' => 'position', 'type' => 'number', 'label' => 'Ordre d\'affichage', 'optional' => true,
                    'hint' => 'De 0 à 999 : les plus petits d\'abord.',
                    'value' => old($old, 'position', '0'), 'error' => $err('position'),
                    'attrs' => ['min' => '0', 'max' => '999', 'step' => '1', 'inputmode' => 'numeric']]) ?>
            </div>
        </div>
    </section>

    <div class="form__actions form__actions--sticky">
        <button class="btn btn--primary" type="submit" data-busy-label="Enregistrement…"><?= icon('check', 16) ?><?= $isEdit ? 'Enregistrer' : 'Créer le projet' ?></button>
        <a class="btn btn--secondary" href="/admin/projets">Annuler</a>
    </div>
</form>

<?php if ($isEdit): ?>
<section class="card card--pad stack danger-zone" aria-labelledby="titre-suppression">
    <h2 class="section-title" id="titre-suppression">Supprimer le projet</h2>
    <p class="text-soft">Le projet, ses liaisons et ses images seront supprimés définitivement.</p>
    <div>
        <?= partial('admin/post-button', [
            'action'  => '/admin/projets/' . $id . '/supprimer',
            'label'   => 'Supprimer ce projet',
            'icon'    => 'trash',
            'variant' => 'danger',
            'size'    => 'md',
            'confirm' => 'Supprimer définitivement le projet « ' . (string) $project['title'] . ' » et toutes ses images ? Cette action est irréversible.',
        ]) ?>
    </div>
</section>
<?php endif; ?>
