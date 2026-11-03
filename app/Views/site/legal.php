<?php
/**
 * Mentions légales (LCEN), données personnelles (RGPD), cookies et sécurité.
 *
 * Variables (LegalController::index) :
 *   $publisher string  réglage legal_publisher (éditeur et directeur de la publication)
 *   $host      string  réglage legal_host (multiligne : nom, adresse, site de l'hébergeur)
 *   $updatedAt string  date de dernière mise à jour (AAAA-MM-JJ)
 */

declare(strict_types=1);

$publisher = trim((string) ($publisher ?? '')) ?: 'Alexandre Derbez';
$host      = trim((string) ($host ?? ''));
$updatedAt = (string) ($updatedAt ?? '');

// Hébergeur : une ligne par information ; une ligne qui est une adresse web devient un lien.
$hostLines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $host) ?: []), static fn (string $l): bool => $l !== ''));

$toc = [
    'editeur'     => 'Éditeur du site',
    'hebergement' => 'Hébergement',
    'propriete'   => 'Propriété intellectuelle',
    'donnees'     => 'Données personnelles',
    'cookies'     => 'Cookies',
    'securite'    => 'Sécurité',
];
?>
<?= partial('site/page-header', [
    'comment' => '-- mentions-legales.md',
    'title'   => 'Mentions légales',
    'lead'    => 'Informations légales, protection de vos données et cookies.',
]) ?>

<div class="legal">
    <nav class="card card--pad legal__toc" aria-labelledby="titre-sommaire">
        <h2 class="legal__toc-title" id="titre-sommaire">Sommaire</h2>
        <ol class="legal__toc-list">
<?php foreach ($toc as $anchor => $label): ?>
            <li><a href="#<?= e($anchor) ?>"><?= e($label) ?></a></li>
<?php endforeach; ?>
        </ol>
    </nav>

    <div class="legal__content">
        <section class="legal__section" id="editeur" aria-labelledby="titre-editeur">
            <h2 class="content-block__title" id="titre-editeur">Éditeur du site</h2>
            <div class="prose">
                <p>Ce site est le portfolio personnel, non commercial, de <strong><?= e($publisher) ?></strong>, étudiant en BTS CIEL option IR au Campus St Jean-Baptiste de La Salle, à Avignon.</p>
                <ul>
                    <li>Directeur de la publication : <?= e($publisher) ?>.</li>
                    <li>Contact : par le <a href="/contact">formulaire de contact</a>.</li>
                </ul>
                <p>Le site est publié à titre non professionnel : conformément à l'article 6, III, 2 de la loi n° 2004-575 du 21 juin 2004 pour la confiance dans l'économie numérique (LCEN), les coordonnées postales de l'éditeur ont été communiquées à l'hébergeur.</p>
            </div>
        </section>

        <section class="legal__section" id="hebergement" aria-labelledby="titre-hebergement">
            <h2 class="content-block__title" id="titre-hebergement">Hébergement</h2>
            <div class="prose">
<?php if ($hostLines !== []): ?>
                <p class="legal__address">
<?php foreach ($hostLines as $i => $line): ?>
<?php if (preg_match('#^https?://[^\s"<>]+$#i', $line)): ?>
                    <a href="<?= e($line) ?>" rel="noopener noreferrer"><?= e($line) ?></a><?= $i < count($hostLines) - 1 ? '<br>' : '' ?>
<?php else: ?>
                    <?= e($line) ?><?= $i < count($hostLines) - 1 ? '<br>' : '' ?>
<?php endif; ?>
<?php endforeach; ?>
                </p>
<?php else: ?>
                <p>Informations sur l'hébergeur en cours de mise à jour.</p>
