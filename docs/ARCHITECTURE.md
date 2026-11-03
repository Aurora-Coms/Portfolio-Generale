# Architecture du portfolio

Ce document est le **contrat technique** du projet : structure, conventions,
API internes. Toute contribution doit le respecter.

## 1. Vue d'ensemble

- PHP 8.1+ natif, sans framework ni Composer. MySQL/MariaDB via PDO.
- CSS et JavaScript vanilla, aucun build.
- Mini-MVC : `app/Core` (noyau), `app/Models` (accès aux données, SQL),
  `app/Controllers` (logique), `app/Views` (affichage).
- `app/` est **hors du web root** ; seul `public_html/` est publié.
- Toutes les requêtes passent par `public_html/index.php` → `App\Core\App::run()`
  → `app/routes.php` → contrôleur.

```
app/
  bootstrap.php        démarrage (autoload, config, erreurs)
  helpers.php          fonctions globales : e(), md(), csrf_field(), asset(), icon()…
  routes.php           table des routes (NE PAS MODIFIER sans raison)
  config.php           identifiants (non versionné) / config.example.php (modèle)
  Core/                noyau (Router, View, Database, Session, Csrf, Auth, Validator…)
  Models/              une classe statique par table
  Controllers/Site/    pages publiques
  Controllers/Admin/   administration (héritent de App\Core\AdminController)
  Views/layouts/       site.php, admin.php, admin-auth.php
  Views/partials/      fragments réutilisables (site/…, admin/…)
  Views/site/…         vues publiques
  Views/admin/…        vues d'administration
  storage/             logs, sessions, cv (non publics)
  tools/               dev-router.php, create-admin.php
public_html/
  index.php            contrôleur frontal
  .htaccess            réécriture, en-têtes de sécurité, HTTPS
  assets/css|js|fonts|img
  uploads/projects/    images générées (exécution interdite)
  setup-admin.php      création du 1er admin (à supprimer après usage)
database.sql           schéma + données de départ (nouvelle installation)
database/migrations/   mises à jour d'une base déjà installée (voir docs/MIGRATIONS.md)
tests/                 tests (non déployés)
docs/                  documentation (non déployée)
```

## 2. Règles impératives

### Sécurité
1. **SQL** : uniquement `App\Core\Database` avec paramètres (`?`). Jamais de
   variable concaténée dans une requête. Les noms de colonnes/tri dynamiques
   passent par une liste blanche (`match`).
2. **Sorties** : toute donnée dynamique dans une vue passe par `e()`.
   Exceptions : `md($texte)` (Markdown déjà échappé), `partial()`, `icon()`,
   `csrf_field()`.
3. **CSRF** : chaque `<form method="post">` contient `<?= csrf_field() ?>`.
   Le routeur vérifie le jeton de toutes les requêtes POST automatiquement.
4. **CSP stricte** : AUCUN `<script>` en ligne, AUCUN attribut `style="…"`,
   AUCUN gestionnaire `onclick=` etc. Tout le JS est dans `assets/js/*.js`,
   tout le style dans `assets/css/*.css`. Pour une largeur variable (barre de
   niveau), utiliser des classes (`level-3`) ou `data-*` + JS (`el.style.x = …`
   est autorisé en JS).
5. **Admin** : tout contrôleur d'administration (sauf `AuthController`) hérite
   de `App\Core\AdminController` (connexion exigée dans le constructeur).
6. **Redirections** : `redirect('/chemin')` uniquement (chemins internes).
7. **Uploads** : `ImageProcessor::processUpload()` et `PdfUpload::store()`
   uniquement ; attraper `App\Core\UploadException` et afficher son message.
8. **Erreurs** : lever `App\Core\HttpException(404)` pour une ressource
   absente. Ne jamais afficher `$e->getMessage()` d'une exception technique.

### Rédaction (textes visibles)
- Français, ton professionnel.
- **Jamais de tiret long « — » ni de tiret moyen « – »** dans les textes
  visibles : utiliser « · » (point médian) ou « : ». Titres de page :
  `Projets · Alexandre Derbez`.
- Code et commentaires en français.

### Contrôleurs
- Pattern PRG : en cas d'erreur de validation, re-rendre le formulaire avec
  `$errors` (tableau champ ⇒ message) et `$old` (données saisies), statut 422 ;
  en cas de succès, `Session::flash('success', '…')` puis `redirect()`.
