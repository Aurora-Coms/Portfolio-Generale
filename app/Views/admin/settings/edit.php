<?php
/**
 * Réglages du site, regroupés par thème, puis gestion du CV (PDF).
 *
 * @var array<string, mixed>  $old
 * @var array<string, string> $errors
 * @var array{exists: bool, size: int, updated_at: string, max: int} $cv
 * @var string                $cvError
 * @var int                   $metaMax
 */

declare(strict_types=1);

$err = static fn (string $key): string => $errors[$key] ?? '';

$sizeLabel = static function (int $bytes): string {
    return $bytes >= 1048576
        ? str_replace('.', ',', (string) round($bytes / 1048576, 1)) . ' Mo'
        : max(1, (int) round($bytes / 1024)) . ' Ko';
};
?>
<?= partial('admin/page-header', [
    'comment' => '-- config.lua',
    'title'   => 'Réglages',
    'lead'    => 'Textes de l\'accueil, bandeau de recherche de stage, liens, référencement, mentions légales et CV.',
    'actions' => '<a class="btn btn--secondary" href="/" target="_blank" rel="noopener noreferrer">' . icon('external', 16) . 'Voir le site<span class="sr-only"> (nouvel onglet)</span></a>',
]) ?>

<nav class="page-toc" aria-label="Sections des réglages">
    <ul class="cluster">
        <li><a class="btn btn--ghost btn--sm" href="#reglages-accueil">Accueil</a></li>
        <li><a class="btn btn--ghost btn--sm" href="#reglages-bandeau">Bandeau de stage</a></li>
        <li><a class="btn btn--ghost btn--sm" href="#reglages-liens">Liens et contact</a></li>
        <li><a class="btn btn--ghost btn--sm" href="#reglages-seo">Référencement</a></li>
        <li><a class="btn btn--ghost btn--sm" href="#reglages-mentions">Mentions légales</a></li>
        <li><a class="btn btn--ghost btn--sm" href="#cv">CV</a></li>
    </ul>
</nav>

<form class="form card card--pad" method="post" action="/admin/reglages" novalidate data-focus-error>
    <?= csrf_field() ?>
<?php if ($errors !== []): ?>
    <?= partial('site/alert', ['type' => 'error', 'title' => 'Les réglages n\'ont pas été enregistrés', 'message' => 'Corrigez les champs signalés ci-dessous.']) ?>
