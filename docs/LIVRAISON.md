# Livraison : paquet Hostinger et intégration continue

Le site se met en ligne à partir d'**un seul fichier zip**, fabriqué et vérifié
automatiquement : `portfolio-hostinger-AAAAMMJJ.zip` (la date de fabrication
remplace `AAAAMMJJ`, par exemple `portfolio-hostinger-20261103.zip`).

Deux façons de l'obtenir :

| Méthode | Quand l'utiliser |
|---|---|
| **En local** : `bash scripts/build-release.sh` | Vous avez le dépôt sur votre ordinateur. |
| **Sur GitHub** : onglet **Actions**, artefact du job « paquet » | Rien à installer ; la version a passé tous les tests. |

Le guide de mise en ligne pas à pas reste dans le [README](../README.md)
(section 4, « Mise en ligne sur Hostinger »). Ce document explique d'où vient le
zip, ce qu'il contient et ce que vérifie l'intégration continue.

---

## 1. Contenu du zip

| Élément (racine du zip) | Destination chez Hostinger |
|---|---|
| `public_html/` | son **contenu** va dans `domains/votre-domaine.fr/public_html/` (avec les fichiers cachés `.htaccess`) |
| `app/` | le dossier va **à côté**, dans `domains/votre-domaine.fr/app/` (jamais dans `public_html/`) |
| `database.sql` | à importer avec phpMyAdmin (**nouvelle installation uniquement**), puis à ne pas laisser sur le serveur |
| `LISEZMOI.txt` | aide-mémoire de l'installation (15 lignes au plus), avec la date et le commit livrés |

Structure obtenue sur le serveur :

```text
domains/votre-domaine.fr/
├── app/                  ← code, modèle de configuration, dossiers storage/ vides
│   ├── config.example.php   (à copier en config.php puis à remplir)
│   ├── tools/create-admin.php
│   └── storage/logs/ sessions/ cv/
└── public_html/          ← partie publique
    ├── .htaccess
    ├── index.php
    ├── setup-admin.php      (à supprimer après la création du compte)
    ├── assets/
    └── uploads/.htaccess + uploads/projects/
```

**Jamais dans le zip** (le script refuse de produire un paquet qui en contient) :

| Exclu | Pourquoi |
|---|---|
| `app/config.php` (et ses copies `config.*`) | contient vos identifiants : chaque serveur a le sien |
| `app/storage/logs/*`, `sessions/*`, `cv/*` | données de votre poste (journaux, sessions, CV de test) |
| `public_html/uploads/projects/*` | images envoyées en local |
| fichiers `_demo*` de `public_html/` | pages de démonstration |
| `app/tools/dev-router.php`, `app/tools/generate-images.php` | outils de développement |
| `tests/`, `docs/`, `scripts/`, `database/`, `.git/`, `.github/`, `README.md` | inutiles en ligne |
| fichiers cachés (sauf `.htaccess`, `.gitkeep`), sauvegardes `*.bak`, `*~`, `*.swp`, `*.log` | fichiers parasites |

Comme le zip ne contient **aucune de vos données** (ni `config.php`, ni images,
ni CV), le recopier sur un site déjà en ligne n'écrase rien de ce que vous avez
saisi ou envoyé.

---

## 2. Produire le zip en local

```bash
bash scripts/build-release.sh            # fabrique et vérifie le paquet
bash scripts/build-release.sh --detail   # idem, en listant chaque fichier du zip
```

- Fonctionne depuis **n'importe quel dossier** (Linux, macOS, WSL ou Git Bash sous
  Windows) : les chemins sont calculés à partir de l'emplacement du script.
- Outils : `bash`, et `zip` ou, à défaut, **Python 3**. Si `php` est installé, la
  syntaxe de chaque fichier PHP livré est aussi contrôlée (`php -l`).
- Résultat : `dist/portfolio-hostinger-AAAAMMJJ.zip` (le dossier `dist/` est
  ignoré par Git). Relancer le script le même jour remplace le fichier.

Après fabrication, le script **relit l'archive elle-même** et vérifie :

1. la présence des éléments indispensables (`index.php`, les trois `.htaccess`,
   `setup-admin.php`, `config.example.php`, `create-admin.php`, les dossiers
   `storage/`, `database.sql`, `LISEZMOI.txt`) ;
2. que la racine ne contient que `public_html/`, `app/`, `database.sql` et
   `LISEZMOI.txt` ;
3. l'**absence** de `app/config.php`, `tests/`, `docs/`, `scripts/`, `.git`,
   sessions, journaux, images envoyées et outils de développement ;
4. l'**absence de secret** : aucune chaîne de 64 caractères hexadécimaux (le format
   de `app_key`) dans un fichier texte, hors `config.example.php`.

Il affiche ensuite le contenu (résumé par dossier), la taille et l'empreinte
SHA-256. Si une vérification échoue, **le zip est supprimé** et le script s'arrête
en erreur : un paquet douteux ne peut pas être envoyé par mégarde.

---

## 3. Télécharger le zip depuis GitHub (onglet Actions)

À chaque poussée sur la branche `main`, l'intégration continue fabrique le paquet,
**seulement si tous les contrôles et tests ont réussi**.

1. Sur la page du dépôt GitHub, ouvrez l'onglet **Actions**, puis le workflow
   **CI** dans la colonne de gauche.
2. Cliquez sur la dernière exécution **réussie** (coche verte) de la branche `main`.
   Pour en fabriquer une à la demande : bouton **Run workflow**, choisissez la
   branche, puis **Run workflow**.