- Validation : `App\Core\Validator`.
- Vues site : `$this->render('site/…', $data)` ; admin : `$this->render('admin/…', $data)`.
- Chaque vue reçoit `meta` : `['title' => …, 'description' => …, 'canonical' => '/chemin', 'og_image' => '/…', 'robots' => 'noindex']` (tout optionnel).

## 3. Noyau (`app/Core`) : API disponible

| Classe | Méthodes principales |
|---|---|
| `Database` | `fetchAll($sql, $p)`, `fetch()`, `value()`, `insert()` → id, `execute()` → nb lignes, `transaction(fn)`, `placeholders($arr)` |
| `View` | `render($tpl, $data, $layout, $status)`, `renderToString()`, `partial($name, $data)` |
| `Request` | `method()`, `isPost()`, `path()`, `input($k)`, `query($k)`, `files($k)` (liste normalisée), `ip()`, `isHttps()` |
| `Session` | `start()`, `get/set/forget`, `flash($type, $msg)` (types : success, error, warning, info), `regenerate()`, `destroy()` |
| `Csrf` | `token()`, `field()`, `rotate()` |
| `Auth` | `attempt($u, $p)` → `ok|2fa|invalid|locked`, `pendingSecondFactor()`, `attemptSecondFactor($code)` → `ok|invalid|locked|expired`, `check()`, `user()`, `id()`, `logout()` |
| `Throttle` | `tooMany($action, $value, $max, $window)`, `hit()`, `clear()`, `count()` |
| `Validator` | `string()`, `text()`, `email()`, `url()`, `int()`, `bool()`, `date()`, `in()`, `slug()`, `youtubeId()`, `ids()`, `addError()`, `fails()`, `errors()` |
| `Slug` | `make($texte)` |
| `Markdown` | `render($txt)`, `plain($txt)` |
| `Mailer` | `send($to, $subject, $body, $replyTo, $replyName)` → bool |
| `ImageProcessor` | `processUpload($file, $dir)` → `[filename, thumb_filename, width, height]`, `deleteFiles($dir, ...$noms)` |
| `PdfUpload` | `store($file)`, `exists()`, `delete()`, `path()` |
| `Crypto` | `encrypt($txt, $ctx)`, `decrypt($b64, $ctx)` |
| `Totp` | `generateSecret()` → base32, `verify($secret, $code, ?$lastStep)` → ?int (pas accepté), `currentStep()`, `provisioningUri($secret, $label, $issuer)` |
| `QrCode` | `svg($texte, $taillePx)` → `<svg>` (sûr, sans style en ligne) |

Helpers (`app/helpers.php`) : `e`, `md`, `csrf_field`, `abs_url`, `asset`,
`upload_url`, `setting`, `partial`, `redirect`, `flashes`, `old`,
`is_current`, `date_fr`, `period_fr`, `datetime_fr`, `month_fr`,
`level_label`, `section_label`, `status_label`, `timeline_type_label`,
`str_limit`, `icon($nom, $taille, $classe)`.

Constante dossier images : `PUBLIC_PATH . '/uploads/projects'`.

## 4. Modèles (`app/Models`) : API

Toutes les méthodes sont statiques. Les lignes sont des tableaux associatifs
(colonnes de la table). Booléens SQL = entiers 0/1.

### Project
- `published(string $section, ?string $categorySlug = null): list` : projets publiés d'une section
  (`game|tech`), triés par `position`, puis `project_date` DESC. Chaque ligne a en plus :
  `cover` (`null` ou `['thumb_filename','filename','alt_text','width','height']`),
  `categories` (liste `['id','name','slug']`), `tags` (liste `['id','name','slug']`).
- `featured(int $limit = 4): list` : publiés ET mis en avant, toutes sections, même format.
- `findPublishedBySlug(string $slug): ?array` : même format + `images` (toutes, par position).
- `adminList(?string $section = null): list` : tous (brouillons compris) + `image_count`, `categories`.
- `find(int $id): ?array` : ligne brute + `category_ids` (list<int>) + `tag_ids` (list<int>).
- `create(array $data, array $categoryIds, array $tagIds): int`
- `update(int $id, array $data, array $categoryIds, array $tagIds): void`
  (`$data` : colonnes de `projects` hors id/dates ; liaisons remplacées ; catégories d'une autre
  section ignorées).
