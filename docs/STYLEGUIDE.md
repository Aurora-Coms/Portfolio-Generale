# Guide de style : classes CSS, partials et comportements JS

Ce document est la **référence unique** pour écrire les vues (`app/Views/site/…`
et `app/Views/admin/…`). Tout ce qui est décrit ici existe déjà dans
`public_html/assets/css/site.css`, `admin.css`, `assets/js/site.js`, `admin.js`
et `app/Views/partials/`. N'inventez pas de nouvelle classe sans l'ajouter
au CSS **et** à ce document.

Sommaire :

1. [Règles à respecter dans toute vue](#1-règles-à-respecter-dans-toute-vue)
2. [Jetons de design](#2-jetons-de-design)
3. [Gabarits (layouts)](#3-gabarits-layouts)
4. [Site public : structure de page](#4-site-public--structure-de-page)
5. [Site public : composants](#5-site-public--composants)
6. [Site public : formulaires](#6-site-public--formulaires)
7. [Site public : comportements JavaScript](#7-site-public--comportements-javascript-sitejs)
8. [Administration : composants](#8-administration--composants)
9. [Administration : formulaires](#9-administration--formulaires)
10. [Administration : comportements JavaScript](#10-administration--comportements-javascript-adminjs)
11. [Pages d'authentification et d'erreur](#11-pages-dauthentification-et-derreur)
12. [Images et icônes](#12-images-et-icônes)
13. [Pièges connus](#13-pièges-connus)

---

## 1. Règles à respecter dans toute vue

- **CSP stricte** : aucun `<script>` en ligne, aucun attribut `style="…"`,
  aucun `onclick=`/`onload=`… Tout comportement passe par un attribut `data-*`
  décrit ici. Une largeur variable passe par une classe (`level-3`).
- **Échappement** : toute donnée dynamique passe par `e()`. Seuls `md()`,
  `partial()`, `icon()` et `csrf_field()` s'affichent sans `e()`.
- **Formulaires POST** : toujours `<?= csrf_field() ?>`. Une action
  (supprimer, publier, déconnexion…) est **toujours** un formulaire POST, jamais
  un lien GET : utilisez `partial('admin/post-button', …)`.
- **Textes** : français, jamais de tiret long « — » ni moyen « – » : utilisez
  « · » ou « : ».
- **Un seul `<h1>` par page** : fourni par le hero, `site/page-header`,
  `.project-head__title` ou `admin/page-header`. Puis `h2` pour les sections,
  `h3` pour les cartes (les partials ont un paramètre `headingLevel`).
- **Lien externe** : `rel="noopener noreferrer"`. N'acceptez que des URL
  `http(s)://` venant de la base (jamais `javascript:`).
- **Icône seule** : toujours un nom accessible (`aria-label` ou texte `.sr-only`).
- **Messages flash** : affichés par les gabarits, ne les appelez pas dans les vues.

---

## 2. Jetons de design

Définis sur `:root` (identiques dans `site.css` et `admin.css`) :

| Variable | Valeur | Usage |
|---|---|---|
| `--c-bg` | `#13141B` | fond de page |
| `--c-chrome` | `#0F1016` | en-tête, pied de page, barre latérale |
| `--c-panel` | `#1A1C25` | cartes, panneaux |
| `--c-panel-2` | `#15161E` | zones secondaires, champs, médias |
| `--c-border` / `--c-border-strong` | `#272A37` / `#343849` | bordures |
| `--c-input-border` | `#6B7189` | bordure des champs (contraste 3.7:1, WCAG 1.4.11) |
| `--c-text` | `#D5D8E4` | texte courant (12.9:1 sur le fond) |
| `--c-text-strong` | `#F1F2F7` | titres |
| `--c-text-soft` | `#ABB0C4` | paragraphes secondaires (7.9:1) |
| `--c-text-muted` | `#8C91A8` | libellés discrets (5.4:1 sur panneau) |
| `--c-comment` | `#7D839B` | « -- commentaires » (4.5:1 sur panneau ; jamais sur une puce) |
| `--c-lineno` | `#656B82` | numéros de ligne (décoratifs, `aria-hidden`) |
| `--c-accent` | `#C3A6FF` | violet : mot-clé, actif, focus (8.3:1) |
| `--c-accent-ink` | `#1A1030` | texte posé sur l'accent (8.8:1) |
| `--c-accent-soft` | `rgba(195,166,255,.14)` | fond accent doux |
| `--c-string` | `#9FE0A3` | vert : chaînes, succès, disponibilité |
| `--c-number` | `#F5A66B` | orange : nombres, « en cours », stages |
| `--c-chip` | `#232633` | fond des tags |
| `--c-success` / `--c-warning` / `--c-error` / `--c-info` | vert / jaune `#F5D76B` / rouge `#FF8F8F` / violet | alertes |

Polices locales (`@font-face`, `font-display: swap`) : **Inter** 400/500/600/700
pour le texte courant (`--font-sans`), **JetBrains Mono** 400/500/700 pour titres
`h1`/`h2`, libellés, code (`--font-mono`). Classe utilitaire : `.mono`.

Points de rupture (mobile d'abord) : `640px` (tablette), `900px` (ordinateur),
`1024px` / `1200px` (grand écran). Conteneur : 1240 px max, gouttières 16 px
(mobile), 24 px (≥ 640), 32 px (≥ 900).

Focus : `:focus-visible` = contour accent 2 px sur tout élément interactif.
`prefers-reduced-motion: reduce` : toutes les animations et transitions sont
coupées (CSS et JS).

---

## 3. Gabarits (layouts)

### `layouts/site` (défaut de `Controller::render`)

Variables : `$content` (fourni), `$meta` (optionnel), `$bodyClass` (optionnel).

```php
$this->render('site/projects/index', [
    'projects' => $projects,
    'meta' => [
        'title'       => 'Projets de jeux',          // <title> « Projets de jeux · Alexandre Derbez »
        'description' => 'Jeux en Lua sur Roblox…',  // sinon setting('meta_description')
        'canonical'   => '/projets/jeux',             // sinon le chemin courant (sans ?query)
        'og_image'    => upload_url($cover['filename']), // sinon /assets/img/og-default.png (1200x630)
        'og_image_width' => 1280, 'og_image_height' => 720, // si og_image fourni (facultatif)
        'og_image_alt'   => 'Capture du TARDIS',
        'og_type'     => 'article',                   // défaut : profile sur /, website ailleurs
        'robots'      => 'noindex',                   // facultatif ; pas de canonical si noindex
    ],
]);
```

Le gabarit produit : `<title>`, description, canonical absolu, Open Graph
complet (URL absolues via `abs_url`), Twitter `summary_large_image`, JSON-LD
`Person`, favicons, lien d'évitement « Aller au contenu », en-tête à onglets,
bandeau de stage, flashes, `<main id="contenu">` contenant un `.container`,
pied de page « barre d'état ».

**Votre vue est déjà à l'intérieur de `<main><div class="container">`** :
n'ajoutez pas de conteneur supplémentaire. Pour une colonne étroite :
`<div class="container--narrow">` n'est pas nécessaire, utilisez
`.prose` (72 caractères max) ou une classe de section.

### `layouts/admin` (défaut de `AdminController::render`)

Variables : `$content`, `$meta` (seul `title` est utilisé ; `noindex` forcé).
Fournit la barre latérale (menu repliable sur mobile, badge des messages non
lus, « Voir le site », formulaire POST de déconnexion), les flashes et
`<main id="contenu" class="admin-content">`. **Chaque vue admin commence par
`partial('admin/page-header', …)`.**

### `layouts/admin-auth`

Carte centrée (connexion, double authentification, installation, erreurs sous
`/admin`). Variables : `$content`, `$meta['title']`. Voir [§ 11](#11-pages-dauthentification-et-derreur).

```php
View::render('admin/auth/login', ['meta' => ['title' => 'Connexion']], 'layouts/admin-auth');
```

---

## 4. Site public : structure de page

### En-tête de page intérieure : `partial('site/page-header')`

| Variable | Type | Description |
|---|---|---|
| `title` | string | titre `h1` (obligatoire) |
| `comment` | string | commentaire mono au-dessus, ex. `'-- parcours.md'` |
| `lead` | string | phrase d'introduction (texte simple) |
| `breadcrumb` | list | éléments du fil d'Ariane (voir `site/breadcrumb`) |

```php
<?= partial('site/page-header', [
    'comment' => '-- parcours.md',
    'title'   => 'Parcours',
    'lead'    => 'Formation, stages et expériences.',
]) ?>
```

### Sections et titre façon commentaire : `.section` + `partial('site/section-head')`

`.section` ajoute l'espacement vertical (56 px mobile, 80 px ordinateur).

| Variable | Type | Description |
|---|---|---|
| `title` | string | titre (obligatoire) |
| `comment` | string | ex. `'-- projets/'` (décoratif, `aria-hidden`) |
| `id` | string | id du titre, à référencer par `aria-labelledby` |
| `level` | int | niveau de titre (2 par défaut, 2 à 4) |
| `actions` | string | HTML déjà sûr affiché à droite (filtres, bouton) |

```php
<section class="section" aria-labelledby="titre-projets">
<?= partial('site/section-head', ['comment' => '-- projets/', 'title' => 'Projets mis en avant', 'id' => 'titre-projets']) ?>
    …
</section>
```

Classe seule : `.comment` (texte mono couleur commentaire) pour un
« -- commentaire » libre.

### Grilles

| Classe | Effet |
|---|---|
| `.grid-cards` | cartes projet : 1 colonne (mobile), 2 (tablette), jusqu'à 4 (ordinateur), min 260 px |
| `.grid` | grille avec espacement (16/20 px), 1 colonne |
| `.grid.grid--2` | 2 colonnes dès 640 px |
| `.grid.grid--3` | 2 colonnes dès 640 px, 3 dès 1024 px |
| `.stack` / `.stack-sm` / `.stack-lg` | espacement vertical 16 / 8 / 32 px entre enfants |
| `.cluster` | rangée flexible qui passe à la ligne (gap 12 px) |

### Utilitaires

`.sr-only` (masqué visuellement, lu par les lecteurs d'écran), `.mono`,
`.text-soft`, `.text-muted`, `.text-accent`, `.text-center`,
`.container`, `.container--narrow` (820 px).

---

## 5. Site public : composants

### Boutons

| Classe | Rendu |
|---|---|
| `.btn` | base (bordure, 48 px de haut) ; seul = secondaire |
| `.btn.btn--primary` | fond accent, texte encre |
| `.btn.btn--secondary` | bordure forte |
| `.btn.btn--code` | petit, mono, fond accent doux (ex. `contact.sh --stage`) |
| `.btn--sm` | 40 px de haut |
| `.btn--block` | pleine largeur |
| `.btn-row` | rangée de boutons (pleine largeur sur mobile, en ligne dès 640 px) |

```php
<div class="btn-row">
    <a class="btn btn--primary" href="/projets/jeux">Voir mes projets</a>
    <a class="btn btn--secondary" href="/parcours">Mon parcours</a>
    <a class="btn btn--secondary" href="/cv"><?= icon('download') ?>CV (PDF)</a>
    <a class="btn btn--code" href="/contact?sujet=stage">contact.sh --stage</a>
</div>
```

`disabled` ou `aria-disabled="true"` grisent le bouton.

### Hero d'accueil + fenêtre de code

Classes : `.hero` (grille 2 colonnes dès 900 px), `.hero__kicker`,
`.hero__title` (h1), `.hero__subtitle` (accent), `.hero__lead`,
`.hero__actions`, `.hero__visual`.

```php
<section class="hero" aria-labelledby="titre-accueil">
    <div>
        <p class="hero__kicker"><?= e(setting('hero_kicker')) ?></p>
        <h1 class="hero__title" id="titre-accueil" data-typewriter><?= e(setting('site_name')) ?></h1>
        <p class="hero__subtitle"><?= e(setting('hero_title')) ?></p>
        <p class="hero__lead"><?= e(setting('hero_tagline')) ?></p>
        <div class="btn-row hero__actions">…boutons…</div>
    </div>
    <div class="hero__visual">
        <?= partial('site/code-window', [...]) ?>
    </div>
</section>
```

#### `partial('site/code-window')`

| Variable | Type | Description |
|---|---|---|
| `filename` | string | onglet actif, ex. `'profil.lua'` (obligatoire) |
| `tabs` | list&lt;string&gt; | onglets secondaires inactifs, ex. `['cardverse.lua']` |
| `lines` | list&lt;string&gt; | **lignes en HTML déjà sûr** : échappez vous-même avec `e()` |
| `label` | string | nom accessible (défaut « Extrait de code : {filename} ») |
| `cursor` | bool | curseur clignotant ▍ en fin de code (défaut `true`) |
| `class` | string | classes en plus sur la `<figure>` |

Les numéros de ligne sont générés. Coloration Lua : `.tok-kw` (mot-clé,
violet), `.tok-str` (chaîne, vert), `.tok-num` (nombre, orange), `.tok-com`
(commentaire), `.tok-fn` (fonction, blanc).

```php
<?php $s = static fn (string $v): string => '<span class="tok-str">"' . e($v) . '"</span>'; ?>
<?= partial('site/code-window', [
    'filename' => 'profil.lua',
    'tabs'     => ['cardverse.lua'],
    'lines'    => [
        '<span class="tok-com">-- profil.lua</span>',
        '<span class="tok-kw">local</span> alexandre = {',
        '    nom       = ' . $s(setting('site_name')) . ',',
        '    annee     = <span class="tok-num">1</span>,',
        '}',
        '',
        '<span class="tok-kw">return</span> alexandre',
    ],
]) ?>
```

### Bandeau de stage : `partial('site/banner')`

Affiché automatiquement par le gabarit si `setting('banner_enabled') === '1'`
(titre, texte, dates `internship_start`/`internship_end`, bouton
`contact.sh --stage` vers `/contact?sujet=stage`). Ne pas l'appeler dans une vue.
Classes : `.banner`, `.banner__text`.

### Carte projet : `partial('site/project-card')`

| Variable | Type | Description |
|---|---|---|
| `project` | array | ligne de `Project::published()` / `featured()` |
| `headingLevel` | int | niveau du titre (3 par défaut ; 2 si la page n'a pas de h2 au-dessus) |
| `hidden` | bool | carte masquée (filtre actif côté serveur) |
| `reveal` | bool | apparition au défilement (défaut `true`) |

Rendu : en-tête « chemin de fichier » (`roblox/sci-fi/tardis.lua`, construit
depuis les slugs de catégories ; `.lua` pour les jeux), couverture
(`upload_url(thumb_filename)`, `width`/`height`, `loading="lazy"`) ou zone
« -- capture à venir », titre lien vers `/projet/{slug}` étiré sur toute la
carte (aucun lien imbriqué : n'ajoutez pas d'autre lien dans la carte),
résumé, badge de statut, tags `"lua"`. Attribut `data-categories` pour les filtres.

Classes : `.project-card`, `__path`, `__media`, `__body`, `__title`, `__link`,
`__summary`, `__meta`, `.media-placeholder`.

```php
<div class="grid-cards">
<?php foreach ($featured as $project): ?>
    <?= partial('site/project-card', ['project' => $project]) ?>
<?php endforeach; ?>
</div>
```

### Grille filtrable : `partial('site/project-grid')` + `partial('site/project-filters')`

`project-grid` affiche les cartes dans `.grid-cards` et l'état vide intégré.
**Passez la liste COMPLÈTE de la section** (`Project::published($section)`) et
le slug actif : les cartes hors filtre sont rendues avec `hidden`, le JS peut
alors les réafficher instantanément.

| `site/project-grid` | Type | Description |
|---|---|---|
| `projects` | list | projets (format `Project::published()`) |
| `id` | string | id de la grille, cible des filtres (défaut `'projets'`) |
| `active` | ?string | slug de catégorie actif (`null` = toutes) |
| `headingLevel` | int | niveau des titres des cartes (3) |
| `emptyTitle` / `emptyText` | string | textes de l'état vide |

| `site/project-filters` | Type | Description |
|---|---|---|
| `categories` | list | `Category::forSection($section, true)` : `name`, `slug`, `project_count` |
| `active` | ?string | slug actif |
| `basePath` | string | chemin de la page, ex. `'/projets/jeux'` |
| `target` | string | id de la grille (défaut `'projets'`) |
| `allLabel` | string | puce « toutes » (défaut `'*.lua'`) |
| `label` | string | nom accessible du groupe |
| `showCount` | bool | nombre de projets par puce |

Sans JS : les puces sont des liens `?categorie=slug` (`aria-current="page"`
sur l'active). Avec JS : voir [§ 7](#filtres-de-projets).

```php
<?php $active = $categorySlug; // Request::query('categorie') validé, ou null ?>
<section class="section" aria-labelledby="titre-jeux">
<?= partial('site/section-head', [
    'comment' => '-- roblox/',
    'title'   => 'Projets de jeux',
    'id'      => 'titre-jeux',
    'actions' => partial('site/project-filters', [
        'categories' => $categories, 'active' => $active, 'basePath' => '/projets/jeux',
    ]),
]) ?>
<?= partial('site/project-grid', ['projects' => $projects, 'active' => $active]) ?>
</section>
```

Classes : `.filters`, `.filters__list`, `.chip` (puce mono, état actif via
`aria-current="page"`, `aria-current="true"` ou `aria-pressed="true"`),
`.chip__count`.

### État vide : `partial('site/empty-state')` (site et admin)

| Variable | Type | Description |
|---|---|---|
| `title` | string | titre (obligatoire) |
| `text` | string | explication |
| `comment` | string | ex. `'-- 0 résultat'` |
| `icon` | string | nom d'icône (défaut `'folder'`) |
| `actions` | string | HTML déjà sûr (boutons) |

```php
<?= partial('site/empty-state', [
    'comment' => '-- 0 résultat',
    'title'   => 'Aucun projet pour le moment',
    'text'    => 'Revenez bientôt.',
    'actions' => '<a class="btn btn--secondary btn--sm" href="/">Accueil</a>',
]) ?>
```

### Badges et tags

| Classe | Rendu |
|---|---|
| `.badge` | pastille arrondie mono avec point |
| `.badge--done` / `--success` | vert (« Terminé ») |
| `.badge--progress` / `--warning` | orange (« En cours ») |
| `.badge--accent` | violet |
| `.badge--error` | rouge |
| `.badge--plain` | sans point |
| `.tags` > `.tag` | liste de tags façon chaîne Lua `"lua"` (guillemets décoratifs non lus) |
| `.tag--plain` | tag sans guillemets |

```php
<span class="badge badge--<?= $project['status'] === 'done' ? 'done' : 'progress' ?>"><?= e(status_label($project['status'])) ?></span>
<ul class="tags" aria-label="Technologies">
<?php foreach ($project['tags'] as $tag): ?>
    <li class="tag"><?= e($tag['name']) ?></li>
<?php endforeach; ?>
</ul>
```

### Alertes : `partial('site/alert')` (site et admin)

| Variable | Type | Description |
|---|---|---|
| `type` | string | `success` / `error` / `warning` / `info` (défaut `info`) |
| `message` | string | texte simple |
| `title` | string | titre en gras |
| `html` | string | HTML déjà sûr ajouté après le message (`md()`, liste d'erreurs…) |
| `role` | string | `status` / `alert` / `''` (défaut : `alert` pour error/warning, `status` sinon ; `''` pour une alerte statique) |

```php
<?= partial('site/alert', ['type' => 'error', 'title' => 'Le formulaire contient des erreurs', 'message' => 'Corrigez les champs signalés.']) ?>
```

Classes : `.alert.alert--{type}`, `.alert__body`, `.alert__title`.
Conteneur de plusieurs alertes : `.flashes`.

### Fil d'Ariane : `partial('site/breadcrumb')`

`items` : liste `['label' => …, 'href' => …]`, du général au précis ; le
dernier (page courante) sans `href` reçoit `aria-current="page"`.

```php
<?= partial('site/breadcrumb', ['items' => [
    ['label' => 'Accueil', 'href' => '/'],
    ['label' => 'Projets de jeux', 'href' => '/projets/jeux'],
    ['label' => $project['title']],
]]) ?>
```

### Pagination : `partial('site/pagination')` (site et admin)

| Variable | Type | Description |
|---|---|---|
| `page` | int | page courante (1 = première) |
| `pages` | int | nombre total ; rien n'est affiché si ≤ 1 |
| `path` | string | chemin de la liste (défaut : chemin courant) |
| `query` | array | autres paramètres à conserver |
| `param` | string | nom du paramètre (défaut `'page'`) |

```php
<?= partial('site/pagination', ['page' => $page, 'pages' => (int) ceil($total / 20), 'path' => '/admin/messages']) ?>
```

### Page détail d'un projet

```php
<article>
    <header class="project-head">
        <?= partial('site/breadcrumb', ['items' => [...]]) ?>
        <h1 class="project-head__title"><?= e($project['title']) ?></h1>
        <div class="project-head__meta">
            <span class="badge badge--done">Terminé</span>
            <span class="meta-item"><?= icon('calendar', 16) ?><?= e(date_fr($project['project_date'], 'month')) ?></span>
            <span class="meta-item"><?= icon('folder', 16) ?>Roblox, Sci-fi</span>
        </div>
        <p class="project-head__summary"><?= e($project['summary']) ?></p>
    </header>

    <div class="detail-layout">
        <div class="detail-main">
            <?= partial('site/gallery', ['images' => $project['images'], 'title' => $project['title']]) ?>
            <?= partial('site/youtube', ['id' => $project['youtube_id'], 'title' => $project['title']]) ?>
            <section aria-labelledby="titre-description">
                <h2 class="content-block__title" id="titre-description">Description</h2>
                <div class="prose"><?= md($project['body']) ?></div>
            </section>
        </div>
        <aside class="detail-aside card card--pad" aria-label="Informations">
            <dl class="info-list">
                <div class="info-list__row"><dt>statut</dt><dd>…</dd></div>
                <div class="info-list__row"><dt>technologies</dt><dd><ul class="tags">…</ul></dd></div>
            </dl>
        </aside>
    </div>
</article>
```

Classes : `.project-head`, `__title`, `__meta`, `__summary`, `.meta-item`,
`.detail-layout` (2 colonnes dès 1024 px, aside collant), `.detail-main`
(blocs espacés de 40 px), `.detail-aside`, `.content-block__title` (h2 mono
précédé de « ## »), `.info-list` / `__row` (`dt` mono discret, `dd`).

#### Galerie : `partial('site/gallery')`

| Variable | Type | Description |
|---|---|---|
| `images` | list | `filename`, `thumb_filename`, `alt_text`, `width`, `height` |
| `title` | string | titre du projet (alt de repli : « {title} : capture N ») |
| `id` | string | groupe de la visionneuse (défaut `'galerie'`) |

Grande image + miniatures, chaque lien `data-lightbox` (voir § 7). Rien si la
liste est vide. Classes : `.gallery__main`, `.gallery__thumbs`,
`.gallery__thumb`, `.gallery__hint`, `.lightbox*` (créée par le JS).

#### Vidéo YouTube : `partial('site/youtube')`

| Variable | Type | Description |
|---|---|---|
| `id` | string | identifiant YouTube (11 caractères ; sinon rien n'est affiché) |
| `title` | string | titre de la vidéo (nom accessible) |

Façade sans aucune requête vers YouTube avant le clic, mention
« La vidéo est hébergée par YouTube (youtube-nocookie.com) » et lien
« Ouvrir sur YouTube ». Classes : `.video`, `__frame`, `__facade`, `__play`,
`__label`, `__notice`.

#### Prose (Markdown) : `.prose`

Style `p`, `ul`, `ol`, `li`, `strong`, `em`, `code`, `pre`, `a`, `h3`, `h4`
(largeur max 72 caractères). Toujours avec `md()` :

```php
<div class="prose"><?= md($texte) ?></div>
```

### Timeline : `partial('site/timeline')`

| Variable | Type | Description |
|---|---|---|
| `entries` | list | `TimelineEntry::published()` : `type`, `title`, `organization`, `location`, `start_date`, `end_date`, `date_precision`, `description` |
| `headingLevel` | int | niveau des titres (3) |
| `legend` | bool | légende des types (défaut `true`) |
| `reveal` | bool | apparition au défilement (défaut `true`) |

Chaque type a une couleur, une forme (rond, carré, losange) **et** une
étiquette texte (« Études », « Stage », « Autre »). Classes :
`.timeline`, `.timeline__item--education|internship|other`, `__dot`, `__head`,
`__type`, `__date`, `__title`, `__org`, `__desc`, `.legend`, `.legend__item`,
`.legend__swatch(--internship|--other)`.

### Compétences : `partial('site/skill-category')` et `partial('site/skill-meter')`

`site/skill-category` : `category` (ligne de `SkillCategory::withSkills()` :
`name`, `icon`, `skills[]` avec `name`, `level`, `description`),
`headingLevel` (3), `reveal` (true).

`site/skill-meter` : `level` (1 à 5). Produit 5 segments (`.meter.level-N`,
décoratifs) et le texte accessible « Niveau 4 sur 5 : Avancé » (`level_label()`).

```php
<div class="grid grid--3">
<?php foreach ($categories as $category): ?>
    <?= partial('site/skill-category', ['category' => $category, 'headingLevel' => 2]) ?>
<?php endforeach; ?>
</div>
```

Classes : `.skill-card`, `__head`, `__icon`, `__title`, `.skill-list`,
`.skill`, `.skill__head`, `__name`, `__level`, `__desc`, `.meter`, `.meter__seg`.

### Centres d'intérêt et « en bref »

`partial('site/interest-card', ['interest' => $row, 'headingLevel' => 3])` :
`label`, `description`, `icon` (nom d'icône ; `star` si inconnu).

`partial('site/facts', ['items' => [['label' => 'ville', 'value' => 'Avignon (84)'], …], 'title' => '-- en bref'])` :
liste façon table Lua `clé = valeur` (`.facts`, `__title`, `__list`, `__row`).

### Cartes génériques

`.card` (panneau bordé arrondi), `.card--pad` (marges intérieures),
`.card__title` (titre mono).

---

## 6. Site public : formulaires

### Champ complet : `partial('site/field')` (site ET administration)

| Variable | Type | Description |
|---|---|---|
| `name` | string | nom du champ (obligatoire) |
| `label` | string | libellé visible (obligatoire) |
| `type` | string | `text` (défaut), `email`, `url`, `password`, `number`, `date`, `tel`, `search`, `textarea`, `select`, `checkbox`, `file` |
| `value` | string | valeur (`old($old, 'name')`) ; checkbox : `'1'` = cochée |
| `error` | string | message d'erreur (`$errors['name'] ?? ''`) → `aria-invalid` + `aria-describedby` |
| `hint` | string | aide (reliée par `aria-describedby`) |
| `id` | string | id (défaut `f-{name}`, crochets retirés : `images[]` → `f-images`) |
| `required` | bool | `required` + astérisque |
| `optional` | bool | « (facultatif) » après le libellé |
| `maxlength` | int | attribut `maxlength` |
| `counter` | bool | compteur de caractères (`data-maxlength`, nécessite `maxlength`) |
| `options` | array | (select) `['valeur' => 'Libellé']` |
| `placeholder` | string | exemple (jamais à la place du libellé) |
| `rows` | int | (textarea) lignes, défaut 8 |
| `class` | string | classes en plus : `textarea--lg`, `input--mono`… |
| `attrs` | array | attributs en plus : `['autocomplete' => 'email', 'data-slug-source' => '#f-title', 'multiple' => true]` |
| `after` | string | HTML déjà sûr ajouté après le champ |

Ids produits : champ `f-{name}`, aide `f-{name}-aide`, erreur `f-{name}-erreur`.

### Formulaire de contact (exemple complet)

```php
<form class="form" method="post" action="/contact" novalidate data-focus-error>
    <?= csrf_field() ?>
<?php if ($errors !== []): ?>
    <?= partial('site/alert', ['type' => 'error', 'title' => 'Le message n\'a pas pu être envoyé', 'message' => 'Corrigez les champs signalés ci-dessous.']) ?>
<?php endif; ?>
    <div class="form-row form-row--2">
        <?= partial('site/field', ['name' => 'name', 'label' => 'Nom', 'required' => true, 'maxlength' => 100,
            'value' => old($old, 'name'), 'error' => $errors['name'] ?? '', 'attrs' => ['autocomplete' => 'name']]) ?>
        <?= partial('site/field', ['name' => 'email', 'type' => 'email', 'label' => 'Adresse e-mail', 'required' => true,
            'value' => old($old, 'email'), 'error' => $errors['email'] ?? '', 'attrs' => ['autocomplete' => 'email']]) ?>
    </div>
    <?= partial('site/field', ['name' => 'subject', 'type' => 'select', 'label' => 'Sujet',
        'options' => ['stage' => 'Proposition de stage', 'question' => 'Question', 'autre' => 'Autre'],
        'value' => old($old, 'subject', 'stage')]) ?>
    <?= partial('site/field', ['name' => 'message', 'type' => 'textarea', 'label' => 'Message', 'required' => true,
        'maxlength' => 5000, 'counter' => true, 'hint' => 'Entre 10 et 5000 caractères.',
        'value' => old($old, 'message'), 'error' => $errors['message'] ?? '']) ?>
    <?= partial('site/field', ['name' => 'consent', 'type' => 'checkbox', 'required' => true,
        'label' => 'J\'accepte que mes données soient utilisées pour me répondre.',
        'value' => old($old, 'consent'), 'error' => $errors['consent'] ?? '']) ?>
    <?= partial('site/honeypot', ['name' => 'site_web']) ?>
    <div class="form__actions">
        <button class="btn btn--primary" type="submit"><?= icon('mail') ?>Envoyer</button>
        <p class="form__note">Réponse sous 48 h.</p>
    </div>
</form>
```

### Champ piège anti-spam : `partial('site/honeypot')`

`name` (défaut `'site_web'`). Produit un champ dans `.hp` (hors écran,
`aria-hidden="true"`, `tabindex="-1"`, `autocomplete="off"`). Le contrôleur
rejette l'envoi si ce champ n'est pas vide.

### Classes de formulaire (site)

| Classe | Rôle |
|---|---|
| `.form` | grille verticale (gap 20 px) |
| `.form-row` / `.form-row--2` | champs côte à côte dès 640 px |
| `.field` | groupe libellé / aide / champ / erreur |
| `.field__label`, `.field__req`, `.field__opt` | libellé, astérisque, « (facultatif) » |
| `.field__hint`, `.field__error` | aide, erreur (rouge, avec icône) |
| `.input`, `.textarea`, `.select` | champs (16 px : pas de zoom iOS) ; `aria-invalid="true"` → bordure rouge |
| `.check`, `.check__input`, `.check__label` | case à cocher |
| `.counter` (`.is-near`, `.is-over`) | compteur (créé par le JS) |
| `.form__actions`, `.form__note` | zone des boutons, note |
| `.hp` | champ piège |

---

## 7. Site public : comportements JavaScript (`site.js`)

Chargé en `defer` par le gabarit. Tout est de l'amélioration progressive.

| Attribut | Effet |
|---|---|
| `data-menu-toggle` + `aria-controls="menu-mobile"` | bouton du menu mobile (dans `site/header`, rien à faire) : sans JS il reste masqué et la rangée d'onglets défilante suffit ; Échap ferme et rend le focus |
| `data-typewriter` | texte qui s'écrit (élément contenant **uniquement du texte**). Le texte complet reste lu par les lecteurs d'écran (copie `.sr-only`, partie animée `aria-hidden`), la mise en page ne bouge pas. Option `data-typewriter-delay="400"` (ms). Désactivé si `prefers-reduced-motion` |
| `data-reveal` | apparition au défilement (IntersectionObserver). Seuls les éléments **sous l'écran au chargement** sont masqués puis révélés : rien n'est caché sans JS, aucun clignotement. Désactivé si mouvement réduit |
| `data-filter-group` + `data-filter-target="id"` | groupe de filtres (partial `site/project-filters`) |
| `data-filter="slug"` | puce (lien) ; `""` = toutes |
| `data-filter-list` (id = cible) | conteneur des cartes (partial `site/project-grid`) |
| `data-categories="slug1 slug2"` | sur chaque carte |
| `data-filter-empty` | bloc affiché quand aucune carte ne correspond |
| `data-filter-status` | région `aria-live` : « N projets affichés » |
| `data-lightbox="groupe"` (sur un `<a href="image">`) | visionneuse `<dialog>` : ←/→, Échap, bouton Fermer, focus rendu au lien. Option `data-caption="…"` (sinon l'`alt` de l'image du lien) |
| `data-youtube="ID"` + `data-title="…"` (sur un `<button>`) | façade YouTube : au clic seulement, insère l'iframe `youtube-nocookie.com/embed/ID?autoplay=1` |
| `data-maxlength="2000"` (sur un champ) | compteur « 120 / 2000 caractères » relié par `aria-describedby`, annonce l'approche et le dépassement de la limite |
| `data-focus-error` (sur un `<form>`) | au chargement, focus sur le premier champ `aria-invalid="true"` |

<a id="filtres-de-projets"></a>
**Filtres de projets** : au clic sur une puce, les cartes sont filtrées sans
rechargement, `aria-current="page"` passe sur la puce, l'URL devient
`?categorie=slug` (`history.replaceState`, le paramètre `page` est retiré),
la région `aria-live` annonce le nombre de projets. Ctrl/Cmd+clic ouvre le
lien normalement.

---

## 8. Administration : composants

Les partials `site/alert`, `site/empty-state`, `site/pagination` et
`site/field` fonctionnent aussi dans l'administration (même classes dans
`admin.css`).

### En-tête de page : `partial('admin/page-header')`

| Variable | Type | Description |
|---|---|---|
| `title` | string | `h1` (obligatoire) |
| `comment` | string | ex. `'-- projets/'` |
| `lead` | string | phrase d'explication |
| `back` | array | `['href' => '/admin/projets', 'label' => 'Projets']` (lien retour) |
| `actions` | string | HTML déjà sûr (boutons) |

```php
<?= partial('admin/page-header', [
    'comment' => '-- projets/',
    'title'   => 'Projets',
    'lead'    => 'Jeux et projets techniques, brouillons compris.',
    'actions' => '<a class="btn btn--primary" href="/admin/projets/nouveau">' . icon('edit', 16) . 'Nouveau projet</a>',
]) ?>
```

Titre de section dans une page : `<h2 class="section-title">…</h2>`.

### Boutons (admin)

| Classe | Rendu |
|---|---|
| `.btn` / `.btn--secondary` | bordure (44 px) |
| `.btn--primary` | fond accent |
| `.btn--danger` | rouge (bordure, plein au survol) |
| `.btn--ghost` | sans bordure |
| `.btn--code` | mono, fond accent doux |
| `.btn--sm` | 36 px |
| `.btn--icon` | icône seule, carré : **`aria-label` obligatoire** |
| `.btn--block` | pleine largeur |
| `.btn-row` | rangée de boutons |

```php
<a class="btn btn--secondary btn--sm btn--icon" href="/admin/tags/<?= (int) $tag['id'] ?>/modifier" aria-label="Modifier <?= e($tag['name']) ?>"><?= icon('edit', 16) ?></a>
```

### Action POST en ligne : `partial('admin/post-button')`

| Variable | Type | Description |
|---|---|---|
| `action` | string | URL (obligatoire) |
| `label` | string | texte (obligatoire ; devient `aria-label` si `iconOnly`) |
| `variant` | string | `secondary` (défaut), `primary`, `danger`, `ghost`, `code` |
| `size` | string | `sm` (défaut) ou `md` |
| `icon` | string | nom d'icône |
| `iconOnly` | bool | bouton icône seule |
| `confirm` | string | message de confirmation (`data-confirm`) |
| `fields` | array | champs cachés en plus |
| `pressed` | ?bool | état d'une bascule (`aria-pressed`) |

```php
<?= partial('admin/post-button', [
    'action'   => '/admin/tags/' . (int) $tag['id'] . '/supprimer',
    'label'    => 'Supprimer ' . $tag['name'],
    'icon'     => 'trash', 'iconOnly' => true, 'variant' => 'danger',
    'confirm'  => 'Supprimer définitivement ce tag ?',
]) ?>
<?= partial('admin/post-button', ['action' => '/admin/projets/' . $id . '/publier', 'label' => $p['is_published'] ? 'Dépublier' : 'Publier', 'icon' => 'eye']) ?>
```

Routes créées par `$router->resource('/admin/tags', …)` : `GET /admin/tags` (liste),
`GET /admin/tags/nouveau`, `POST /admin/tags` (création), `GET /admin/tags/{id}/modifier`,
`POST /admin/tags/{id}` (mise à jour), `POST /admin/tags/{id}/supprimer`.
Classe : `.inline-form` (formulaire `display: inline-flex`).

### Tableaux de données

Toujours dans `.table-wrap` (défilement horizontal sur mobile ; région
focalisable et nommée pour le clavier).

```php
<div class="table-wrap" tabindex="0" role="region" aria-label="Liste des projets">
    <table class="table">
        <thead>
            <tr>
                <th scope="col">Titre</th>
                <th scope="col">Statut</th>
                <th scope="col" class="table__num">Images</th>
                <th scope="col" class="table__actions"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($projects as $p): ?>
            <tr>
                <td><a class="table__title" href="/admin/projets/<?= (int) $p['id'] ?>/modifier"><?= e($p['title']) ?></a>
                    <span class="table__sub"><?= e($p['slug']) ?></span></td>
                <td><span class="badge badge--<?= $p['is_published'] ? 'published' : 'draft' ?>"><?= $p['is_published'] ? 'Publié' : 'Brouillon' ?></span></td>
                <td class="table__num"><?= (int) $p['image_count'] ?></td>
                <td class="table__actions"><div class="btn-row">…boutons…</div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

Classes : `.table` (min 640 px, défile), `.table--compact` (sans largeur min),
`.table__title`, `.table__sub`, `.table__num`, `.table__actions`,
`.table__thumb` (miniature 64x40 : `<img>` ou `<span class="thumb-placeholder">`),
ligne `tr.is-unread` (message non lu, en gras).

### Badges (admin)

`.badge--published` (Publié), `.badge--draft` (Brouillon, pointillés),
`.badge--featured` (En avant), `.badge--unread` (Non lu), `.badge--done`,
`.badge--progress`, `.badge--success`, `.badge--warning`, `.badge--error`,
`.badge--danger`, `.badge--accent`, `.badge--plain`. Tags : `.tags` > `.tag`.

### Tuiles de statistiques : `<ul class="stats">` + `partial('admin/stat')`

| Variable | Type | Description |
|---|---|---|
| `label` | string | libellé (obligatoire) |
| `value` | int\|string | valeur (obligatoire) |
| `icon` | string | nom d'icône |
| `href` | string | lien : toute la tuile devient cliquable |
| `hint` | string | précision sous la valeur |

```php
<ul class="stats">
    <?= partial('admin/stat', ['label' => 'Projets publiés', 'value' => $counts['published'], 'icon' => 'folder', 'href' => '/admin/projets', 'hint' => $counts['drafts'] . ' brouillon(s)']) ?>
    <?= partial('admin/stat', ['label' => 'Messages non lus', 'value' => $unread, 'icon' => 'mail', 'href' => '/admin/messages']) ?>
</ul>
```

### Cartes

```php
<section class="card" aria-labelledby="titre-derniers">
    <div class="card__head">
        <h2 class="card__title" id="titre-derniers">Derniers messages</h2>
        <a class="btn btn--ghost btn--sm" href="/admin/messages">Tout voir</a>
    </div>
    <div class="card__body">…</div>
    <div class="card__foot">…</div>
</section>
```

`.card--pad` pour une carte simple. Grilles : `.grid.grid--2|3|4`.
Listes de définitions : `<dl class="dl"><div class="dl__row"><dt>…</dt><dd>…</dd></div></dl>`.
Corps d'un message (texte brut, retours à la ligne conservés) :
`<div class="message-body"><?= e($message['body']) ?></div>`. Markdown rendu :
`.prose`.

### Vignettes d'images : `<ul class="thumb-grid">` + `partial('admin/image-card')`

| Variable | Type | Description |
|---|---|---|
| `image` | array | ligne `ProjectImage` : `id`, `thumb_filename`, `alt_text`, `width`, `height` |
| `isCover` | bool | image de couverture (badge, bouton « Couverture » masqué) |
| `isFirst` / `isLast` | bool | masque « monter » / « descendre » |
| `number` | int | numéro affiché |
| `error` | string | erreur du texte alternatif |

Chaque vignette contient : formulaire du texte alternatif
(POST `/admin/images/{id}`, champ `alt_text`, 200 caractères max), et des
formulaires POST `/admin/images/{id}/monter|descendre|couverture|supprimer`
(confirmation avant suppression).

```php
<ul class="thumb-grid">
<?php $n = count($images); foreach ($images as $i => $img): ?>
    <?= partial('admin/image-card', ['image' => $img, 'number' => $i + 1,
        'isCover' => $i === 0, 'isFirst' => $i === 0, 'isLast' => $i === $n - 1]) ?>
<?php endforeach; ?>
</ul>
```

Classes : `.thumb` (`.is-cover`), `__media`, `__badge`, `__body`, `__meta`, `__actions`.

### Aide Markdown : `partial('admin/markdown-help')`

`<details>` repliable (tableau syntaxe → résultat). Variable `id` (défaut
`'aide-markdown'`). À passer en `after` d'un `site/field` de type textarea.

---

## 9. Administration : formulaires

Mêmes classes que le site (`.form`, `.field`, `.input`, `.textarea`,
`.select`, `.check`…) plus :

| Classe | Rôle |
|---|---|
| `.form-section` | groupe de champs (séparateur entre deux sections) |
| `.form-row--2` / `.form-row--3` | 2 ou 3 champs côte à côte dès 640 px, en colonne sur mobile |
| `.input--mono`, `.textarea--mono` | police mono (slug, code) |
| `.textarea--lg` | grande zone (320 px) |
| `.check-group` (`--cols`) | liste de cases (en colonnes) |
| `.switch`, `.switch__input`, `.switch__label` | interrupteur |
| `.form__actions--sticky` | barre d'actions collée en bas (longs formulaires) |
| `.file-preview` | aperçu des fichiers (créé par le JS) |

### Groupe de cases : `partial('admin/checkbox-group')`

| Variable | Type | Description |
|---|---|---|
| `name` | string | nom **sans** `[]`, ex. `'category_ids'` |
| `legend` | string | titre du groupe |
| `options` | list | `[['value' => 3, 'label' => 'Sci-fi', 'section' => 'game'], …]` |
| `checked` | list | valeurs cochées |
| `error`, `hint` | string | erreur / aide |
| `id` | string | id du fieldset (défaut `f-{name}`) ; cases `f-{name}-{index}` |
| `columns` | bool | en colonnes (défaut `true`) |
| `sectionSelect` | string | sélecteur du `<select>` de section, ex. `'#f-section'` (filtrage JS) |
| `empty` | string | texte si aucune option |

### Interrupteur : `partial('admin/switch')`

| Variable | Type | Description |
|---|---|---|
| `name` | string | nom (obligatoire) |
| `label` | string | libellé (obligatoire) |
| `checked` | bool | état initial |
| `hint` | string | aide |
| `id` | string | id (défaut `f-{name}`) |
| `value` | string | valeur si activé (défaut `'1'`) |

Un champ caché envoie `0` quand l'interrupteur est éteint.

### Exemple : formulaire de projet

```php
<form class="form card card--pad" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" data-focus-error>
    <?= csrf_field() ?>
    <div class="form-section">
        <div class="form-row form-row--2">
            <?= partial('site/field', ['name' => 'title', 'label' => 'Titre', 'required' => true, 'maxlength' => 120,
                'counter' => true, 'value' => old($old, 'title'), 'error' => $errors['title'] ?? '']) ?>
            <?= partial('site/field', ['name' => 'slug', 'label' => 'Adresse (slug)', 'class' => 'input--mono',
                'hint' => 'Lettres minuscules, chiffres et tirets. Rempli depuis le titre.',
                'value' => old($old, 'slug'), 'error' => $errors['slug'] ?? '',
                'attrs' => ['data-slug-source' => '#f-title', 'autocapitalize' => 'none', 'spellcheck' => 'false']]) ?>
        </div>
        <div class="form-row form-row--3">
            <?= partial('site/field', ['name' => 'section', 'type' => 'select', 'label' => 'Section',
                'options' => ['game' => 'Jeux', 'tech' => 'Projets techniques'], 'value' => old($old, 'section', 'game')]) ?>
            <?= partial('site/field', ['name' => 'status', 'type' => 'select', 'label' => 'Statut',
                'options' => ['in_progress' => 'En cours', 'done' => 'Terminé'], 'value' => old($old, 'status')]) ?>
            <?= partial('site/field', ['name' => 'project_date', 'type' => 'date', 'label' => 'Date', 'optional' => true,
                'value' => old($old, 'project_date')]) ?>
        </div>
        <?= partial('admin/checkbox-group', ['name' => 'category_ids', 'legend' => 'Catégories',
            'sectionSelect' => '#f-section', 'checked' => $categoryIds,
            'options' => array_map(fn ($c) => ['value' => $c['id'], 'label' => $c['name'], 'section' => $c['section']], $categories)]) ?>
    </div>
    <div class="form-section">
        <?= partial('site/field', ['name' => 'body', 'type' => 'textarea', 'label' => 'Description', 'class' => 'textarea--lg',
            'value' => old($old, 'body'), 'after' => partial('admin/markdown-help')]) ?>
        <?= partial('site/field', ['name' => 'images', 'type' => 'file', 'label' => 'Ajouter des images', 'optional' => true,
            'hint' => 'JPEG, PNG ou WebP, 8 Mo maximum par fichier.',
            'attrs' => ['multiple' => true, 'accept' => 'image/jpeg,image/png,image/webp', 'data-file-preview' => true, 'data-max-size' => '8388608']]) ?>
        <?= partial('admin/switch', ['name' => 'is_published', 'label' => 'Publié', 'checked' => old($old, 'is_published') === '1', 'hint' => 'Visible sur le site public.']) ?>
    </div>
    <div class="form__actions">
        <button class="btn btn--primary" type="submit"><?= icon('check', 16) ?>Enregistrer</button>
        <a class="btn btn--secondary" href="/admin/projets">Annuler</a>
    </div>
</form>
```

Avec `multiple`, nommez le champ `images[]` si le contrôleur attend une liste :
l'id reste `f-images` (les crochets sont retirés de l'id). `Request::files('images')`
renvoie la liste normalisée.

---

## 10. Administration : comportements JavaScript (`admin.js`)

| Attribut | Effet |
|---|---|
| `data-confirm="Message"` (sur un `<form>` ou un bouton submit) | `confirm()` natif avant l'envoi ; refus = envoi annulé |
| `data-slug-source="#f-title"` (sur le champ slug) | slug rempli depuis le titre (accents retirés, tirets) tant que l'utilisateur n'a pas modifié le slug ; un slug déjà rempli au chargement n'est jamais écrasé |
| `data-section-filter="#f-section"` (sur le fieldset) + `data-section="game"` (sur chaque case) | seules les cases de la section choisie sont visibles ; les autres sont décochées (fourni par `admin/checkbox-group` avec `sectionSelect`) |
| `data-maxlength="160"` | compteur de caractères (comme sur le site) |
| `data-file-preview` (sur `input[type=file]`) | liste nom + taille (+ miniature) des fichiers choisis ; option `data-max-size="octets"` signale « trop volumineux » |
| `data-focus-error` (sur un `<form>`) | focus sur le premier champ invalide au chargement |
| `data-admin-menu-toggle` / `data-admin-menu-link` | menu mobile (dans le gabarit, rien à faire) : sans JS, le lien « Menu » mène à `#admin-nav` (affiché par `:target`) |

---

## 11. Pages d'authentification et d'erreur

Contenu d'une vue rendue avec `layouts/admin-auth` (déjà dans la carte) :

```php
<p class="auth-card__comment" aria-hidden="true">-- connexion.lua</p>
<h1 class="auth-card__title">Connexion</h1>
<p class="auth-card__lead">Espace réservé à l'administrateur du site.</p>
<form class="form" method="post" action="/admin/connexion" data-focus-error>
    <?= csrf_field() ?>
    <?= partial('site/field', ['name' => 'username', 'label' => 'Identifiant', 'required' => true,
        'value' => old($old, 'username'), 'attrs' => ['autocomplete' => 'username', 'autocapitalize' => 'none', 'spellcheck' => 'false']]) ?>
    <?= partial('site/field', ['name' => 'password', 'type' => 'password', 'label' => 'Mot de passe', 'required' => true,
        'error' => $error ?? '', 'attrs' => ['autocomplete' => 'current-password']]) ?>
    <button class="btn btn--primary btn--block" type="submit"><?= icon('lock', 16) ?>Se connecter</button>
</form>
```

Code TOTP : `'attrs' => ['autocomplete' => 'one-time-code', 'inputmode' => 'numeric', 'pattern' => '[0-9]{6}']`
avec `'class' => 'input--mono'`. Codes de secours : `<ul class="codes"><li>…</li></ul>`.
QR code : `<div class="qr"><?= $svg ?></div>` (fond blanc).
Classes : `.auth-card__comment`, `.auth-card__title`, `.auth-card__lead`,
`.auth-foot`, `.error-detail`, `.codes`, `.qr`.

### Page d'erreur : `site/errors/error`

Rendue par `ErrorHandler` (rien à faire). Variables : `$status`, `$message`
(message sûr d'une `HttpException`, sinon texte par défaut selon le code),
`$detail` (trace, en développement seulement), `$meta['title']`. Sur le site :
gros code, titre, fenêtre de code, boutons (accueil, projets, contact). Sous
`/admin` : carte du gabarit `admin-auth`. Classes : `.error-page`, `__code`,
`__status`, `__title`, `__text`, `__actions`, `__detail`, `.auth-error`.

---

## 12. Images et icônes

### Icônes : `icon($nom, $taille = 18, $classe = 'icon')`

SVG en ligne décoratif (`aria-hidden`). Noms disponibles : `download`,
`arrow`, `arrow-left`, `info`, `menu`, `close`, `file`, `external`, `play`,
`mail`, `linkedin`, `github`, `network`, `server`, `code`, `shield`, `tools`,
`chip`, `terminal`, `bike`, `gamepad`, `book`, `music`, `calendar`, `check`,
`lock`, `trash`, `edit`, `eye`, `star`, `up`, `down`, `logout`, `image`,
`folder`, `tag`, `settings`, `user`, `home`. Un nom inconnu renvoie `''`.
Ces noms sont aussi les valeurs possibles des colonnes `icon` (catégories de
compétences, centres d'intérêt) : proposez-les dans un `<select>`.

### Fichiers fixes (`public_html/assets/img/`)

| Fichier | Usage |
|---|---|
| `favicon.svg` | icône d'onglet (« >_ » dans un carré violet) |
| `favicon-32.png` | repli PNG 32x32 |
| `apple-touch-icon.png` | écran d'accueil iOS (180x180) |
| `og-default.png` | aperçu LinkedIn par défaut (1200x630) |

Les PNG sont générés par `php app/tools/generate-images.php` (CLI, GD). Le SVG
est écrit à la main ; si vous modifiez le dessin, mettez les deux à jour.

### Images de projet

`<img src="<?= e(upload_url($img['thumb_filename'])) ?>" alt="<?= e($img['alt_text']) ?>" width="<?= (int) $img['width'] ?>" height="<?= (int) $img['height'] ?>" loading="lazy" decoding="async">`
Toujours `width`/`height` (pas de décalage de mise en page) et un `alt`.

---

## 13. Pièges connus

- **Ids des cartes projet** : `site/project-card` donne l'id `projet-{slug}` au
  titre. N'affichez pas deux fois la même carte sur une page.
- **Filtres** : `site/project-grid` doit recevoir la liste complète de la
  section, sinon le filtrage JS ne peut pas réafficher les autres cartes.
- **`data-typewriter`** : uniquement sur un élément sans enfant HTML (texte seul).
- **Textes `.sr-only` dans un conteneur qui défile** : le conteneur doit être
  `position: relative` (déjà le cas pour `.table-wrap` et `.filters`), sinon
  la page entière déborde horizontalement sur mobile.
- **Images en aperçu** : la CSP autorise `data:` mais pas `blob:` pour les
  images (le JS utilise `FileReader`).
- **YouTube** : la CSP n'autorise que `frame-src https://www.youtube-nocookie.com` :
  n'insérez jamais d'iframe `youtube.com`.
- **Tableaux sur mobile** : toujours `.table-wrap` + `tabindex="0"` +
  `role="region"` + `aria-label`.
- **Bouton icône seule** : sans `aria-label`, le bouton est muet pour un
  lecteur d'écran.
- **Le menu mobile du site** n'apparaît qu'avec le JS ; sans JS, la rangée
  d'onglets défilante reste la navigation.

---

## Ajouts pages publiques

Composants ajoutés pour les pages publiques (`app/Controllers/Site`,
`app/Views/site`). Styles : fin de `site.css`, section
`/* === Ajouts pages publiques === */`. Aucun JavaScript supplémentaire.

### Vues et contrôleurs du site

| Route | Contrôleur | Vue |
|---|---|---|
| `/` | `HomeController::index` | `site/home` |
| `/projets` (301), `/projets/jeux`, `/projets/techniques` | `ProjectController::index`, `games`, `tech` | `site/projects/index` |
| `/projet/{slug}` | `ProjectController::show` | `site/projects/show` |
| `/parcours` | `TimelineController::index` | `site/timeline` |
| `/competences` | `SkillController::index` | `site/skills` |
| `/a-propos` | `AboutController::index` | `site/about` |
| `/contact` (GET, POST) | `ContactController::show`, `send` | `site/contact` |
| `/cv` | `CvController::download` | (fichier PDF) |
| `/mentions-legales` | `LegalController::index` | `site/legal` |
| `/sitemap.xml`, `/robots.txt` | `SitemapController::index`, `robots` | (texte) |

Tous héritent de `App\Controllers\Site\SiteController` (lui-même
`App\Core\Controller`), qui fournit : `cvAvailable()`, `internshipWanted()`,
`internshipPeriod()` (« 18 mai au 26 juin 2026 »), `internshipSentence()`
(« du 18 mai au 26 juin 2026 »), `facts()` (bloc « en bref ») et
`safeUrl()` (URL http/https uniquement).

### Corps de page : `.page-body`

Marge sous `.page-head` quand le contenu suit directement l'en-tête
(32 px, 44 px dès 900 px).

### Lien de fin de section : `.section-more`

```php
<p class="section-more"><a class="btn btn--secondary" href="/projets/jeux">Tous les projets<?= icon('arrow') ?></a></p>
```

### Barre d'outils des projets : `.projects-toolbar` + `.section-switch`

Rangée (qui passe à la ligne) contenant le sélecteur de section et les puces
de filtre (`site/project-filters`, dont la marge basse est alors annulée).
Le sélecteur est une `<nav>` d'onglets segmentés ; la section courante porte
`aria-current="page"` (fond accent).

```php
<div class="projects-toolbar">
    <nav class="section-switch" aria-label="Sections de projets">
        <ul class="section-switch__list">
            <li><a class="section-switch__link" href="/projets/jeux" aria-current="page"><span class="sr-only">Projets de jeux</span><span aria-hidden="true">jeux/</span></a></li>
            <li><a class="section-switch__link" href="/projets/techniques"><span class="sr-only">Projets techniques</span><span aria-hidden="true">techniques/</span></a></li>
        </ul>
    </nav>
    <?= partial('site/project-filters', [...]) ?>
</div>
```

### Appel à l'action : `.cta`

Carte en bas de page (lien vers l'autre section, contact, CV). Toujours avec
`.card`. Classes : `.cta__body`, `.cta__comment` (décoratif, `aria-hidden`),
`.cta__title` (h2), `.cta__text`, `.cta__actions` (peut porter `.btn-row`).

```php
<aside class="card cta" aria-labelledby="titre-contact">
    <div class="cta__body">
        <p class="cta__comment" aria-hidden="true">-- contact.sh</p>
        <h2 class="cta__title" id="titre-contact">Un stage à me proposer ?</h2>
        <p class="cta__text">…</p>
    </div>
    <div class="cta__actions btn-row">
        <a class="btn btn--primary" href="/contact"><?= icon('mail') ?>Me contacter</a>
    </div>
</aside>
```

### Échelle des niveaux : `.level-legend`

Carte (`.card.card--pad.level-legend`) avec un titre `.level-legend__title`
et une liste `<ol class="level-legend__list">` de `.level-legend__item` :
barre `.meter.level-N` décorative (`aria-hidden`) + `.level-legend__label`
contenant `.level-legend__num` (chiffre orange) et `level_label(N)`.

### Page détail d'un projet

| Classe | Rôle |
|---|---|
| `.project-head__path` | chemin de fichier `-- roblox/…/tardis.lua` (avec `.comment`, `aria-hidden`) entre le fil d'Ariane et le h1 |
| `.inline-list` | liste en ligne séparée par des virgules décoratives (catégories dans l'aside) |
| `.detail-aside__actions` | boutons empilés en bas de l'aside (lien externe, retour à la liste) |

### Divers

| Classe | Rôle |
|---|---|
| `.availability` | point vert « ● » de disponibilité (à mettre en `aria-hidden`) |
| `.hero__code--full` / `.hero__code--compact` | sur `site/code-window` (paramètre `class`) : version complète dès 640 px, version courte sur téléphone (une seule affichée) |

### Mentions légales : `.legal`

Grille sommaire + contenu (sommaire collant à gauche dès 1024 px).
Classes : `.legal__toc` (avec `.card.card--pad`), `.legal__toc-title`,
`.legal__toc-list` (liens d'ancre), `.legal__content`, `.legal__section`
(chaque section a un `id` : `editeur`, `hebergement`, `propriete`,
**`donnees`** (cible du lien RGPD du formulaire), `cookies`, `securite`),
`.legal__address`, `.legal__updated`. Titres : `.content-block__title`,
texte : `.prose`.

### Correctifs généraux (section « Ajouts pages publiques »)

- `.filters__list { position: relative }` : les textes `.sr-only` des puces
  restent dans la liste qui défile (sinon débordement horizontal de 29 px sur
  mobile dès qu'une puce sort de l'écran, voir § 13).
- `.form-row > .field { align-content: start }` : dans une rangée de deux
  champs, l'aide ou l'erreur d'un champ n'étire plus la hauteur du champ
  voisin.

### Formulaire de contact : contrat

Champs envoyés à `POST /contact` : `_csrf`, `form_ts` (jeton horodaté signé
`horodatage.hmac`, généré à chaque affichage), `name` (≤ 100), `email`,
`subject` (facultatif, ≤ 150 ; « Message depuis le portfolio » par défaut),
`message` (10 à 5000), `consent` (`1`), `website` (champ piège, doit rester
vide). Erreur générale (jeton, limite d'envoi) : clé `_form` de `$errors`.
Codes : 303 (succès, réel ou simulé pour un robot), 419 (CSRF), 422
(validation ou jeton), 429 (3 messages / 10 min ou 10 / 24 h par IP).

---

## Ajouts administration (lot A)

Connexion et double authentification, tableau de bord, projets, images,
catégories, tags. Styles : fin de `admin.css`, section
`/* === Ajouts admin lot A === */` ; scripts : fin de `admin.js`, même titre.

### Vues et contrôleurs (lot A)

| Route | Contrôleur | Vue |
|---|---|---|
| `GET/POST /admin/connexion` | `AuthController::loginForm/login` | `admin/auth/login` (gabarit `admin-auth`) |
| `GET/POST /admin/connexion/verification` | `AuthController::twoFactorForm/twoFactor` | `admin/auth/two-factor` |
| `POST /admin/deconnexion` | `AuthController::logout` | (redirection) |
| `GET /admin` | `DashboardController::index` | `admin/dashboard/index` |
| `/admin/projets…` (ressource + `publier`, `vedette`) | `ProjectController` | `admin/projects/index`, `admin/projects/form` |
| `/admin/projets/{id}/images`, `/admin/images/{id}…` | `ImageController` | `admin/images/index` |
| `/admin/categories…` | `CategoryController` | `admin/categories/index`, `admin/categories/form` |
| `/admin/tags…` | `TagController` | `admin/tags/index`, `admin/tags/form` |

Contrats utiles :

- **Connexion** : champs `username`, `password` ; réponses 303 (`/admin` ou
  `/admin/connexion/verification`), 401 (« Identifiant ou mot de passe
  incorrect. », identique que le compte existe ou non, durée minimale de 1 s),
  429 (« Trop de tentatives. Réessayez dans 15 minutes. »), 419 (jeton CSRF).
- **Double authentification** : champ `code` + champ caché `mode`
  (`totp` ou `recovery`, pour afficher l'erreur sur le bon formulaire). Les
  espaces d'un code TOTP sont retirés (« 123 456 »). 401 si code faux ou rejoué,
  429 après 5 échecs, retour à la connexion si l'attente a expiré (5 min).
- **Projet** : champs `section`, `title`, `slug` (vide : depuis le titre ;
  unicité par `Project::uniqueSlug`, un message signale le suffixe ajouté),
  `summary`, `context`, `work_done`, `learnings` (10 000 caractères maximum :
  une colonne TEXT tient 65 535 octets), `youtube_id` (adresse ou identifiant),
  `external_url`, `external_label`, `status`, `project_date`, `position`,
  `is_published`, `is_featured`, `category_ids[]`, `tag_ids[]`, `new_tags`
  (« Lua, Docker » : un tag de même nom, sans tenir compte de la casse ni des
  accents, est réutilisé). Champ caché facultatif `section` sur les actions en
  ligne (`publier`, `vedette`, `supprimer`) : retour sur la liste filtrée.
- **Images** : `images[]` (10 fichiers maximum par envoi, sinon rien n'est
  enregistré), `alt_text` (texte alternatif par défaut, sinon le titre du
  projet). Succès partiel possible : un message par fichier refusé, nom
  d'origine nettoyé et échappé. Le champ caché `MAX_FILE_SIZE` n'est qu'une
  indication : la vérification réelle est celle d'`ImageProcessor`.

### 8 bis. Composants

| Classe | Rôle |
|---|---|
| `.filter-bar` (`nav`) | rangée de liens `.btn.btn--secondary.btn--sm` ; l'entrée active porte `aria-current="page"` (fond accent doux) |
| `.filter-bar__count` | nombre mono discret dans un lien du filtre |
| `.btn[aria-pressed="true"]` | bouton bascule enfoncé (ex. « En avant ») : bordure et texte accent. Avec `partial('admin/post-button', ['pressed' => true/false, …])` |
| `.btn-row--even` | rangée de boutons dont les boutons texte ont une largeur minimale commune (108 px) : alignement d'une ligne de tableau à l'autre |
| `.list-head` | titre de liste (`h2.section-title`) + bouton à droite, sur une ligne |
| `.table tbody th` | en-tête de ligne (`<th scope="row">`) : même graisse qu'une cellule (le lien `.table__title` reste en gras) |
| `a.table__link` | lien secondaire sous le titre d'une ligne (ex. adresse publique `/projet/slug`, nouvel onglet) |
| `.table--compact .table__sub` | dans un tableau compact, le sous-titre (slug) peut être coupé n'importe où : le tableau tient en 390 px |
| `.col-secondary` | colonne de tableau masquée sous 640 px (sur `th` ET `td`) pour garder les actions visibles ; à combiner avec `.table--compact` si le tableau doit tenir en 390 px |
| `.alert-list` | liste (`ul`) d'alertes statiques `partial('site/alert', [… 'role' => ''])` |
| `.item-list`, `__item`, `__main`, `__title`, `__meta` | liste d'éléments dans une `.card` (derniers messages, brouillons) ; `__item.is-unread` ajoute une pastille accent devant le titre |
| `.danger-zone` | carte de suppression sous un formulaire de modification (`.card.card--pad.stack.danger-zone`) |
| `.disclosure`, `.disclosure__body` | bloc repliable `<details>` (ex. « Utiliser un code de secours ») |
| `.auth-card__alt` | lien secondaire centré sous un formulaire d'authentification |
| `.input--code` | saisie d'un code à 6 chiffres (grande taille, espacement) ; avec `.input--mono` |

```php
<nav class="filter-bar" aria-label="Filtrer par section">
    <a class="btn btn--secondary btn--sm" href="/admin/projets" aria-current="page">Tous <span class="filter-bar__count">5</span></a>
    <a class="btn btn--secondary btn--sm" href="/admin/projets?section=game">Jeux <span class="filter-bar__count">4</span></a>
</nav>

<div class="list-head">
    <h2 class="section-title" id="titre-jeux">Jeux (Roblox)</h2>
    <a class="btn btn--ghost btn--sm" href="/admin/categories/nouveau?section=game">Ajouter</a>
</div>

<section class="card" aria-labelledby="titre-messages">
    <div class="card__head"><h2 class="card__title" id="titre-messages">Derniers messages</h2></div>
    <ul class="item-list">
        <li class="item-list__item is-unread">
            <div class="item-list__main">
                <a class="item-list__title" href="/admin/messages/3">Proposition de stage</a>
                <span class="item-list__meta">Claire Martin · 03/11/2026 à 12:20</span>
            </div>
            <span class="badge badge--unread">Non lu</span>
        </li>
    </ul>
</section>
```

Formulaires longs (projet) : chaque groupe de champs est une
`<section class="card card--pad stack">` avec un `h2.section-title`, le
`<form class="form">` les espace, et la barre `.form__actions--sticky` est
placée directement dans le formulaire (hors des cartes) pour couvrir toute la
largeur du contenu.

### 10 bis. Comportements JavaScript (`admin.js`, section lot A)

| Attribut | Effet |
|---|---|
| `data-max-files="10"` (sur `input[type=file]`) | au-delà du nombre de fichiers, l'envoi est bloqué (`setCustomValidity`), un message `.field__error` (id `{id}-nombre`, `aria-live`) est affiché et relié par `aria-describedby`. Le serveur refuse de toute façon |
| `data-busy-label="Envoi en cours…"` (sur un bouton submit) | une fois l'envoi réellement parti (pas annulé par `data-confirm` ni par la validation du navigateur), le bouton est désactivé (`disabled` + `aria-disabled`) et affiche ce texte : pas de double envoi. Rétabli si la page revient du cache (bouton Retour) |

---

## Ajouts administration (lot B)

Parcours, compétences, catégories de compétences, centres d'intérêt,
« À propos », messages, réglages et CV, compte (mot de passe, double
authentification). Styles : fin de `admin.css`, section
`/* === Ajouts admin lot B === */` ; scripts : fin de `admin.js`, même titre.

### 8 ter. Composants

#### Choix d'une icône : `partial('admin/icon-picker')`

Boutons radio dont chaque libellé montre l'icône réelle (`icon()`) : aucun
JavaScript, utilisable au clavier (flèches), focus visible sur la tuile.

| Variable | Type | Description |
|---|---|---|
| `name` | string | nom du champ (défaut `'icon'`) |
| `legend` | string | titre du groupe (`<legend>`) |
| `options` | array | `['network' => 'Réseau', …]` : nom d'icône => libellé |
| `value` | string | icône cochée (`old($old, 'icon')`) |
| `error`, `hint` | string | erreur / aide (reliées par `aria-describedby`) |
| `required` | bool | astérisque + `required` |
| `id` | string | id du fieldset (défaut `f-{name}`) ; radios `f-{name}-{icône}` |

```php
<?= partial('admin/icon-picker', ['name' => 'icon', 'legend' => 'Icône', 'required' => true,
    'options' => ['network' => 'Réseau', 'shield' => 'Sécurité'], 'value' => old($old, 'icon'),
    'error' => $errors['icon'] ?? '']) ?>
```

Classes : `.icon-picker`, `__list`, `__item`, `__input` (radio réel, invisible
mais focalisable), `__label`, `__preview`, `__name`, `__code`. Listes blanches
d'icônes : `SkillCategoryController::ICONS`, `InterestController::ICONS`.

#### Classes ajoutées

| Classe | Rôle |
|---|---|
| `.icon-box` | pastille carrée 36 px (fond accent doux) autour d'un `icon()` dans un tableau ou un titre |
| `.table__icon` | cellule de tableau étroite contenant une `.icon-box` |
| `.list-note` (`--top`) | petite note sous (ou au-dessus d') une liste, avec `.form__note` |
| `.meter`, `.meter__seg`, `.meter.level-N` | niveau en 5 segments (mêmes classes que le site, largeur 96 px) : utilisez `partial('site/skill-meter')` |
| `.level-cell`, `__label` | segments + texte « 4/5 · Avancé » (texte `aria-hidden`, le partial fournit le texte lu) |
| `.skill-group`, `__title` | bloc d'une catégorie (titre + tableau), à combiner avec `.list-head` (lot A) |
| `.empty-inline` | petit état vide en pointillés dans une section |
| `.period-preview`, `__label`, `__value` | aperçu de la période d'une entrée du parcours (voir `data-period-preview`) |
| `.edit-layout` | formulaire + aperçu côte à côte dès 1200 px |
| `.message-layout` | message + colonne d'informations (320 px) dès 1024 px |
| `.message-body--html` | à ajouter à `.message-body` quand le corps est passé par `nl2br(e(…))` : annule `pre-wrap` (sinon chaque retour à la ligne est doublé) |
| `.message-cell`, `__subject` | cellule « sujet + extrait » de la liste des messages |
| `.dl--stacked` | `.dl` toujours sur une colonne (colonnes étroites) |
| `.break` | coupe les longues chaînes (adresses e-mail) |
| `.page-toc` | sommaire d'ancres en haut d'une longue page (liens `.btn--ghost.btn--sm` dans un `ul.cluster`) |
| `.cv-card` | carte du CV (marge haute, ancre `#cv`) |
| `.icon--flip` | icône retournée (`download` utilisée comme « envoyer ») : `icon('download', 16, 'icon icon--flip')` |
| `.account-grid` | deux cartes côte à côte dès 1024 px |
| `.card--inset` | carte dans une carte (fond plus sombre, contenu aligné en haut) |
| `.card--danger` | bordure rouge discrète (action dangereuse) |
| `.subsection-title` | titre `h3` mono dans une carte |
| `.measure` | largeur de lecture limitée (72 caractères) |
| `.form--narrow` | formulaire court limité à 460 px |
| `.steps`, `__item`, `__title` | étapes numérotées automatiquement (pastille violette) |
| `.app-list` | liste d'applications (nom + plateformes en `.text-muted`) |
| `.qr-setup`, `__code` | QR code + explications côte à côte dès 760 px |
| `.secret-key` | clé TOTP en groupes de 4 (`<code>`), sélection d'un seul geste |
| `.codes-card` | carte des codes de secours (720 px max ; à l'impression, seuls les codes restent) |

Correctif général : `.form-row > .field { align-content: start; }`. Dans une
rangée de champs, le champ le plus court (sans aide ni compteur) ne s'étire
plus en hauteur.

#### Pages et contrôleurs

| Route | Contrôleur | Vues (`app/Views/admin/…`) |
|---|---|---|
| `/admin/parcours` (resource) | `TimelineController` | `timeline/index`, `timeline/form` |
| `/admin/competences/categories` (resource) | `SkillCategoryController` | `skill-categories/index`, `skill-categories/form` |
| `/admin/competences` (resource, `?categorie=ID` présélectionne la catégorie) | `SkillController` | `skills/index`, `skills/form` |
| `/admin/interets` (resource) | `InterestController` | `interests/index`, `interests/form` |
| `/admin/a-propos` | `AboutController` | `about/edit` |
| `/admin/messages`, `/{id}`, `/{id}/lu`, `/{id}/supprimer` | `MessageController` | `messages/index`, `messages/show` |
| `/admin/reglages`, `/cv`, `/cv/supprimer` | `SettingsController` | `settings/edit` |
| `/admin/compte`, `/mot-de-passe`, `/2fa`, `/2fa/desactiver`, `/2fa/codes` | `AccountController` | `account/index`, `account/two-factor`, `account/recovery-codes` |

Points à connaître :

- Les formulaires ont `novalidate` : la validation de référence est celle du
  serveur (messages en français, statut 422, saisie conservée).
- Messages : le bouton lu / non lu et la suppression envoient le champ caché
  `page` pour revenir à la même page de la liste. La liste lance la purge RGPD
  (`Message::purgeOlderThan(12)`) à chaque ouverture.
- Le lien « Répondre » est construit côté serveur (adresse revérifiée par
  `filter_var`, parties encodées par `rawurlencode`) : jamais de `mailto:`
  assemblé dans la vue.
- Réglages : le formulaire du CV a pour action `/admin/reglages/cv#cv` : en cas
  d'erreur (statut 422), le navigateur revient directement sur la carte du CV.
- Compte : chaque action sensible redemande le mot de passe actuel ; 5 échecs
  en 15 min (mot de passe ou code) bloquent ces actions (`Throttle`, action
  `reauth`). Les champs de ces formulaires ont des noms distincts
  (`current_password`, `codes_password`, `disable_password`, `disable_code`)
  car ils sont sur la même page.
- Les codes de secours sont rendus directement dans la réponse au POST
  (jamais en session ni après redirection).

### 10 ter. Comportements JavaScript (`admin.js`, section lot B)

| Attribut | Effet |
|---|---|
| `data-period-preview` + `data-start="#f-start_date"`, `data-end="#f-end_date"`, `data-precision="#f-date_precision"`, enfant `[data-period-output]` (un `<output aria-live="polite">`) | aperçu en direct de la période, même format que `period_fr()` (« 18 mai 2026 → en cours ») ; signale une fin avant le début. Le serveur rend la valeur initiale |
| `data-copy-target="#id"` (sur un `<button hidden>`) + `data-copy-done="Codes copiés"` | bouton affiché seulement si le presse-papiers est disponible ; copie les `<li>` de la cible, un par ligne, et l'annonce dans `[data-copy-status]` (région `aria-live`) |
| `data-print` (sur un `<button hidden>`) | bouton « Imprimer » affiché avec JS seulement |
| plusieurs `form[data-focus-error]` sur une page | si rien n'a le focus au chargement, il va au premier champ `aria-invalid="true"` de la page, quel que soit le formulaire (le comportement de base ne regarde que le premier formulaire) |