<?php endif; ?>

    <section class="form-section" aria-labelledby="reglages-accueil">
        <h2 class="section-title" id="reglages-accueil">Accueil</h2>
        <div class="form-row form-row--2">
            <?= partial('site/field', ['name' => 'site_name', 'label' => 'Nom affiché', 'required' => true, 'maxlength' => 80,
                'hint' => 'Titre principal de l\'accueil et nom du site.',
                'value' => old($old, 'site_name'), 'error' => $err('site_name')]) ?>
            <?= partial('site/field', ['name' => 'site_class', 'label' => 'Formation (sous le nom)', 'optional' => true, 'maxlength' => 60,
                'hint' => 'Affichée à côté du logo, ex. BTS CIEL · IR.',
                'value' => old($old, 'site_class'), 'error' => $err('site_class')]) ?>
        </div>
        <div class="form-row form-row--2">
            <?= partial('site/field', ['name' => 'hero_kicker', 'label' => 'Accroche', 'optional' => true, 'maxlength' => 60, 'class' => 'input--mono',
                'hint' => 'Petite ligne au-dessus du nom, ex. -- Bonjour, je suis',
                'value' => old($old, 'hero_kicker'), 'error' => $err('hero_kicker')]) ?>
            <?= partial('site/field', ['name' => 'hero_title', 'label' => 'Titre', 'required' => true, 'maxlength' => 150, 'counter' => true,
                'hint' => 'Sous le nom, en couleur d\'accent.',
                'value' => old($old, 'hero_title'), 'error' => $err('hero_title')]) ?>
        </div>
        <?= partial('site/field', ['name' => 'hero_tagline', 'type' => 'textarea', 'label' => 'Présentation courte', 'optional' => true,
            'maxlength' => 300, 'counter' => true, 'rows' => 3,
            'hint' => 'Deux phrases maximum. Texte simple, sur une seule ligne.',
            'value' => old($old, 'hero_tagline'), 'error' => $err('hero_tagline')]) ?>
    </section>

    <section class="form-section" aria-labelledby="reglages-bandeau">
        <h2 class="section-title" id="reglages-bandeau">Bandeau de recherche de stage</h2>
        <?= partial('admin/switch', ['name' => 'banner_enabled', 'label' => 'Afficher le bandeau sur tout le site',
            'checked' => old($old, 'banner_enabled') === '1',
            'hint' => 'Exemple : « Recherche de stage en développement informatique / réseaux : du 18 mai au 26 juin 2026 ».']) ?>
        <div class="form-row form-row--2">
            <?= partial('site/field', ['name' => 'banner_title', 'label' => 'Titre du bandeau', 'maxlength' => 80,
                'hint' => 'Obligatoire si le bandeau est affiché.',
                'value' => old($old, 'banner_title'), 'error' => $err('banner_title')]) ?>
            <?= partial('site/field', ['name' => 'banner_text', 'label' => 'Texte du bandeau', 'optional' => true, 'maxlength' => 200,
                'value' => old($old, 'banner_text'), 'error' => $err('banner_text')]) ?>
        </div>
        <div class="form-row form-row--2">
            <?= partial('site/field', ['name' => 'internship_start', 'type' => 'date', 'label' => 'Début du stage', 'optional' => true,
                'value' => old($old, 'internship_start'), 'error' => $err('internship_start')]) ?>
            <?= partial('site/field', ['name' => 'internship_end', 'type' => 'date', 'label' => 'Fin du stage', 'optional' => true,
                'hint' => 'Doit être après le début.',
                'value' => old($old, 'internship_end'), 'error' => $err('internship_end')]) ?>
        </div>
    </section>

    <section class="form-section" aria-labelledby="reglages-liens">
        <h2 class="section-title" id="reglages-liens">Liens et contact</h2>
        <div class="form-row form-row--2">
            <?= partial('site/field', ['name' => 'linkedin_url', 'type' => 'url', 'label' => 'Profil LinkedIn', 'optional' => true,
                'placeholder' => 'https://www.linkedin.com/in/…', 'hint' => 'Laissez vide pour masquer le lien.',
                'value' => old($old, 'linkedin_url'), 'error' => $err('linkedin_url'),
                'attrs' => ['autocapitalize' => 'none', 'spellcheck' => 'false', 'inputmode' => 'url']]) ?>
            <?= partial('site/field', ['name' => 'github_url', 'type' => 'url', 'label' => 'Profil GitHub', 'optional' => true,
                'placeholder' => 'https://github.com/…', 'hint' => 'Laissez vide pour masquer le lien.',
                'value' => old($old, 'github_url'), 'error' => $err('github_url'),
                'attrs' => ['autocapitalize' => 'none', 'spellcheck' => 'false', 'inputmode' => 'url']]) ?>
        </div>
        <?= partial('site/field', ['name' => 'contact_email', 'type' => 'email', 'label' => 'Adresse e-mail de contact', 'optional' => true,
            'hint' => 'Reçoit une copie des messages du formulaire si l\'envoi d\'e-mails est configuré. Laissez vide pour ne pas l\'afficher.',
            'value' => old($old, 'contact_email'), 'error' => $err('contact_email'),
            'attrs' => ['autocomplete' => 'email', 'autocapitalize' => 'none', 'spellcheck' => 'false']]) ?>
    </section>

    <section class="form-section" aria-labelledby="reglages-seo">
        <h2 class="section-title" id="reglages-seo">Référencement</h2>
        <?= partial('site/field', ['name' => 'meta_description', 'type' => 'textarea', 'label' => 'Description pour les moteurs de recherche', 'optional' => true,
            'maxlength' => $metaMax, 'counter' => true, 'rows' => 3,
            'hint' => 'Texte affiché sous le titre dans Google et dans l\'aperçu LinkedIn. Idéalement entre 120 et ' . $metaMax . ' caractères.',
            'value' => old($old, 'meta_description'), 'error' => $err('meta_description')]) ?>
    </section>

    <section class="form-section" aria-labelledby="reglages-mentions">
        <h2 class="section-title" id="reglages-mentions">Mentions légales</h2>
        <?= partial('site/field', ['name' => 'legal_publisher', 'label' => 'Éditeur du site', 'required' => true, 'maxlength' => 150,
            'hint' => 'Nom de la personne responsable du site.',
            'value' => old($old, 'legal_publisher'), 'error' => $err('legal_publisher')]) ?>
        <?= partial('site/field', ['name' => 'legal_host', 'type' => 'textarea', 'label' => 'Hébergeur', 'required' => true,
            'maxlength' => 1000, 'rows' => 4,
            'hint' => 'Nom, adresse et site de l\'hébergeur, une information par ligne.',
            'value' => old($old, 'legal_host'), 'error' => $err('legal_host')]) ?>
    </section>

    <div class="form__actions form__actions--sticky">
        <button class="btn btn--primary" type="submit"><?= icon('check', 16) ?>Enregistrer les réglages</button>
        <a class="btn btn--secondary" href="/admin/reglages">Annuler</a>
    </div>