- `delete(int $id): list<array>` : supprime et renvoie les images (pour effacer les fichiers).
- `uniqueSlug(string $base, ?int $exceptId = null): string`
- `toggle(int $id, string $field): void` (`is_published` ou `is_featured` seulement).
- `counts(): array` : `['game' => n, 'tech' => n, 'published' => n, 'drafts' => n, 'featured' => n]`.

### Category
- `forSection(string $section, bool $onlyUsed = false): list` (+ `project_count` = projets publiés)
- `all(): list` (triées par section, position), `find(int $id)`, `create(array $d): int`,
  `update(int $id, array $d)`, `delete(int $id)`, `slugExists(string $section, string $slug, ?int $except)`.

### Tag
- `all(): list` (+ `project_count`), `find`, `create`, `update`, `delete`,
  `slugExists(string $slug, ?int $except)`, `nameExists(string $name, ?int $except)`.

### ProjectImage
- `forProject(int $projectId): list`, `find(int $id): ?array`,
  `add(int $projectId, array $file, string $alt): int` (`$file` = résultat d'ImageProcessor ; position en dernier),
  `updateAlt(int $id, string $alt)`, `delete(int $id): ?array` (renvoie la ligne supprimée),
  `moveUp(int $id)`, `moveDown(int $id)`, `makeCover(int $id)`, `countForProject(int $projectId): int`.

### TimelineEntry
- `published(): list` (par `start_date` DESC, puis `position`), `all()`, `find`, `create`, `update`, `delete`.

### SkillTheme (thèmes de compétences : niveau le plus haut)
- `tree(bool $includeEmpty = false): list` : thèmes (par position) avec clé `categories` (par position),
  chacune avec clé `skills` (par position). 3 requêtes. Sans `$includeEmpty`, les sous-catégories sans
  compétence et les thèmes sans sous-catégorie affichable sont exclus (page publique).
- `all()` (+ `category_count`), `find`, `create`, `update`, `delete(int $id): bool` (false s'il contient des
  sous-catégories), `slugExists(string $slug, ?int $except)`.

### SkillCategory (sous-catégories de compétences, rangées dans un thème)
- Colonnes : `theme_id` (obligatoire, clé étrangère ON DELETE RESTRICT), `name`, `slug`, `icon`, `position`.
- `withSkills(): list` : catégories (par position) avec clé `skills` (liste par position). Conservée pour
  compatibilité ; préférer `SkillTheme::tree()`.
- `all()` (+ `theme_name`, `skill_count` ; triées par thème puis position), `find`, `create`, `update`,
  `delete(int $id): bool` (false si elle contient des compétences), `slugExists(string $slug, ?int $except)`.

### Skill
- Colonnes : `category_id`, `name`, `level` (1 à 5 ou `null` = sans barre de niveau), `level_label`
  (≤ 40, libellé affiché à la place de `level_label()`, ex. « A2 »), `description`, `position`.
- `all(): list` (+ `category_name`, `theme_name`), `find`, `create`, `update`, `delete`.

### Interest
- `all()`, `find`, `create`, `update`, `delete`.

### Message
- `create(string $name, string $email, string $subject, string $body): int`, `markMailSent(int $id)`,
  `paginate(int $page, int $perPage = 20): array{items: list, total: int}`, `find(int $id)`,
  `markRead(int $id)`, `toggleRead(int $id)`, `delete(int $id)`, `unreadCount(): int`,
  `latest(int $limit): list`, `purgeOlderThan(int $months = 12): int`.

### Setting (fait) / Admin (fait)
- `Setting::all()`, `Setting::get($k, $def)`, `Setting::setMany([...])`, `Setting::DEFAULTS`.
- `Admin` : voir `app/Models/Admin.php`.

## 5. Design

Thème « éditeur de code » sombre (maquette E validée). Jetons : voir
`public_html/assets/css/site.css` (`:root`) et `docs/STYLEGUIDE.md` pour le
vocabulaire de classes à utiliser dans les vues.

## 6. Tests locaux

```bash
mysqld_safe &                    # MariaDB
php -S 127.0.0.1:8080 -t public_html app/tools/dev-router.php
php tests/core-test.php
```
Une base de test isolée peut être utilisée : `PF_DB_NAME=portfolio_test_x`
(droits accordés sur `portfolio_test%`).
