# Sécurité du portfolio

Ce document explique, pour un professeur, un recruteur ou un maître de stage,
**chaque mesure de sécurité réellement présente dans le code**, le risque qu'elle
traite et le fichier où la vérifier. Il se termine par les limites connues.

Le site est volontairement écrit **sans framework ni dépendance** : chaque
protection est donc visible et lisible dans le dépôt, et aucune bibliothèque
tierce ne peut introduire de faille.

## Sommaire

1. [Vue d'ensemble](#1-vue-densemble)
2. [Injections SQL](#2-injections-sql)
3. [XSS (injection de code dans les pages)](#3-xss-injection-de-code-dans-les-pages)
4. [CSRF (requêtes forgées)](#4-csrf-requêtes-forgées)
5. [Sessions](#5-sessions)
6. [Mots de passe](#6-mots-de-passe)
7. [Brute-force et limitation de débit](#7-brute-force-et-limitation-de-débit)
8. [Double authentification (TOTP)](#8-double-authentification-totp)
9. [Envoi de fichiers (images, CV)](#9-envoi-de-fichiers-images-cv)
10. [En-têtes HTTP de sécurité](#10-en-têtes-http-de-sécurité)
11. [HTTPS forcé](#11-https-forcé)
12. [Gestion des erreurs](#12-gestion-des-erreurs)
13. [Code hors de la racine web](#13-code-hors-de-la-racine-web)
14. [Anti-spam du formulaire de contact](#14-anti-spam-du-formulaire-de-contact)
15. [Confidentialité (RGPD)](#15-confidentialité-rgpd)
16. [E-mails](#16-e-mails)
17. [Redirections et adresses](#17-redirections-et-adresses)
18. [Administration et installation](#18-administration-et-installation)
19. [Limites connues et améliorations possibles](#19-limites-connues-et-améliorations-possibles)
20. [Vérifier soi-même](#20-vérifier-soi-même)

---

## 1. Vue d'ensemble

| Menace | Mesure principale | Où |
|---|---|---|
| Injection SQL | requêtes préparées natives uniquement | `app/Core/Database.php` |
| XSS | échappement systématique + CSP sans script en ligne | `app/helpers.php` (`e()`), `public_html/.htaccess` |
| CSRF | jeton par session vérifié pour **tout** POST + `SameSite=Strict` + Origin | `app/Core/Csrf.php`, `app/Core/Router.php` |
| Vol de session | cookie `HttpOnly`, `Secure`, `__Host-`, régénération, expirations | `app/Core/Session.php`, `app/Core/Auth.php` |
| Vol de la base | mots de passe Argon2id, secret 2FA chiffré, IP hachées | `app/Models/Admin.php`, `app/Core/Crypto.php`, `app/Core/Throttle.php` |
| Brute-force | limitation par IP et par compte | `app/Core/Throttle.php`, `app/Core/Auth.php` |
| Fichier piégé | type réel, ré-encodage, nom aléatoire, dossier sans exécution | `app/Core/ImageProcessor.php`, `public_html/uploads/.htaccess` |
| Clickjacking, sniffing… | en-têtes HTTP stricts | `public_html/.htaccess` |
| Fuite d'informations | aucune erreur affichée en production, code hors web | `app/Core/ErrorHandler.php`, structure `app/` / `public_html/` |
| Spam | piège, jeton horodaté signé, limitation | `app/Controllers/Site/ContactController.php` |

Principe directeur : **défense en profondeur**. Chaque risque est couvert par au
moins deux barrières indépendantes (par exemple : échappement **et** CSP contre le
XSS ; jeton **et** SameSite **et** Origin contre le CSRF).

---

## 2. Injections SQL

**Le risque** : un visiteur glisse du SQL dans un champ (`' OR 1=1 --`) pour lire
ou modifier la base.

**Ce qui est fait** :

- Toutes les requêtes passent par `App\Core\Database` avec des **paramètres liés**
  (`?`) : la donnée n'est jamais collée dans la chaîne SQL.
- `PDO::ATTR_EMULATE_PREPARES => false` : ce sont de **vraies requêtes préparées**
  côté serveur MySQL (la requête et les données voyagent séparément).
- Les entiers sont liés avec `PDO::PARAM_INT` (indispensable pour `LIMIT`/`OFFSET`).
- `PDO::ERRMODE_EXCEPTION` : toute erreur SQL lève une exception (jamais d'échec silencieux).
- **Mode strict** MySQL (`STRICT_ALL_TABLES`, `NO_ZERO_DATE`…) : une donnée invalide
  est refusée au lieu d'être tronquée ; jeu de caractères `utf8mb4`.
- Les noms de colonnes ne viennent jamais de l'utilisateur : les modèles utilisent
  une **liste blanche** de colonnes (constante `COLUMNS`) et une expression régulière
  (`^[a-z_]{1,64}$`) dans `app/Models/Crud.php` ; les champs basculables passent par
  un `match` (`Project::toggle`).
- La base elle-même impose des contraintes (clés étrangères, `CHECK`, unicité).

**Où** : `app/Core/Database.php`, `app/Models/*.php`, `database.sql`.

---

## 3. XSS (injection de code dans les pages)

**Le risque** : un texte contenant `<script>` (dans un message, un titre, une URL)
s'exécute dans le navigateur d'un visiteur ou de l'administrateur.

**Ce qui est fait** :

- **Échappement systématique** : toute donnée affichée passe par `e()`
  (`htmlspecialchars` avec `ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5`, en UTF-8), y
  compris dans les attributs.
- **Markdown sûr** : `App\Core\Markdown` échappe d'abord **tout** le texte, puis
  ne transforme que quelques syntaxes connues (titres, gras, listes, liens, code).
  Aucun HTML saisi ne passe. Les liens n'acceptent que `http://`, `https://`,
  `mailto:` ou un chemin interne (`/…`) : `javascript:` est refusé. Les liens
  externes reçoivent `rel="noopener noreferrer"`.
- **URL venant de la base** : `Validator::url()` n'accepte que `http(s)` ;
  `SiteController::safeUrl()` revérifie à l'affichage ; l'identifiant YouTube est
  contrôlé par une expression régulière (11 caractères).
- **JSON-LD** (données structurées) encodé avec `JSON_HEX_TAG` : impossible de
  fermer la balise `</script>`.
- Le composant de champ de formulaire (`app/Views/partials/site/field.php`) refuse les
  attributs `on…` et `style`.
- **CSP stricte**, seconde barrière : `script-src 'self'; style-src 'self'`. Aucun
  script ni style **en ligne** n'existe dans le site (aucun `onclick=`, aucun
  `style="…"`) : même si un échappement était oublié, un script injecté ne
  s'exécuterait pas. Le QR code de la 2FA est un SVG généré sans attribut `style`.

**Où** : `app/helpers.php`, `app/Core/Markdown.php`, `app/Core/Validator.php`,
`app/Controllers/Site/SiteController.php`, `app/Views/layouts/site.php`,
`public_html/.htaccess`.

---

## 4. CSRF (requêtes forgées)

**Le risque** : un site malveillant fait envoyer, par le navigateur de
l'administrateur connecté, un formulaire vers le portfolio (supprimer un projet,
changer un réglage).

**Ce qui est fait** :

- **Jeton aléatoire** de 256 bits par session (`random_bytes(32)`), inséré dans
  chaque formulaire par `csrf_field()`.
- Le **routeur vérifie le jeton de toutes les requêtes POST** avant d'appeler le
  contrôleur : impossible d'oublier la protection sur un nouveau formulaire.
  Comparaison en temps constant (`hash_equals`). Échec : erreur 419.
- **Vérification de l'en-tête `Origin`** : s'il est présent, il doit correspondre
  au domaine du site (erreur 403 sinon).
- **Cookie `SameSite=Strict`** : le navigateur n'envoie jamais le cookie de session
  depuis un autre site.
- Nouveau jeton à la connexion (`Csrf::rotate()`). Toute action (suppression,
  publication, **déconnexion**) est un POST, jamais un simple lien.
- `setup-admin.php`, qui ne passe pas par le routeur, appelle lui-même
  `Csrf::verifyRequest()`.

**Où** : `app/Core/Csrf.php`, `app/Core/Router.php`, `app/helpers.php`,
`public_html/setup-admin.php`.

---

## 5. Sessions

**Le risque** : vol ou fixation du cookie de session pour se faire passer pour
l'administrateur.

**Ce qui est fait** :

- Cookie **`HttpOnly`** (illisible en JavaScript), **`Secure`** (HTTPS uniquement,
  imposé en production même si la détection HTTPS échouait), **`SameSite=Strict`**,
  et préfixe **`__Host-`** en HTTPS (cookie lié au domaine exact, sans sous-domaine).
- `session.use_strict_mode` (un identifiant inventé est refusé),
  `use_only_cookies`, pas d'identifiant dans l'URL.
- **Nouvel identifiant de session** à chaque changement de privilège : connexion,
  étape 2FA, changement de mot de passe, activation de la 2FA.
- **Expiration** : 30 minutes d'inactivité et 8 heures au maximum
  (`security.session_idle_timeout`, `session_absolute_timeout`) ; la session est
  aussi liée à l'empreinte (SHA-256) du navigateur.
- Fichiers de session stockés dans `app/storage/sessions`, **hors du web**.
- La session n'est démarrée **que si nécessaire** (formulaire, administration) :
  un simple visiteur ne reçoit aucun cookie.

**Où** : `app/Core/Session.php`, `app/Core/Auth.php`.

---

## 6. Mots de passe

**Le risque** : la base fuit et les mots de passe sont retrouvés ; ou l'attaquant
devine quels identifiants existent.

**Ce qui est fait** :

- Hachage **Argon2id** (`password_hash`, algorithme recommandé par l'OWASP), repli
  bcrypt coût 12 si Argon2id n'est pas disponible ; le hachage est **mis à jour
  automatiquement** à la connexion si les paramètres changent (`needsRehash`).
- **Politique** : 12 caractères minimum, 72 octets maximum, ne doit pas contenir
  l'identifiant, au moins 6 caractères différents, refus des mots courants
  (`password`, `azerty`, `admin`…) dans un mot de passe de moins de 16 caractères.
  Les phrases de passe sont encouragées.
- **Anti-énumération** : même message d'erreur que l'identifiant existe ou non,
  et **hachage factice** calculé quand le compte n'existe pas, pour que le temps de
  réponse soit identique.
- **Ré-authentification** : changer le mot de passe ou gérer la 2FA redemande le
  mot de passe actuel (avec limitation : 5 échecs en 15 minutes).
- Le mot de passe n'est jamais réaffiché dans un formulaire ni journalisé.

**Où** : `app/Models/Admin.php`, `app/Core/Auth.php`,
`app/Controllers/Admin/AccountController.php`.

---

## 7. Brute-force et limitation de débit

**Le risque** : essayer des milliers de mots de passe ou de codes, ou inonder le
formulaire de contact.

**Ce qui est fait** : chaque tentative est enregistrée dans la table `throttle`.

| Action | Limite |
|---|---|
| Connexion, par IP | 5 échecs / 15 minutes |
| Connexion, par identifiant | 10 échecs / heure |
| Code de double authentification, par compte | 5 échecs / 15 minutes |
| Ré-authentification (page « Mon compte ») | 5 échecs / 15 minutes |
| Jeton de `setup-admin.php`, par IP | 5 échecs / 15 minutes |
| Formulaire de contact, par IP | 3 messages / 10 minutes et 10 / 24 heures |

- **IP hachées** : la table ne contient qu'un **HMAC-SHA256** (clé secrète
  `app_key`) de la clé surveillée, jamais l'adresse IP en clair (RGPD). Les entrées
  de plus de 2 jours sont supprimées régulièrement (nettoyage automatique).
- L'IP utilisée est `REMOTE_ADDR` : l'en-tête `X-Forwarded-For`, falsifiable, est ignoré.

**Où** : `app/Core/Throttle.php`, `app/Core/Auth.php`, `app/Core/Request.php`.

---

## 8. Double authentification (TOTP)

**Le risque** : le mot de passe est volé (hameçonnage, réutilisation).

**Ce qui est fait** :

- **TOTP conforme à la RFC 6238** (HMAC-SHA1, 6 chiffres, pas de 30 secondes),
  compatible avec les applications courantes ; implémentation testée sur les
  vecteurs officiels des RFC 4226 et 6238 (`tests/totp-test.php`).
- Tolérance de ±1 pas (décalage d'horloge), comparaison en temps constant.
- **Anti-rejeu** : le dernier pas accepté est enregistré ; un code déjà utilisé
  (ou plus ancien) est refusé.
- **Secret chiffré** en base avec **AES-256-GCM** (chiffrement authentifié) ; la clé
  est dérivée de `app_key` par **HKDF** (une clé différente par usage). Une fuite de
  la base seule ne révèle pas le secret.
- Pendant l'activation, le secret reste **en session** et n'est enregistré qu'après
  un premier code valide.
- **QR code généré sur le serveur** (générateur écrit en PHP) : le secret n'est
  jamais envoyé à un service externe de création de QR codes.
- **Codes de secours** : 8 codes aléatoires, **hachés** (bcrypt) en base, à usage
  unique, affichés une seule fois sur une page jamais mise en cache.
- L'étape « code » expire au bout de 5 minutes.

**Où** : `app/Core/Totp.php`, `app/Core/Crypto.php`, `app/Core/QrCode.php`,
`app/Core/Auth.php`, `app/Models/Admin.php`, `app/Controllers/Admin/AccountController.php`.

---

## 9. Envoi de fichiers (images, CV)

**Le risque** : envoyer un script PHP déguisé en image, une « bombe de
décompression », ou une image contenant des métadonnées privées (GPS).

**Ce qui est fait pour les images** :

1. vérification de l'envoi (`is_uploaded_file`) et de la taille (8 Mo) ;
2. **type MIME réel** lu dans le contenu (`finfo`), pas celui annoncé par le navigateur ;
3. **liste blanche** : JPEG, PNG, WebP uniquement, extension cohérente avec le contenu ;
4. dimensions limitées (10 000 px de côté, 40 mégapixels) contre les bombes de décompression ;
5. **ré-encodage complet avec GD** : seuls les pixels sont conservés, tout code caché
   et toutes les métadonnées (EXIF, GPS) disparaissent ;
6. **nom aléatoire** de 128 bits généré par le serveur (le nom d'origine est ignoré) ;
7. dossier `uploads/` **sans exécution** : moteur PHP coupé, gestionnaires retirés,
   tout fichier refusé **sauf** les noms générés (`[a-f0-9]{32}`), et CSP `sandbox`.
   La suppression vérifie aussi le nom (aucune traversée de dossier).

**Pour le CV (PDF)** : 5 Mo maximum, extension `.pdf`, type MIME réel **et** signature
`%PDF-` vérifiés ; fichier stocké **hors du web** (`app/storage/cv/`) et servi
uniquement par la route `/cv`, en téléchargement (`Content-Disposition: attachment`,
`nosniff`).

**Où** : `app/Core/ImageProcessor.php`, `app/Core/PdfUpload.php`,
`public_html/uploads/.htaccess`, `app/Controllers/Site/CvController.php`.

---

## 10. En-têtes HTTP de sécurité

Envoyés sur toutes les réponses par `public_html/.htaccess` (et reproduits en local
par `app/tools/dev-router.php`) :

| En-tête | Valeur | Rôle |
|---|---|---|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; frame-src https://www.youtube-nocookie.com; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; upgrade-insecure-requests` (extrait) | n'autorise que les ressources du site ; aucun script en ligne |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | HTTPS obligatoire pendant 1 an (HSTS) |
| `X-Frame-Options` | `DENY` | interdit l'affichage dans un cadre (clickjacking) |
| `X-Content-Type-Options` | `nosniff` | le navigateur ne « devine » pas le type d'un fichier |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | n'envoie que le domaine aux sites externes |
| `Permissions-Policy` | caméra, micro, géolocalisation, paiement… désactivés | réduit la surface d'attaque |
| `Cross-Origin-Opener-Policy` | `same-origin` | isole la page des fenêtres d'autres sites |

Autres réglages : `X-Powered-By` retiré, `ServerSignature Off`, pas de listing des
dossiers (`Options -Indexes`). Les pages d'administration ajoutent
`Cache-Control: no-store` et `X-Robots-Tag: noindex, nofollow`.

---

## 11. HTTPS forcé

- Redirection permanente (301) de `http://` vers `https://` dans `.htaccess`.
- **HSTS** et `upgrade-insecure-requests` (voir ci-dessus).
- Cookies `Secure` imposés en production.
- `X-Forwarded-Proto` n'est pris en compte que si `security.trust_proxy` est activé
  (sinon un client pourrait mentir sur le protocole).
- `setup-admin.php` refuse de s'afficher en HTTP en production : le premier mot de
  passe ne circule jamais en clair.
- Les e-mails partent en SMTP chiffré avec vérification du certificat (§ 16).

---

## 12. Gestion des erreurs

- En production, **aucune erreur n'est affichée** (`display_errors` désactivé dès le
  démarrage, avant même la lecture de la configuration). Le visiteur voit une page
  d'erreur générique ; le détail est **journalisé** dans
  `app/storage/logs/php-errors.log` (hors du web).
- Les avertissements PHP sont transformés en exceptions : pas de comportement
  silencieusement incorrect.
- Seuls les messages des `HttpException`, écrits pour l'utilisateur, sont affichés ;
  jamais le message d'une exception technique.
- Configuration absente ou invalide : réponse 503 « Site en maintenance », sans
  détail en production.
- Le détail technique (trace) n'apparaît qu'en mode `development`, en local.

**Où** : `app/bootstrap.php`, `app/Core/ErrorHandler.php`, `app/Core/Config.php`,
`app/Views/site/errors/error.php`.

---

## 13. Code hors de la racine web

- Seul `public_html/` est publié. Le code, la configuration (`app/config.php`), les
  sessions, les journaux et le CV sont dans `app/`, **à côté** de `public_html/` :
  aucune adresse web ne permet de les atteindre.
- Un seul point d'entrée : `public_html/index.php`. Le `.htaccess` interdit
  l'exécution de tout autre fichier PHP (sauf `setup-admin.php`, temporaire), les
  fichiers cachés (`.git`, `.env`…) et les extensions sensibles (`.sql`, `.log`,
  `.md`, `.bak`, `.zip`…) au cas où ils seraient envoyés par erreur.
- Chargement des classes et des vues restreint à des noms « normaux » (pas de
  `../`) : `app/bootstrap.php`, `app/Core/View.php`. Les chemins contenant des
  caractères de contrôle sont rejetés (`Request::path()`), les paramètres d'URL sont
  contraints par le routeur (`{id}` entier, `{slug}` en `[a-z0-9-]`).

---

## 14. Anti-spam du formulaire de contact

Sans CAPTCHA (qui pisterait les visiteurs), le formulaire combine :

1. **jeton CSRF** (vérifié par le routeur) ;
2. **champ piège** invisible (`website`) : s'il est rempli, le site simule un succès
   sans rien enregistrer (le robot ne sait pas qu'il a été détecté) ;
3. **limitation** par IP hachée : 3 messages / 10 minutes, 10 / jour ;
4. **jeton horodaté signé** (HMAC avec `app_key`) : refus si la signature est fausse,
   si l'envoi a lieu moins de 3 secondes ou plus de 2 heures après l'affichage ;
5. **validation** stricte des champs (longueurs, e-mail, UTF-8 valide, caractères de
   contrôle retirés) et case de consentement obligatoire.

**Où** : `app/Controllers/Site/ContactController.php`, `app/Core/Validator.php`,
`app/Views/partials/site/honeypot.php`.

---

## 15. Confidentialité (RGPD)

- **Aucun traceur** : pas de Google Analytics, pas de pixel publicitaire, pas de
  service externe chargé par défaut. Les **polices sont hébergées sur le site** (pas
  de requête vers Google Fonts).
- **Aucun cookie pour un simple visiteur** : le seul cookie (session, strictement
  nécessaire) n'apparaît qu'avec le formulaire de contact ou l'administration ; pas
  de bannière de consentement nécessaire.
- **YouTube après un clic** : une « façade » s'affiche à la place du lecteur ; l'iframe
  `youtube-nocookie.com` n'est chargée qu'au clic, avec un avertissement et un lien
  direct vers YouTube.
- **Purge des messages** de plus de 12 mois (à l'ouverture de la liste des messages
  et régulièrement lors des envois).
- Adresses IP jamais stockées en clair (HMAC, effacé après 2 jours).
- Page **Mentions légales** (éditeur, hébergeur, données personnelles, cookies).

**Où** : `app/Views/partials/site/youtube.php`, `public_html/assets/js/site.js`,
`app/Controllers/Admin/MessageController.php`, `app/Models/Message.php`,
`app/Controllers/Site/LegalController.php`.

---

## 16. E-mails

- **Anti-injection d'en-têtes** : les retours à la ligne sont supprimés de toutes les
  valeurs d'en-tête, les adresses sont validées (`FILTER_VALIDATE_EMAIL`, sans CR/LF),
  le sujet et les noms sont encodés (RFC 2047). Un visiteur ne peut pas ajouter de
  destinataire caché.
- **TLS vérifié** : SMTP en SSL implicite (port 465) ou STARTTLS (port 587, TLS 1.2
  ou 1.3), avec **vérification du certificat et du nom du serveur**
  (`verify_peer`, `verify_peer_name`).
- Le mot de passe SMTP n'apparaît jamais dans les journaux (masqué en `[AUTH]`).
- Corps encodé en base64, « dot-stuffing » SMTP, en-tête `Auto-Submitted`.
- L'adresse du visiteur n'est utilisée qu'en `Reply-To` (l'expéditeur est toujours la
  boîte du site, ce qui évite l'usurpation et le classement en spam).

**Où** : `app/Core/Mailer.php`.

---

## 17. Redirections et adresses

- `redirect()` n'accepte que des **chemins internes** commençant par `/` : `//site.fr`,
  `\`, les retours à la ligne ou une URL complète sont remplacés par `/`. Aucune
  « redirection ouverte » vers un site de hameçonnage n'est possible.
- La normalisation des adresses (`/parcours/` vers `/parcours`) passe par cette même
  fonction.

**Où** : `app/helpers.php`, `app/Core/Router.php`.

---

## 18. Administration et installation

- Tout contrôleur d'administration hérite de `AdminController`, dont le
  **constructeur exige une session valide** : une page ne peut pas être oubliée.
- Les pages d'administration ne sont **ni mises en cache ni indexées**.
- `public_html/setup-admin.php` (création du premier compte) : inactif sans jeton
  d'installation d'au moins 24 caractères (403), comparé en temps constant ; HTTPS
  exigé en production ; **inutilisable dès qu'un administrateur existe** (410) ;
  CSRF vérifié ; 5 jetons erronés au plus par IP et par quart d'heure ; aucun secret
  réaffiché ; à **supprimer** après usage (le tableau de bord le rappelle tant que le
  fichier existe ou que le jeton n'est pas vidé).
- `app/tools/create-admin.php` (alternative SSH) : refuse de s'exécuter hors ligne de
  commande ; mot de passe saisi **sans écho**, affichage du terminal restauré même
  en cas d'erreur ou de Ctrl+C ; `--reset` ferme toutes les sessions ouvertes.

---

## 19. Limites connues et améliorations possibles

Aucune application n'est parfaite. Voici ce qui pourrait être renforcé :

- **Sessions et changement de mot de passe** : changer son mot de passe depuis
  l'administration renouvelle la session courante, mais une session ouverte sur un
  autre appareil reste valide jusqu'à son expiration (30 min d'inactivité, 8 h au
  maximum). Seul `create-admin.php --reset` ferme toutes les sessions. Amélioration :
  enregistrer une « version de session » dans la table `admins`.
- **Double authentification facultative** : elle est fortement recommandée (le
  tableau de bord alerte tant qu'elle est inactive) mais pas imposée.
- **Limitation par IP** : plusieurs personnes derrière la même IP (réseau d'un lycée)
  partagent le même compteur ; à l'inverse, un attaquant disposant de nombreuses
  adresses IPv6 peut contourner la limite par IP (la limite par identifiant reste
  active). Si un CDN ou un proxy est placé devant le site, `REMOTE_ADDR` devient celle
  du proxy : il faudrait alors lire l'IP réelle dans un en-tête de confiance.
- **Mot de passe compromis** : pas de vérification contre les bases de mots de passe
  ayant fuité (par exemple l'API « Have I Been Pwned » par k-anonymat).
- **Secrets dans `config.php`** : les mots de passe de la base et de la messagerie y
  sont en clair (protégés par leur emplacement hors du web et les droits du fichier),
  ce qui est la norme sur un hébergement mutualisé.
- **Droits de la base** : l'utilisateur MySQL créé par hPanel a tous les droits sur sa
  base ; on pourrait le limiter à `SELECT, INSERT, UPDATE, DELETE` après l'installation.
- **CSP** : `img-src` autorise `data:` (aperçu des images avant envoi dans
  l'administration). Les en-têtes `Cross-Origin-Resource-Policy` et
  `Cross-Origin-Embedder-Policy` ne sont pas définis ; HSTS n'est pas inscrit dans la
  liste de préchargement des navigateurs (`preload`).
- **Journaux** : pas de rotation automatique de `php-errors.log`, pas d'alerte en temps
  réel en cas d'attaque (les échecs sont seulement journalisés).
- **`security.txt`** : pas de fichier `/.well-known/security.txt` indiquant comment
  signaler une faille.
- **Sauvegardes** : manuelles (phpMyAdmin) ou automatiques côté Hostinger, non chiffrées
  par le site.
- **`setup-admin.php`** : sa suppression après usage dépend de l'utilisateur (rappels
  sur la page de confirmation, dans le README et sur le tableau de bord).

---

## 20. Vérifier soi-même

- Tests automatiques : `php tests/core-test.php`, `php tests/totp-test.php`,
  `PF_DB_NAME=portfolio_test_models php tests/models-test.php` (voir README).
- En-têtes en ligne : [securityheaders.com](https://securityheaders.com) et
  [Mozilla Observatory](https://developer.mozilla.org/en-US/observatory).
- Console du navigateur (F12) : aucune violation de la CSP ne doit apparaître.
- Essais manuels : envoyer `<script>alert(1)</script>` dans le formulaire de contact
  (il s'affiche comme du texte dans l'administration), ouvrir
  `https://votre-domaine.fr/app/config.php` ou `/database.sql` (refusés), envoyer un
  fichier `.php` renommé en `.jpg` (refusé : le contenu réel n'est pas une image).
