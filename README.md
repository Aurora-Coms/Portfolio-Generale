# Portfolio · Alexandre Derbez

Site personnel d'**Alexandre Derbez**, étudiant en 1re année de **BTS CIEL option IR**
(Cybersécurité, Informatique et réseaux, Électronique) au Campus St Jean-Baptiste
de La Salle à Avignon. Il présente ses projets (jeux Roblox entièrement scriptés en
Lua, projets réseau et systèmes), son parcours, ses compétences, et permet aux
recruteurs et maîtres de stage de le contacter.

Le site est écrit en **PHP 8 natif + MySQL**, sans framework ni Composer, avec du
CSS et du JavaScript « vanilla ». Il a été pensé pour un **hébergement mutualisé
Hostinger** et pour être **exemplaire en sécurité** : chaque mesure est expliquée
dans [`docs/SECURITE.md`](docs/SECURITE.md).

## Sommaire

1. [Fonctionnalités](#1-fonctionnalités)
2. [Arborescence](#2-arborescence)
3. [Prérequis](#3-prérequis)
4. [Mise en ligne sur Hostinger, pas à pas](#4-mise-en-ligne-sur-hostinger-pas-à-pas)
5. [Mettre le lien sur LinkedIn](#5-mettre-le-lien-sur-linkedin)
6. [Utiliser l'administration](#6-utiliser-ladministration)
7. [Développement en local](#7-développement-en-local)
8. [Maintenance](#8-maintenance)
9. [Dépannage](#9-dépannage)
10. [Licence et crédits](#10-licence-et-crédits)

Autres documents :
[`docs/SECURITE.md`](docs/SECURITE.md) (mesures de sécurité) ·
[`docs/ROADMAP.md`](docs/ROADMAP.md) (feuille de route) ·
[`docs/CONTENU-A-VERIFIER.md`](docs/CONTENU-A-VERIFIER.md) (textes à relire) ·
[`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) (contrat technique) ·
[`docs/STYLEGUIDE.md`](docs/STYLEGUIDE.md) (classes CSS et composants).

---

## 1. Fonctionnalités

### Site public

| Page | Adresse | Contenu |
|---|---|---|
| Accueil | `/` | présentation, bandeau « Recherche de stage », projets mis en avant, début du parcours |
| Projets de jeux | `/projets/jeux` | jeux Roblox, filtrables par catégorie (`?categorie=…`) |
| Projets techniques | `/projets/techniques` | réseau, cybersécurité, systèmes, TP et CTF |
| Détail d'un projet | `/projet/{adresse}` | description, galerie d'images, vidéo YouTube (chargée après un clic) |
| Parcours | `/parcours` | études et stages, sous forme de frise |
| Compétences | `/competences` | compétences par thème et sous-catégorie, avec ou sans niveau |
| À propos | `/a-propos` | présentation détaillée et centres d'intérêt |
| Contact | `/contact` | formulaire protégé contre le spam |
| CV | `/cv` | téléchargement du CV en PDF (s'il a été envoyé) |
| Mentions légales | `/mentions-legales` | éditeur, hébergeur, données personnelles |
| Référencement | `/sitemap.xml`, `/robots.txt` | plan du site pour les moteurs de recherche |

Le site est responsive (pensé d'abord pour le téléphone, car beaucoup de visiteurs
arrivent depuis LinkedIn), accessible (navigation au clavier, contrastes vérifiés,
lecteurs d'écran) et produit un **aperçu soigné quand on partage un lien** (Open Graph).

### Administration (`/admin`)

- Connexion protégée : limitation des tentatives, **double authentification** (TOTP)
  avec QR code et codes de secours.
- Projets : création, brouillons, publication, mise en avant sur l'accueil,
  catégories, tags, images (redimensionnées automatiquement), vidéo YouTube.
- Parcours, compétences, centres d'intérêt, texte « À propos » (Markdown simplifié).
- Messages reçus par le formulaire de contact (notification par e-mail possible).
- Réglages : textes de l'accueil, bandeau de stage, liens LinkedIn et GitHub,
  e-mail de contact, description pour Google, mentions légales, CV en PDF.

---

## 2. Arborescence

```text
portfolio-generale/
├── app/                  code de l'application : JAMAIS dans public_html
│   ├── bootstrap.php     démarrage (chargement des classes, configuration, erreurs)
│   ├── config.example.php  modèle de configuration (à copier en config.php)
│   ├── helpers.php       fonctions utilitaires (e(), csrf_field()…)
│   ├── routes.php        liste des adresses du site
│   ├── Core/             noyau : routeur, base de données, sessions, sécurité
│   ├── Models/           accès aux tables (une classe par table)
│   ├── Controllers/      Site/ (pages publiques) et Admin/ (administration)
│   ├── Views/            gabarits HTML (layouts/, partials/, site/, admin/)
│   ├── storage/          logs/, sessions/, cv/ (données privées, non publiques)
│   └── tools/            create-admin.php, dev-router.php, generate-images.php
├── public_html/          SEULE partie publiée sur le web
│   ├── index.php         point d'entrée unique
│   ├── .htaccess         HTTPS forcé, en-têtes de sécurité, réécriture d'adresses
│   ├── setup-admin.php   création du 1er compte admin (À SUPPRIMER après usage)
│   ├── assets/           css/, js/, fonts/, img/
│   └── uploads/projects/ images des projets (exécution de code interdite)
├── database.sql          structure de la base + contenus de départ
├── database/migrations/  mises à jour d'une base déjà en ligne (docs/MIGRATIONS.md)
├── docs/                 documentation (non envoyée sur le serveur)
└── tests/                tests automatiques (non envoyés sur le serveur)
```

---

## 3. Prérequis

- Une offre d'**hébergement web Hostinger** (mutualisé « Premium », « Business »…)
  avec **PHP 8.1 au minimum** (8.3 conseillé : c'est la version utilisée pour les
  tests ; une version plus récente proposée par Hostinger convient aussi) et
  **MySQL/MariaDB**.
- Un **nom de domaine** relié à l'hébergement (par exemple `alexandre-derbez.fr`).
- Pour envoyer les fichiers : un navigateur (Gestionnaire de fichiers de hPanel)
  ou un client FTP comme **FileZilla**.
- Extensions PHP utilisées (actives par défaut chez Hostinger) : `pdo_mysql`,
  `mbstring`, `openssl`, `gd` (avec WebP), `fileinfo`, `session`.

---

## 4. Mise en ligne sur Hostinger, pas à pas

Dans ce guide, remplacez `votre-domaine.fr` par votre vrai nom de domaine.
Les libellés des menus de hPanel peuvent légèrement changer avec les mises à jour
de Hostinger : en cas de doute, utilisez la barre de recherche de hPanel.

### Étape 1 · Choisir la version de PHP

1. Connectez-vous à **hPanel**, ouvrez **Sites web**, puis **Gérer** à côté de votre domaine.
2. Dans le menu de gauche : **Avancé > Configuration PHP**.
3. Onglet **Version PHP** : choisissez **8.3** (ou plus récent), puis enregistrez.
4. Onglet **Options PHP** : vérifiez que **`display_errors` est DÉCOCHÉ**, puis enregistrez.

> [!WARNING]
> `display_errors` doit rester désactivé en ligne : un message d'erreur affiché peut
> révéler des chemins de fichiers ou des informations techniques à un attaquant.
> Le site masque déjà les erreurs lui-même (`'env' => 'production'`), c'est une
> double sécurité.

### Étape 2 · Créer la base de données

1. hPanel > **Bases de données > Bases de données MySQL**.
2. Remplissez :
   - **nom de la base** : par exemple `portfolio` (Hostinger ajoute un préfixe :
     le nom complet ressemble à `u123456789_portfolio`) ;
   - **utilisateur** : par exemple `portfolio` (devient `u123456789_portfolio`) ;
   - **mot de passe** : un mot de passe **long et unique** (utilisez un gestionnaire
     de mots de passe ou le générateur proposé par hPanel).
3. Cliquez sur **Créer**, puis **notez** les trois valeurs complètes : elles iront
   dans `config.php` à l'étape 4.

### Étape 3 · Importer `database.sql` dans phpMyAdmin

1. Toujours dans **Bases de données**, cliquez sur **phpMyAdmin** à côté de la base créée
   (ou **Entrer dans phpMyAdmin**).
2. Dans la colonne de gauche, **sélectionnez votre base** (`u123456789_portfolio`).
3. Onglet **Importer** > **Choisir un fichier** > `database.sql` (sur votre ordinateur) >
   bouton **Importer** en bas de page.
4. Vérifiez que la colonne de gauche affiche les tables (`projects`, `settings`,
   `admins`…).

> [!CAUTION]
> `database.sql` **supprime puis recrée** toutes les tables. Ne le réimportez **jamais**
> sur le site en ligne une fois rempli : vous perdriez vos projets et vos messages.
> Faites toujours une sauvegarde avant (voir [Maintenance](#8-maintenance)).

### Étape 4 · Préparer `app/config.php`

Sur votre ordinateur, copiez `app/config.example.php` en **`app/config.php`** puis
ouvrez-le avec un éditeur de texte (VS Code, Notepad++…). Chaque valeur est commentée.

```php
'env'      => 'production',                    // toujours « production » en ligne
'base_url' => 'https://www.votre-domaine.fr',  // adresse exacte du site, sans « / » final
```

**4.1 · Base de données** : reprenez les valeurs notées à l'étape 2.

```php
'db' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'u123456789_portfolio',
    'user' => 'u123456789_portfolio',
    'pass' => 'le-mot-de-passe-de-la-base',
    'charset' => 'utf8mb4',
],
```

**4.2 · Clé secrète `app_key`** : **64 caractères hexadécimaux** aléatoires (0-9, a-f).
Elle sert à chiffrer le secret de double authentification et à signer certains jetons.
Générez-la **une seule fois**, avec l'une de ces méthodes :

```bash
# En SSH chez Hostinger (ou sur un ordinateur où PHP est installé)
php -r "echo bin2hex(random_bytes(32));"

# Linux, macOS ou Git Bash (hors ligne)
openssl rand -hex 32
```

```powershell
# Windows PowerShell (hors ligne)
$b = New-Object byte[] 32; [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b); -join ($b | ForEach-Object { $_.ToString('x2') })
```

> [!IMPORTANT]
> Ne changez plus `app_key` une fois le site en service : la double
> authentification devrait être reconfigurée. Ne la publiez jamais (pas de capture
> d'écran, pas de dépôt GitHub).

**4.3 · Jeton d'installation `setup_token`** : une chaîne aléatoire **temporaire**
d'au moins 24 caractères (32 ou plus conseillés). Elle protège la page de création
du compte administrateur (étape 7) et sera **vidée** ensuite.

```bash
openssl rand -hex 24      # ou : php -r "echo bin2hex(random_bytes(24));"
```

**4.4 · E-mails (formulaire de contact)** : le site peut vous prévenir par e-mail à
chaque nouveau message.

1. hPanel > **E-mails** : créez une adresse sur votre domaine, par exemple
   `contact@votre-domaine.fr`, avec un mot de passe fort.
2. Dans `config.php` :

```php
'mail' => [
    'transport'  => 'smtp',
    'host'       => 'smtp.hostinger.com',
    'port'       => 465,
    'encryption' => 'ssl',
    'username'   => 'contact@votre-domaine.fr',   // l'adresse complète
    'password'   => 'mot-de-passe-de-la-boite',
    'from_email' => 'contact@votre-domaine.fr',   // même adresse que username
    'from_name'  => 'Portfolio Alexandre Derbez',
    'timeout'    => 10,
],
```

L'adresse qui **reçoit** les messages (par exemple votre adresse personnelle) se
règle plus tard dans l'administration (**Réglages > Adresse e-mail de contact**).
Sans configuration (`'transport' => 'none'`), les messages restent lisibles dans
**Administration > Messages**.

> [!WARNING]
> `config.php` contient tous vos mots de passe. Ne l'envoyez jamais sur GitHub (il
> est déjà listé dans `.gitignore`), ni par e-mail.

### Étape 5 · Envoyer les fichiers

**Structure cible sur le serveur** (le dossier du domaine contient `public_html`) :

```text
domains/votre-domaine.fr/
├── app/                 ← le dossier app/ COMPLET (avec config.php)
└── public_html/         ← le CONTENU du dossier public_html/ du projet
    ├── .htaccess
    ├── index.php
    ├── setup-admin.php
    ├── assets/
    └── uploads/
```

> [!CAUTION]
> Le dossier `app/` doit être **à côté** de `public_html/`, **jamais dedans** : il
> contient le code et `config.php`, qui ne doivent pas être accessibles depuis le
> web. `public_html/index.php` va chercher `../app/bootstrap.php`.

À **ne pas** envoyer : `database.sql`, `database/`, `docs/`, `tests/`, `.git/`, `.github/`,
`README.md`, `.gitignore`.

**Méthode A · Gestionnaire de fichiers de hPanel** (le plus simple)

1. Sur votre ordinateur, créez deux archives ZIP : une du **contenu** de
   `public_html/` et une du dossier `app/`.
2. hPanel > **Fichiers > Gestionnaire de fichiers**, ouvrez `domains/votre-domaine.fr/`.
3. Envoyez l'archive de `app/` dans `domains/votre-domaine.fr/` et décompressez-la
   (clic droit > **Extraire**) : vous devez obtenir `domains/votre-domaine.fr/app/`.
4. Ouvrez `public_html/`, supprimez le fichier d'accueil par défaut de Hostinger
   (`default.php` s'il existe), envoyez l'archive du contenu de `public_html/` et
   décompressez-la **dans** `public_html/`.
5. Supprimez les deux fichiers `.zip` du serveur.

**Méthode B · FTP avec FileZilla**

1. hPanel > **Fichiers > Comptes FTP** : notez l'hôte, l'identifiant et le port
   (définissez un mot de passe si besoin).
2. FileZilla : **Fichier > Gestionnaire de sites**, protocole **FTP**, chiffrement
   « **Connexion FTP explicite sur TLS** si disponible », puis connectez-vous.
3. Dans la colonne de droite, allez dans `domains/votre-domaine.fr/` et glissez
   le dossier `app/`, puis le **contenu** de `public_html/` dans `public_html/`.

**Fichiers cachés `.htaccess`** : ils commencent par un point et sont souvent
invisibles. Vérifiez qu'ils sont bien sur le serveur :

- FileZilla : menu **Serveur > Forcer l'affichage des fichiers cachés** ;
- Gestionnaire de fichiers : option « Afficher les fichiers cachés » dans les réglages ;
- il en faut deux : `public_html/.htaccess` et `public_html/uploads/.htaccess`.

> [!IMPORTANT]
> Sans `public_html/.htaccess`, le site n'a ni HTTPS forcé, ni en-têtes de sécurité,
> et seules la page d'accueil et les fichiers existants répondent. Sans
> `uploads/.htaccess`, le dossier des images n'est plus verrouillé.

**Droits d'écriture** : ces dossiers doivent être inscriptibles par PHP (**755**
suffit en général chez Hostinger, fichiers en **644**) :

- `app/storage/logs/`, `app/storage/sessions/`, `app/storage/cv/` ;
- `public_html/uploads/projects/`.

Dans le Gestionnaire de fichiers : clic droit > **Autorisations** (ou « Permissions »).
Optionnel : passez `app/config.php` en **600** (lisible par vous seul) ; si le site
affiche ensuite une erreur, remettez **644**.

### Étape 6 · Activer le SSL (HTTPS)

1. hPanel > **Sécurité > SSL** : installez le **certificat SSL gratuit** pour le
   domaine (et `www.` si vous l'utilisez). L'activation peut prendre quelques minutes.
2. Vérifiez que `http://votre-domaine.fr` **redirige** vers `https://votre-domaine.fr`
   (le fichier `.htaccess` s'en charge) et que le cadenas s'affiche.
3. `base_url` dans `config.php` doit commencer par `https://` et correspondre
   exactement à l'adresse utilisée (avec ou sans `www.`).

> [!NOTE]
> Pour forcer `www.` (ou l'inverse), décommentez les deux lignes prévues dans
> `public_html/.htaccess` (section « Forcer www »).

### Étape 7 · Créer le compte administrateur

1. Ouvrez **`https://votre-domaine.fr/setup-admin.php`**.
2. Saisissez le **jeton d'installation** (`setup_token` de l'étape 4.3), votre
   **identifiant** (3 à 50 caractères : lettres sans accent, chiffres, `.`, `-`, `_`)
   et un **mot de passe** d'au moins 12 caractères (une phrase de passe de plusieurs
   mots est idéale), deux fois.
3. Une fois le compte créé :
   - **supprimez** `public_html/setup-admin.php` du serveur ;
   - dans `app/config.php`, **videz** le jeton : `'setup_token' => '',`.

> [!CAUTION]
> Tant que `setup-admin.php` est présent, il reste une porte d'entrée (même si elle
> est verrouillée par le jeton et refuse de servir une fois un compte créé).
> Supprimez-le **immédiatement**.

Sécurités de cette page : elle est inactive si `setup_token` est vide ou trop court
(erreur 403), elle n'est servie qu'en HTTPS, elle refuse de fonctionner dès qu'un
administrateur existe (erreur 410), et elle bloque une adresse IP après 5 jetons
erronés en 15 minutes.

**Alternative en SSH** (utile aussi pour **réinitialiser un mot de passe oublié**) :
activez l'accès SSH dans hPanel (**Avancé > Accès SSH**), connectez-vous, puis :

```bash
cd domains/votre-domaine.fr
php app/tools/create-admin.php                         # créer un compte
php app/tools/create-admin.php --reset                 # changer le mot de passe d'un compte
php app/tools/create-admin.php --reset --disable-2fa   # idem + désactiver la double authentification
```

Le mot de passe est saisi **sans s'afficher**. Après `--reset`, toutes les sessions
ouvertes sont fermées.

### Étape 8 · Configurer le site depuis l'administration

1. Connectez-vous sur **`https://votre-domaine.fr/admin`**.
2. **Mon compte** : activez la **double authentification** (application Google
   Authenticator, Microsoft Authenticator, Aegis, 2FAS…) en scannant le QR code.
   **Notez les codes de secours** affichés une seule fois et rangez-les en lieu sûr
   (gestionnaire de mots de passe, papier).
3. **Réglages** : liens **LinkedIn** et **GitHub**, **adresse e-mail de contact**,
   dates du stage recherché, description pour les moteurs de recherche, mentions
   légales.
4. **Réglages > CV** : envoyez votre CV au format **PDF** (5 Mo maximum).
5. **Projets > Images** : ajoutez des captures d'écran à chaque projet.
6. Relisez tous les textes de départ : la liste précise de ce qui a été supposé
   est dans [`docs/CONTENU-A-VERIFIER.md`](docs/CONTENU-A-VERIFIER.md).

### Étape 9 · Tests après la mise en ligne

- [ ] Toutes les pages s'affichent, sur ordinateur **et** sur téléphone.
- [ ] `http://` redirige vers `https://`.
- [ ] `https://votre-domaine.fr/setup-admin.php` renvoie une erreur (fichier supprimé).
- [ ] `https://votre-domaine.fr/app/config.php` et `https://votre-domaine.fr/database.sql`
      renvoient une erreur (aucun fichier sensible accessible).
- [ ] **Formulaire de contact** : envoyez-vous un message, vérifiez qu'il apparaît dans
      **Administration > Messages** et que l'e-mail arrive (pensez au dossier spam).
- [ ] **En-têtes de sécurité** : testez l'adresse sur
      [securityheaders.com](https://securityheaders.com) (objectif : note A ou A+) et sur
      [Mozilla Observatory](https://developer.mozilla.org/en-US/observatory).
- [ ] **Aperçu LinkedIn** : vérifiez-le avec le
      [Post Inspector](https://www.linkedin.com/post-inspector/) (voir section suivante).
- [ ] Le CV se télécharge depuis `/cv`.

---

## 5. Mettre le lien sur LinkedIn

1. **Coordonnées** : sur votre profil, **Modifier les coordonnées** > **Site web** >
   collez `https://votre-domaine.fr` et choisissez le type **Portfolio**.
2. **Sélection** (« Featured ») : **Ajouter une section**, puis la rubrique
   **Sélection** et **Ajouter un lien** > `https://votre-domaine.fr`. LinkedIn affiche alors une carte avec
   l'image d'aperçu : c'est l'élément le plus visible du profil.
3. **Titre et Infos** : mentionnez le portfolio dans la description (« Infos ») et,
   si la place le permet, dans le titre (par exemple « Étudiant BTS CIEL IR ·
   Portfolio : votre-domaine.fr »). Selon votre compte, la fiche d'intro peut aussi
   afficher un lien personnalisé.
4. **Rafraîchir l'aperçu** : LinkedIn garde l'aperçu en cache. Après une
   modification (titre, description, image), passez l'adresse dans le
   [LinkedIn Post Inspector](https://www.linkedin.com/post-inspector/) pour le mettre à jour.

L'image d'aperçu par défaut est `public_html/assets/img/og-default.png` (1200x630 px).
Quand vous partagez la page d'un projet, c'est **l'image de couverture du projet**
(sa première image) qui est utilisée.

> [!TIP]
> Partagez directement un projet (`https://votre-domaine.fr/projet/tardis`) dans un
> post LinkedIn : l'aperçu montrera sa capture d'écran.

---

## 6. Utiliser l'administration

| Menu | À quoi ça sert |
|---|---|
| **Tableau de bord** | chiffres clés, derniers messages, brouillons, et **points de vigilance** : fichier d'installation encore présent, jeton non vidé, double authentification inactive, CV manquant, adresse de contact vide… |
| **Projets** | créer et modifier les projets. **Publié** décoché = **brouillon** (invisible sur le site). **Mis en avant** = affiché sur l'accueil (les 4 premiers). **Ordre d'affichage** : plus petit = plus haut |
| **Projets > Images** | jusqu'à 10 images par envoi (JPEG, PNG ou WebP, 8 Mo max chacune), redimensionnées automatiquement. La **première image est la couverture** ; flèches pour réordonner. Renseignez toujours le **texte alternatif** (description pour les personnes aveugles et pour Google) |
| **Catégories** | catégories de projets, propres à chaque section (jeux ou technique) : elles servent de filtres |
| **Tags** | technologies affichées sur les projets (Lua, Roblox Studio…) |
| **Parcours** | études, stages et autres expériences (frise du site) |
| **Compétences** / **Thèmes de compétences** / **Sous-catégories de compétences** | compétences rangées en thèmes (Informatique & réseaux, Langues…) puis en sous-catégories (Réseau, Systèmes…), avec un niveau de 1 (Notions) à 5 (Maîtrise) ou sans niveau (savoir-être, atouts) ; libellé de niveau personnalisable (« A2 », « Langue maternelle ») |
| **Centres d'intérêt** | VTT, jeux vidéo… avec une icône |
| **À propos** | texte de présentation détaillé |
| **Messages** | messages du formulaire de contact ; ceux de plus de 12 mois sont supprimés automatiquement (RGPD) |
| **Réglages** | textes de l'accueil, bandeau de stage, liens, e-mail de contact, référencement, mentions légales, CV |
| **Mon compte** | mot de passe, double authentification, codes de secours |

**Markdown simplifié** (descriptions de projets, parcours, « À propos ») : tout HTML
saisi est affiché tel quel (jamais interprété), seules ces syntaxes sont reconnues.

| Vous écrivez | Résultat |
|---|---|
| `### Titre` / `#### Sous-titre` | titre de section / sous-titre |
| `**gras**`, `*italique*` | **gras**, *italique* |
| `` `code` `` | `code` en ligne |
| `- élément` ou `1. élément` | liste à puces ou numérotée |
| `[texte](https://exemple.fr)` | lien (http, https, mailto ou adresse interne `/…` uniquement) |
| bloc entre deux lignes ` ``` ` | bloc de code |
| ligne vide | nouveau paragraphe |

---

## 7. Développement en local

Il faut **PHP 8.1+** (avec `pdo_mysql`, `gd`, `mbstring`) et **MariaDB** ou MySQL.

1. Base de données (en administrateur MariaDB, par exemple `sudo mysql`) :

   ```sql
   CREATE DATABASE portfolio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'portfolio'@'localhost' IDENTIFIED BY 'un-mot-de-passe-local';
   GRANT ALL PRIVILEGES ON portfolio.* TO 'portfolio'@'localhost';
   GRANT ALL PRIVILEGES ON `portfolio\_test%`.* TO 'portfolio'@'localhost';
   ```

   ```bash
   mysql -u portfolio -p portfolio < database.sql
   ```

2. Configuration : copiez `app/config.example.php` en `app/config.php`, avec
   `'env' => 'development'`, `'base_url' => 'http://127.0.0.1:8080'`, une `app_key`
   générée comme à l'étape 4.2, un `setup_token` et `'transport' => 'none'`.
   Astuce : `'name' => getenv('PF_DB_NAME') ?: 'portfolio'` permet de choisir une
   base de test avec la variable d'environnement `PF_DB_NAME`.

3. Serveur de développement : le routeur `app/tools/dev-router.php` reproduit les
   règles et les en-têtes de sécurité du `.htaccess` (le serveur intégré de PHP
   ignore les `.htaccess`).

   ```bash
   php -S 127.0.0.1:8080 -t public_html app/tools/dev-router.php
   ```

   Ouvrez <http://127.0.0.1:8080>, puis créez un compte sur
   <http://127.0.0.1:8080/setup-admin.php> ou avec `php app/tools/create-admin.php`.

4. Tests automatiques (en ligne de commande) :

   ```bash
   php tests/core-test.php                                    # noyau (base locale)
   PF_DB_NAME=portfolio_test_models php tests/models-test.php # modèles : VIDE la base visée
   php tests/totp-test.php                                    # double authentification et QR code
   ```

   `models-test.php` refuse de tourner sur une base dont le nom ne commence pas par
   `portfolio_test_` : créez-la et importez-y `database.sql` avant.

> [!NOTE]
> Les images fixes (`favicon-32.png`, `apple-touch-icon.png`, `og-default.png`) sont
> générées par `php app/tools/generate-images.php`. Le vocabulaire CSS à utiliser dans
> les vues est décrit dans [`docs/STYLEGUIDE.md`](docs/STYLEGUIDE.md).

---

## 8. Maintenance

**Sauvegardes** (au moins une fois par mois, et avant toute mise à jour) :

1. **Base** : phpMyAdmin > sélectionnez la base > onglet **Exporter** > méthode rapide,
   format **SQL** > **Exporter**.
2. **Fichiers** : téléchargez `public_html/uploads/projects/` (images),
   `app/storage/cv/` (CV) et `app/config.php` (à stocker en lieu sûr : il contient
   vos mots de passe).

Hostinger réalise aussi des sauvegardes automatiques (hPanel, rubrique Fichiers >
Sauvegardes, selon l'offre).

**Mettre à jour le site** : envoyez seulement les fichiers modifiés, **sans écraser**
`app/config.php`, `app/storage/` ni `public_html/uploads/`, et **sans réimporter**
`database.sql`. Si une évolution modifie la structure de la base, appliquez
uniquement le fichier correspondant de `database/migrations/` dans phpMyAdmin,
après une sauvegarde (mode d'emploi : `docs/MIGRATIONS.md`).

**Journaux** : les erreurs sont enregistrées dans `app/storage/logs/php-errors.log`
(jamais affichées aux visiteurs). Consultez-le de temps en temps et videz-le s'il
grossit.

**Données personnelles (RGPD)** : les messages de plus de 12 mois sont supprimés
automatiquement (à l'ouverture de **Messages** et de temps en temps lors des envois).
Les traces anti-brute-force ne contiennent aucune adresse IP en clair et sont
effacées au bout de 2 jours environ.

**Version de PHP** : après chaque changement de version dans hPanel, vérifiez que
toutes les pages et l'administration fonctionnent.

---

## 9. Dépannage

| Symptôme | Causes probables et solutions |
|---|---|
| **Erreur 500** | Lisez `app/storage/logs/php-errors.log`. Causes fréquentes : `app/` mal placé (il doit être à côté de `public_html/`), table absente (import de `database.sql` incomplet), dossier `app/storage/…` non inscriptible. |
| **« Site en maintenance : configuration… »** (503) | `app/config.php` absent ou invalide : `app_key` doit faire exactement 64 caractères hexadécimaux, `base_url` doit ressembler à `https://www.votre-domaine.fr` sans `/` final. |
| **Page blanche** | Version de PHP trop ancienne (moins de 8.1) : changez-la (étape 1). Vérifiez aussi les journaux d'erreurs de hPanel. |
| **Toutes les pages sauf l'accueil donnent 404** | `public_html/.htaccess` manquant (fichier caché non envoyé) : voir étape 5. |
| **Redirection en boucle** (« trop de redirections ») | Le site passe par Cloudflare en SSL « Flexible » : choisissez « Full (strict) ». Vérifiez aussi que `base_url` est en `https://` et que le certificat est actif (étape 6). |
| **Impossible de se connecter, « session expirée »** | Le site doit être ouvert en HTTPS (le cookie de session est réservé au HTTPS en production) ; vérifiez `app/storage/sessions/` (inscriptible) et l'adresse exacte (`www.` ou non) par rapport à `base_url`. Après 5 erreurs, la connexion est bloquée 15 minutes. |
| **Mot de passe oublié / téléphone 2FA perdu** | Utilisez un **code de secours** à la place du code à 6 chiffres. Sinon, en SSH : `php app/tools/create-admin.php --reset` (ajoutez `--disable-2fa` si vous avez perdu le téléphone ET les codes). |
| **E-mails non reçus** | Vérifiez **Réglages > Adresse e-mail de contact**, le dossier spam, puis la section `mail` de `config.php` (`smtp.hostinger.com`, port 465, `ssl`, adresse complète en `username`, même adresse en `from_email`). Les erreurs d'envoi sont notées `[mail]` dans le journal. Le message reste de toute façon lisible dans **Messages**. |
| **Image refusée** | Formats acceptés : JPEG, PNG, WebP (les photos **HEIC** d'iPhone sont refusées : réglez l'appareil sur « Le plus compatible » ou convertissez en JPEG), 8 Mo maximum, 10 000 px de côté maximum. Si l'erreur parle de taille, augmentez `upload_max_filesize` et `post_max_size` (Configuration PHP > Options PHP). Si elle parle d'écriture, vérifiez les droits de `public_html/uploads/projects/`. |
| **Les images ne s'affichent pas** | `public_html/uploads/.htaccess` n'autorise que les noms générés par le site : n'envoyez pas d'images à la main dans ce dossier, passez par l'administration. |
| **`setup-admin.php` affiche « Installation désactivée »** | `setup_token` est vide ou fait moins de 24 caractères dans `app/config.php`. |
| **`setup-admin.php` affiche « Connexion non chiffrée »** | Activez le SSL (étape 6) et ouvrez la page en `https://`. |
| **En-têtes de sécurité absents** (securityheaders.com) | `.htaccess` absent ou module `headers` indisponible : vérifiez la présence du fichier ; contactez le support Hostinger si besoin. |

---

## 10. Licence et crédits

- **Code du site** : © Alexandre Derbez. Aucun fichier de licence n'est fourni pour
  l'instant : sans licence explicite, la réutilisation du code n'est pas autorisée
  par défaut. Ajoutez un fichier `LICENSE` (par exemple MIT) pour l'ouvrir.
- **Polices** : [Inter](https://github.com/rsms/inter) (The Inter Project Authors) et
  [JetBrains Mono](https://github.com/JetBrains/JetBrainsMono) (The JetBrains Mono
  Project Authors), sous **licence SIL Open Font License 1.1**. Elles sont hébergées
  avec le site (aucune requête vers Google Fonts) ; les textes de licence sont dans
  `public_html/assets/fonts/LICENSE-Inter.txt` et
  `public_html/assets/fonts/LICENSE-JetBrainsMono.txt`.
- **Double authentification** : algorithme TOTP (RFC 6238) et générateur de QR code
  écrits en PHP pour ce projet, sans bibliothèque externe.
- Roblox, LinkedIn, GitHub, YouTube et Hostinger sont des marques de leurs
  propriétaires respectifs.
