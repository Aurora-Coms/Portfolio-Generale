<?php
/**
 * Codes de secours, affichés UNE SEULE FOIS (réponse directe à un POST, page
 * jamais mise en cache). Ils sont stockés hachés : impossible de les réafficher.
 *
 * @var list<string> $codes
 * @var bool         $justEnabled  true juste après l'activation de la double authentification
 */

declare(strict_types=1);
?>
<?= partial('admin/page-header', [
    'comment' => '-- compte/codes-de-secours',
    'title'   => $justEnabled ? 'Double authentification activée' : 'Nouveaux codes de secours',
    'lead'    => $justEnabled
        ? 'Dès votre prochaine connexion, le code de l\'application vous sera demandé après le mot de passe.'
        : 'Les anciens codes de secours ne fonctionnent plus.',
]) ?>

<section class="card codes-card" aria-labelledby="titre-codes">
    <div class="card__head">
        <h2 class="card__title" id="titre-codes">Vos 8 codes de secours</h2>
        <span class="badge badge--warning">affichés une seule fois</span>
    </div>
    <div class="card__body stack">
        <?= partial('site/alert', [
            'type'    => 'warning',
            'title'   => 'Notez ces codes maintenant',
            'message' => 'Ils ne seront plus jamais affichés. Chacun ne sert qu\'une fois et remplace le code à 6 chiffres si vous perdez votre téléphone.',
        ]) ?>
        <ul class="codes" id="codes-secours" aria-label="Codes de secours">
<?php foreach ($codes as $code): ?>
            <li><?= e($code) ?></li>
<?php endforeach; ?>
        </ul>
        <div class="btn-row">
            <button class="btn btn--secondary" type="button" data-copy-target="#codes-secours" data-copy-done="Codes copiés" hidden><?= icon('file', 16) ?><span>Copier les codes</span></button>
            <button class="btn btn--secondary" type="button" data-print hidden><span>Imprimer</span></button>
        </div>
        <p class="sr-only" aria-live="polite" data-copy-status></p>
        <div class="prose">
            <h3>Où les conserver ?</h3>
            <ul>
                <li><strong>Hors ligne</strong> : sur papier, rangé chez vous, ou dans un gestionnaire de mots de passe (Bitwarden, KeePassXC…).</li>
                <li><strong>Jamais</strong> dans le même téléphone que l'application d'authentification, ni par e-mail, ni en capture d'écran partagée.</li>
                <li>Une fois connecté avec un code de secours, régénérez-en depuis « Mon compte ».</li>
            </ul>
        </div>
    </div>
    <div class="card__foot">
        <a class="btn btn--primary" href="/admin/compte"><?= icon('check', 16) ?>J'ai conservé mes codes</a>
    </div>
</section>
