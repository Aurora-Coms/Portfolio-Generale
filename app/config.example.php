<?php
/**
 * Modèle de configuration.
 *
 * 1. Copiez ce fichier en « config.php » dans le même dossier (app/).
 * 2. Remplissez les valeurs ci-dessous.
 * 3. Ne partagez jamais config.php (il contient vos mots de passe) et ne le
 *    versionnez pas : il est listé dans .gitignore.
 *
 * Ce dossier « app/ » doit se trouver EN DEHORS de public_html : il n'est
 * alors pas accessible depuis le web (voir README.md).
 */

declare(strict_types=1);

return [
    // 'production' : erreurs masquées (journalisées dans app/storage/logs).
    // 'development' : erreurs affichées (uniquement en local !).
    'env' => 'production',

    // Adresse publique du site, SANS barre oblique finale.
    // Sert aux liens absolus (Open Graph, sitemap, e-mails).
    'base_url' => 'https://www.votre-domaine.fr',

    // Base de données (hPanel > Bases de données > MySQL).
    // Sur Hostinger, le nom et l'utilisateur ressemblent à « u123456789_portfolio ».
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'u123456789_portfolio',
        'user'    => 'u123456789_admin',
        'pass'    => 'mot-de-passe-de-la-base',
        'charset' => 'utf8mb4',
    ],

    // Envoi des e-mails du formulaire de contact.
    // 'smtp' (recommandé) : créez une adresse dans hPanel > E-mails, puis
    //   utilisez smtp.hostinger.com, port 465, chiffrement 'ssl'.
    // 'mail' : fonction mail() de PHP (moins fiable, souvent classé en spam).
    // 'none' : aucun e-mail, les messages restent consultables dans /admin.
    'mail' => [
        'transport'  => 'smtp',
        'host'       => 'smtp.hostinger.com',
        'port'       => 465,
        'encryption' => 'ssl',            // 'ssl' (port 465) ou 'tls' (STARTTLS, port 587)
        'username'   => 'contact@votre-domaine.fr',
        'password'   => 'mot-de-passe-de-la-boite-mail',
        'from_email' => 'contact@votre-domaine.fr',
        'from_name'  => 'Portfolio Alexandre Derbez',
        'timeout'    => 10,
    ],

    'security' => [
        // Clé secrète de l'application : 64 caractères hexadécimaux.
        // Générez-la UNE fois (puis ne la changez plus, sinon la double
        // authentification devra être reconfigurée) :
        //   https://www.random.org/bytes/ ou, en SSH : php -r "echo bin2hex(random_bytes(32));"
        'app_key' => 'REMPLACEZ_PAR_64_CARACTERES_HEXADECIMAUX_ALEATOIRES_0123456789abcdef',

        // Jeton à usage unique pour public_html/setup-admin.php (création du
        // premier compte admin). Mettez une longue chaîne aléatoire (32+ caractères),
        // puis videz-la ('') une fois le compte créé.
        'setup_token' => '',

        // Durées de session de l'administration (en secondes).
        'session_idle_timeout'     => 1800,   // 30 min d'inactivité
        'session_absolute_timeout' => 28800,  // 8 h maximum

        // Mettre true UNIQUEMENT si le site est derrière un proxy/CDN de
        // confiance qui transmet X-Forwarded-Proto (sinon : falsifiable).
        'trust_proxy' => false,
    ],
];