<?php endif; ?>
            </div>
        </section>

        <section class="legal__section" id="propriete" aria-labelledby="titre-propriete">
            <h2 class="content-block__title" id="titre-propriete">Propriété intellectuelle</h2>
            <div class="prose">
                <p>Les textes, le code, les captures d'écran et les vidéos présentés sur ce site sont la propriété de <?= e($publisher) ?>, sauf mention contraire. Toute reproduction ou réutilisation, même partielle, nécessite son accord préalable.</p>
                <p>Roblox est une marque de Roblox Corporation. Les univers dont s'inspirent certains projets (<em>Doctor Who</em>, <em>Loki</em> et la Time Variance Authority) appartiennent à leurs ayants droit respectifs : ces projets sont des créations personnelles de fan, sans lien avec eux.</p>
                <p>Les polices Inter et JetBrains Mono sont utilisées sous licence SIL Open Font License 1.1 et sont servies directement par ce site.</p>
            </div>
        </section>

        <section class="legal__section" id="donnees" aria-labelledby="titre-donnees">
            <h2 class="content-block__title" id="titre-donnees">Données personnelles</h2>
            <div class="prose">
                <p>Le responsable du traitement est <?= e($publisher) ?>. Le site ne collecte des données personnelles que lorsque vous envoyez un message avec le <a href="/contact">formulaire de contact</a>.</p>
                <ul>
                    <li><strong>Données collectées</strong> : votre nom, votre adresse e-mail, le sujet et le contenu de votre message, ainsi que leur date d'envoi.</li>
                    <li><strong>Finalité</strong> : uniquement vous répondre (par exemple au sujet d'une proposition de stage).</li>
                    <li><strong>Base légale</strong> : votre consentement, donné en cochant la case prévue avant l'envoi (article 6.1.a du RGPD). Vous pouvez le retirer à tout moment.</li>
                    <li><strong>Destinataire</strong> : moi seul. L'hébergeur stocke les données pour mon compte, sans pouvoir les utiliser.</li>
                    <li><strong>Durée de conservation</strong> : 12 mois au plus après la réception du message, puis suppression automatique.</li>
                    <li><strong>Aucun transfert ni revente</strong> : vos données ne sont jamais cédées, vendues ni utilisées à des fins commerciales ou publicitaires.</li>
                </ul>
                <p>Pour limiter le spam, une empreinte chiffrée de votre adresse IP (jamais l'adresse en clair) est enregistrée temporairement lors de l'envoi d'un message, puis effacée automatiquement. Ce traitement repose sur l'intérêt légitime d'assurer la sécurité du site. L'hébergeur peut par ailleurs conserver des journaux techniques de connexion pour la sécurité de son service.</p>
                <p>Vous disposez d'un droit d'accès, de rectification, d'effacement, de limitation et d'opposition sur vos données. Pour l'exercer, écrivez-moi par le <a href="/contact">formulaire de contact</a> en précisant votre demande.</p>
                <p>Si vous estimez, après m'avoir contacté, que vos droits ne sont pas respectés, vous pouvez adresser une réclamation à la CNIL : <a href="https://www.cnil.fr" rel="noopener noreferrer">www.cnil.fr</a>.</p>
            </div>
        </section>

        <section class="legal__section" id="cookies" aria-labelledby="titre-cookies">
            <h2 class="content-block__title" id="titre-cookies">Cookies</h2>
            <div class="prose">
                <ul>
                    <li><strong>Aucun traceur</strong> : ce site n'utilise ni cookie publicitaire, ni outil de mesure d'audience, ni bouton de réseau social.</li>
                    <li><strong>Un cookie de session technique</strong>, strictement nécessaire, est déposé uniquement lorsque vous ouvrez le formulaire de contact ou l'administration. Il sert à protéger l'envoi du formulaire contre les requêtes frauduleuses. Il est supprimé à la fermeture du navigateur et, étant indispensable au service demandé, il ne nécessite pas de consentement.</li>
                    <li><strong>Vidéos YouTube</strong> : elles ne sont chargées qu'après un clic sur le bouton de lecture, depuis le domaine youtube-nocookie.com. Avant ce clic, aucune donnée n'est envoyée à YouTube. Après, YouTube (Google) applique sa propre politique de confidentialité.</li>
                </ul>
            </div>
        </section>

        <section class="legal__section" id="securite" aria-labelledby="titre-securite">
            <h2 class="content-block__title" id="titre-securite">Sécurité</h2>
            <div class="prose">
                <p>Étudiant en cybersécurité, j'applique à ce site les bonnes pratiques que j'apprends :</p>
                <ul>
                    <li>connexion chiffrée en HTTPS, imposée par le navigateur (HSTS) ;</li>
                    <li>politique de sécurité du contenu (CSP) stricte : aucun script externe, aucune ressource tierce chargée sans action de votre part ;</li>
                    <li>formulaire protégé par un jeton anti-falsification (CSRF), un filtrage anti-spam et une limitation du nombre d'envois ;</li>
                    <li>requêtes préparées pour la base de données et échappement de tout contenu affiché ;</li>
                    <li>administration protégée par un mot de passe haché et une double authentification.</li>
                </ul>
                <p>Vous pensez avoir trouvé une faille ? Merci de me la signaler de façon responsable par le <a href="/contact">formulaire de contact</a>, sans l'exploiter ni la divulguer.</p>
            </div>
        </section>

<?php if ($updatedAt !== ''): ?>
        <p class="legal__updated">Dernière mise à jour : <time datetime="<?= e($updatedAt) ?>"><?= e(date_fr($updatedAt)) ?></time></p>
<?php endif; ?>
    </div>
</div>