3. En bas de la page de l'exécution, section **Artifacts** : téléchargez
   `portfolio-hostinger-AAAAMMJJ-xxxxxxx` (`xxxxxxx` = début du numéro de commit).
   Il faut être connecté avec un compte qui a accès au dépôt.
4. GitHub emballe toujours un artefact dans un zip : **décompressez-le une fois**
   pour obtenir `portfolio-hostinger-AAAAMMJJ.zip`, le paquet à envoyer.
5. Facultatif : comparez l'empreinte SHA-256 affichée dans le résumé de l'exécution
   avec celle du fichier (`sha256sum fichier.zip` sous Linux,
   `Get-FileHash fichier.zip` dans PowerShell).

L'artefact est conservé **30 jours**. Passé ce délai, relancez le workflow
(**Run workflow**) pour en obtenir un nouveau.

---

## 4. Utiliser le zip chez Hostinger

**Nouvelle installation** : suivez les étapes du README (section 4). En résumé :

1. Décompressez le paquet sur votre ordinateur.
2. hPanel > **Fichiers > Gestionnaire de fichiers** : envoyez le **contenu** de
   `public_html/` dans `domains/votre-domaine.fr/public_html/` et le dossier `app/`
   dans `domains/votre-domaine.fr/` (supprimez le `default.php` de Hostinger
   s'il existe).
3. Vérifiez la structure : `domains/votre-domaine.fr/app/bootstrap.php` et
   `domains/votre-domaine.fr/public_html/index.php` (et non
   `public_html/public_html/`).
4. Importez `database.sql` avec phpMyAdmin, créez `app/config.php` à partir de
   `app/config.example.php`, puis créez le compte avec `/setup-admin.php` et
   supprimez ce fichier.

**Mise à jour d'un site en ligne** :

- faites d'abord une sauvegarde (README, section 8) ;
- recopiez `public_html/` et `app/` par-dessus : `config.php`, les images et le CV
  ne sont pas dans le zip, ils restent en place ;
- **ne réimportez jamais `database.sql`** (il supprime puis recrée toutes les
  tables) : appliquez seulement les nouvelles migrations de `database/migrations/`
  du dépôt ([docs/MIGRATIONS.md](MIGRATIONS.md)) ;
- le paquet contient `setup-admin.php` : **ne le recopiez pas** sur un site déjà
  installé (ou supprimez-le aussitôt). Il reste inactif tant que `setup_token` est
  vide et qu'un compte existe, mais il n'a rien à faire en ligne.

Pour vérifier le site après l'envoi (requêtes de lecture seulement, aucun
formulaire envoyé) :

```bash
bash scripts/smoke-test.sh https://www.votre-domaine.fr
```

Le script contrôle que les pages répondent, que la page 404 du site s'affiche,
que l'administration exige la connexion, que les fichiers sensibles sont refusés
(403), ainsi que les en-têtes de sécurité (CSP stricte, HSTS…) et les options du
cookie de session.

---

## 5. L'intégration continue (`.github/workflows/ci.yml`)

Lancée à chaque `push` et chaque `pull_request`, et à la demande (**Run workflow**).

| Job | Vérifie | Échoue si |
|---|---|---|
| **lint** (PHP 8.1, 8.2, 8.3) | `php -l` sur tous les fichiers PHP ; `scripts/check-conventions.php` ; `shellcheck` sur `scripts/*.sh` | erreur de syntaxe avec l'une des versions proposées par Hostinger ; tiret long « — » ou moyen « – » dans `app/Views` ou dans les données de `database.sql` ; `style="…"`, `<style>`, `<script>` en ligne (hors JSON-LD `application/ld+json`), `onclick=`… ou `javascript:` dans `app/Views` |
| **tests** (PHP 8.1 et 8.3 avec MySQL 8.0 ; PHP 8.3 avec MariaDB 10.11) | import de `database.sql` ; migrations relançables ; `tests/core-test.php` puis chaque `tests/*-test.php` sur une base neuve (`portfolio_test_ci`) ; visite HTTP du site avec `scripts/smoke-test.sh` | un test échoue, une migration n'est pas relançable, une page répond mal ou une erreur PHP est journalisée |
| **paquet** (branche `main` et lancement manuel) | `scripts/build-release.sh --detail`, puis publication du zip comme artefact (30 jours) | **lint** ou **tests** a échoué, ou une vérification du paquet échoue |

Les erreurs apparaissent en annotations (onglet **Files changed** d'une pull
request, ou résumé de l'exécution). Pour `tests/totp-test.php`, la CI installe
les décodeurs de QR codes (zxing-cpp et OpenCV) ; s'ils sont indisponibles, le
test vérifie tout le reste et la CI affiche un avertissement.

**Sécurité de la CI** :

- le jeton de GitHub n'a que le droit de **lecture** (`permissions: contents: read`)
  et n'est pas conservé dans la copie du dépôt (`persist-credentials: false`) ;
- aucun secret n'est nécessaire : la base de test est jetable (créée puis détruite
  avec la machine, sans accès root), `app/config.php` est généré à chaque
  exécution avec une `app_key` aléatoire masquée dans les journaux ;
- le paquet n'est fabriqué qu'après la réussite de tous les contrôles.

**Refaire les contrôles en local** avant de pousser :

```bash
php scripts/check-conventions.php                         # tirets et CSP
find . -name '*.php' -not -path './.git/*' -exec php -l {} \; | grep -v '^No syntax errors'
php tests/core-test.php
PF_DB_NAME=portfolio_test_models php tests/models-test.php  # VIDE la base visée
php tests/totp-test.php
php -S 127.0.0.1:8080 -t public_html app/tools/dev-router.php &   # puis :
bash scripts/smoke-test.sh http://127.0.0.1:8080
bash scripts/build-release.sh
```