</form>

<section class="card cv-card" id="cv" aria-labelledby="titre-cv">
    <div class="card__head">
        <h2 class="card__title" id="titre-cv">CV (PDF)</h2>
<?php if ($cv['exists']): ?>
        <span class="badge badge--published">en ligne</span>
<?php else: ?>
        <span class="badge badge--draft">aucun CV</span>
<?php endif; ?>
    </div>
    <div class="card__body stack">
<?php if ($cv['exists']): ?>
        <dl class="dl">
            <div class="dl__row"><dt>fichier</dt><dd class="mono">cv.pdf · <?= e($sizeLabel($cv['size'])) ?></dd></div>
            <div class="dl__row"><dt>mis à jour le</dt><dd><?= $cv['updated_at'] !== '' ? e(date_fr($cv['updated_at'])) : 'date inconnue' ?></dd></div>
            <div class="dl__row"><dt>adresse publique</dt><dd><a href="/cv" target="_blank" rel="noopener noreferrer">/cv<span class="sr-only"> (tester le téléchargement, nouvel onglet)</span></a></dd></div>
        </dl>
<?php else: ?>
        <p class="text-soft">Aucun CV n'est en ligne : le bouton « CV (PDF) » du site ne fonctionnera pas tant qu'un fichier n'aura pas été envoyé.</p>
<?php endif; ?>

        <form class="form" method="post" action="/admin/reglages/cv#cv" enctype="multipart/form-data" novalidate data-focus-error>
            <?= csrf_field() ?>
            <?= partial('site/field', ['name' => 'cv', 'type' => 'file', 'label' => $cv['exists'] ? 'Remplacer le CV' : 'Envoyer le CV',
                'required' => true,
                'hint' => 'Fichier PDF, ' . (int) ($cv['max'] / 1048576) . ' Mo maximum. Le fichier est stocké hors du dossier public et servi uniquement par /cv.',
                'error' => $cvError,
                'attrs' => ['accept' => 'application/pdf,.pdf', 'data-file-preview' => true, 'data-max-size' => (string) $cv['max']]]) ?>
            <div class="form__actions">
                <button class="btn btn--primary" type="submit" data-busy-label="Envoi en cours…"><?= icon('download', 16, 'icon icon--flip') ?>Mettre en ligne</button>
            </div>
        </form>
    </div>
<?php if ($cv['exists']): ?>
    <div class="card__foot">
<?= partial('admin/post-button', [
    'action'  => '/admin/reglages/cv/supprimer',
    'label'   => 'Supprimer le CV',
    'icon'    => 'trash',
    'variant' => 'danger',
    'confirm' => 'Supprimer le CV du site ? Le lien /cv ne fonctionnera plus.',
]) ?>
    </div>
<?php endif; ?>
</section>
