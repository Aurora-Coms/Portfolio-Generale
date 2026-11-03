-- =============================================================================
--  Portfolio d'Alexandre Derbez — Base de données
-- =============================================================================
--  Compatible MySQL 8+ et MariaDB 10.4+ (Hostinger).
--
--  IMPORT (phpMyAdmin de Hostinger) :
--    1. Sélectionnez votre base dans la colonne de gauche (elle est créée
--       depuis hPanel > Bases de données > MySQL).
--    2. Onglet « Importer » > choisissez ce fichier > « Importer ».
--
--  ⚠ ATTENTION : ce script SUPPRIME puis recrée les tables. Ne le ré-importez
--    pas sur un site en production : vous perdriez vos projets et messages.
--
--  ⚠ Ce fichier ne doit PAS être envoyé dans public_html.
--
--  Aucun compte administrateur n'est créé ici : utilisez le script
--  public_html/setup-admin.php (voir README.md), puis supprimez-le.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS project_tags;
DROP TABLE IF EXISTS project_categories;
DROP TABLE IF EXISTS project_images;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS timeline_entries;
DROP TABLE IF EXISTS skills;
DROP TABLE IF EXISTS skill_categories;
DROP TABLE IF EXISTS skill_themes;
DROP TABLE IF EXISTS interests;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS throttle;
DROP TABLE IF EXISTS admin_recovery_codes;
DROP TABLE IF EXISTS admins;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
--  Administration
-- -----------------------------------------------------------------------------

-- Compte administrateur (un seul compte prévu, mais la table reste générique).
CREATE TABLE admins (
    id               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    username         VARCHAR(50)      NOT NULL,
    password_hash    VARCHAR(255)     NOT NULL COMMENT 'password_hash() : Argon2id ou bcrypt',
    totp_secret_enc  VARCHAR(255)     NULL     COMMENT 'Secret TOTP chiffré (AES-256-GCM avec app_key)',
    totp_enabled     TINYINT(1)       NOT NULL DEFAULT 0,
    totp_last_step   BIGINT UNSIGNED  NULL     COMMENT 'Dernier pas TOTP accepté (anti-rejeu)',
    session_version  INT UNSIGNED     NOT NULL DEFAULT 1 COMMENT 'Incrémentée pour déconnecter les autres sessions',
    last_login_at    DATETIME         NULL,
    created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Codes de secours de la double authentification (stockés hachés).
CREATE TABLE admin_recovery_codes (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    admin_id    INT UNSIGNED  NOT NULL,
    code_hash   VARCHAR(255)  NOT NULL,
    used_at     DATETIME      NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recovery_admin (admin_id),
    CONSTRAINT fk_recovery_admin FOREIGN KEY (admin_id) REFERENCES admins (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des tentatives (connexion, contact…) pour limiter le brute-force et
-- le spam. L'IP n'est jamais stockée en clair : seulement un HMAC-SHA256.
CREATE TABLE throttle (
    id          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    action      VARCHAR(30)      NOT NULL,
    key_hash    CHAR(64)         NOT NULL,
    created_at  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_throttle_lookup (action, key_hash, created_at),
    KEY idx_throttle_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Réglages clé/valeur (textes de l'accueil, liens, bandeau de stage…).
CREATE TABLE settings (
    setting_key    VARCHAR(64)  NOT NULL,
    setting_value  TEXT         NOT NULL,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  Projets
-- -----------------------------------------------------------------------------

-- Catégories de projets. « section » sépare les jeux des projets techniques.
CREATE TABLE categories (
    id           INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    section      ENUM('game','tech') NOT NULL,
    name         VARCHAR(80)        NOT NULL,
    slug         VARCHAR(90)        NOT NULL,
    description  VARCHAR(255)       NULL,
    position     SMALLINT UNSIGNED  NOT NULL DEFAULT 0,
    created_at   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_section_slug (section, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Technologies / tags (Lua, Cisco, Linux…), partagés entre toutes les sections.
CREATE TABLE tags (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name        VARCHAR(50)   NOT NULL,
    slug        VARCHAR(60)   NOT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tags_name (name),
    UNIQUE KEY uq_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Projets (jeux et projets techniques).
-- Les champs context / work_done / learnings acceptent un Markdown restreint
-- (gras, italique, listes, liens, code), toujours échappé avant affichage.
CREATE TABLE projects (
    id              INT UNSIGNED                 NOT NULL AUTO_INCREMENT,
    section         ENUM('game','tech')          NOT NULL,
    title           VARCHAR(120)                 NOT NULL,
    slug            VARCHAR(140)                 NOT NULL,
    summary         VARCHAR(300)                 NOT NULL COMMENT 'Résumé court affiché sur les cartes',
    context         TEXT                         NULL,
    work_done       TEXT                         NULL,
    learnings       TEXT                         NULL,
    youtube_id      CHAR(11)                     NULL     COMMENT 'Identifiant YouTube seul (jamais d''URL ni d''iframe)',
    external_url    VARCHAR(255)                 NULL,
    external_label  VARCHAR(60)                  NULL,
    status          ENUM('done','in_progress')   NOT NULL DEFAULT 'in_progress',
    project_date    DATE                         NULL,
    is_published    TINYINT(1)                   NOT NULL DEFAULT 0 COMMENT '0 = brouillon',
    is_featured     TINYINT(1)                   NOT NULL DEFAULT 0 COMMENT '1 = mis en avant sur l''accueil',
    position        SMALLINT UNSIGNED            NOT NULL DEFAULT 0,
    created_at      DATETIME                     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME                     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_slug (slug),
    KEY idx_projects_listing (section, is_published, position),
    KEY idx_projects_featured (is_featured, is_published),
    CONSTRAINT chk_projects_youtube CHECK (youtube_id IS NULL OR youtube_id REGEXP '^[A-Za-z0-9_-]{11}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liaison N-N projets <-> catégories (un projet peut être « Roblox » ET « Sci-fi »).
CREATE TABLE project_categories (
    project_id   INT UNSIGNED  NOT NULL,
    category_id  INT UNSIGNED  NOT NULL,
    PRIMARY KEY (project_id, category_id),
    KEY idx_pc_category (category_id),
    CONSTRAINT fk_pc_project  FOREIGN KEY (project_id)  REFERENCES projects (id)   ON DELETE CASCADE,
    CONSTRAINT fk_pc_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liaison N-N projets <-> tags.
CREATE TABLE project_tags (
    project_id  INT UNSIGNED  NOT NULL,
    tag_id      INT UNSIGNED  NOT NULL,
    PRIMARY KEY (project_id, tag_id),
    KEY idx_pt_tag (tag_id),
    CONSTRAINT fk_pt_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
    CONSTRAINT fk_pt_tag     FOREIGN KEY (tag_id)     REFERENCES tags (id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Images d'un projet. Les fichiers sont ré-encodés côté serveur et renommés
-- avec un nom aléatoire. L'image de plus petite « position » sert de couverture.
CREATE TABLE project_images (
    id              INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    project_id      INT UNSIGNED       NOT NULL,
    filename        VARCHAR(64)        NOT NULL COMMENT 'Grande version (ex. 3f9c…e1.webp)',
    thumb_filename  VARCHAR(64)        NOT NULL COMMENT 'Miniature (ex. 3f9c…e1-thumb.webp)',
    alt_text        VARCHAR(200)       NOT NULL DEFAULT '',
    width           SMALLINT UNSIGNED  NOT NULL,
    height          SMALLINT UNSIGNED  NOT NULL,
    position        SMALLINT UNSIGNED  NOT NULL DEFAULT 0,
    created_at      DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_images_filename (filename),
    KEY idx_images_project (project_id, position),
    CONSTRAINT fk_images_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  Parcours, compétences, à propos
-- -----------------------------------------------------------------------------

-- Entrées de la timeline (études, stages…). end_date NULL = « en cours ».
-- date_precision règle l'affichage : 'year' => « 2022 », 'month' => « mai 2023 »,
-- 'day' => « 18 mai 2023 ».
CREATE TABLE timeline_entries (
    id              INT UNSIGNED                              NOT NULL AUTO_INCREMENT,
    type            ENUM('education','internship','other')    NOT NULL,
    title           VARCHAR(150)                              NOT NULL,
    organization    VARCHAR(150)                              NOT NULL,
    location        VARCHAR(100)                              NULL,
    start_date      DATE                                      NOT NULL,
    end_date        DATE                                      NULL,
    date_precision  ENUM('day','month','year')                NOT NULL DEFAULT 'month',
    description     TEXT                                      NULL,
    is_published    TINYINT(1)                                NOT NULL DEFAULT 1,
    position        SMALLINT UNSIGNED                         NOT NULL DEFAULT 0,
    created_at      DATETIME                                  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME                                  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_timeline_listing (is_published, start_date),
    CONSTRAINT chk_timeline_dates CHECK (end_date IS NULL OR end_date >= start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compétences : trois niveaux d'organisation.
--   skill_themes      thèmes généraux (Informatique & réseaux, Développement, Langues…)
--   skill_categories  sous-catégories d'un thème (Réseau, Systèmes, Scripting / Lua…)
--   skills            compétences d'une sous-catégorie, avec ou sans niveau
-- Les trois tables sont créées dans cet ordre (clés étrangères).

-- Thèmes généraux de compétences (titres h2 de la page « Compétences »).
-- icon : clé d'icône du helper icon() (network, code, chip, globe, user, star…).
CREATE TABLE skill_themes (
    id          INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    name        VARCHAR(80)        NOT NULL,
    slug        VARCHAR(90)        NOT NULL COMMENT 'ancre de la page publique : #theme-{slug}',
    icon        VARCHAR(40)        NULL,
    position    SMALLINT UNSIGNED  NOT NULL DEFAULT 0,
    created_at  DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_skill_themes_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sous-catégories de compétences (Réseau, Systèmes, Scripting / Lua…), rangées dans un thème.
-- icon : clé d'icône du helper icon() (mêmes valeurs que les thèmes) ; position : ordre dans le thème.
-- ON DELETE RESTRICT : on ne peut pas supprimer un thème qui contient encore des sous-catégories.
CREATE TABLE skill_categories (
    id          INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    theme_id    INT UNSIGNED       NOT NULL,
    name        VARCHAR(80)        NOT NULL,
    slug        VARCHAR(90)        NOT NULL,
    icon        VARCHAR(40)        NULL,
    position    SMALLINT UNSIGNED  NOT NULL DEFAULT 0,
    created_at  DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_skill_categories_slug (slug),
    KEY idx_skill_categories_theme (theme_id, position),
    CONSTRAINT fk_skill_categories_theme FOREIGN KEY (theme_id) REFERENCES skill_themes (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compétences, avec un niveau de 1 (notions) à 5 (maîtrise).
-- level NULL : compétence affichée SANS barre de niveau (savoir-être, atouts).
-- level_label : libellé affiché à la place du libellé standard du niveau
--   (ex. « A2 » ou « Langue maternelle » pour une langue) ; NULL = libellé standard.
-- ON DELETE RESTRICT : on ne peut pas supprimer une sous-catégorie qui contient encore des compétences.
CREATE TABLE skills (
    id           INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    category_id  INT UNSIGNED       NOT NULL,
    name         VARCHAR(80)        NOT NULL,
    level        TINYINT UNSIGNED   NULL COMMENT '1 à 5, NULL = sans barre de niveau',
    level_label  VARCHAR(40)        NULL COMMENT 'libellé personnalisé du niveau (A2, Langue maternelle…)',
    description  VARCHAR(255)       NULL,
    position     SMALLINT UNSIGNED  NOT NULL DEFAULT 0,
    created_at   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_skills_category (category_id, position),
    CONSTRAINT fk_skills_category FOREIGN KEY (category_id) REFERENCES skill_categories (id) ON DELETE RESTRICT,
    CONSTRAINT chk_skills_level CHECK (level IS NULL OR level BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Centres d'intérêt (page À propos). icon : bike, gamepad, code, shield, network, music, book…
CREATE TABLE interests (
    id           INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    label        VARCHAR(80)        NOT NULL,
    description  VARCHAR(255)       NULL,
    icon         VARCHAR(40)        NULL,
    position     SMALLINT UNSIGNED  NOT NULL DEFAULT 0,
    created_at   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  Contact
-- -----------------------------------------------------------------------------

-- Messages reçus via le formulaire (purgés automatiquement après 12 mois — RGPD).
CREATE TABLE messages (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100)  NOT NULL,
    email       VARCHAR(254)  NOT NULL,
    subject     VARCHAR(150)  NOT NULL,
    body        TEXT          NOT NULL,
    is_read     TINYINT(1)    NOT NULL DEFAULT 0,
    mail_sent   TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '1 = notification e-mail envoyée',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_messages_inbox (is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  DONNÉES DE DÉPART
-- =============================================================================
--  Contenu initial du site, modifiable ensuite depuis l'administration.
--  Les textes signalés dans docs/CONTENU-A-VERIFIER.md sont des propositions
--  à relire et corriger (descriptions, détails techniques, missions de stage…).
--
--  Les identifiants sont fixés explicitement pour que les liaisons restent
--  lisibles. Les tables venant d'être recréées ci-dessus, ce bloc peut être
--  rejoué sans conflit. Les liaisons projets <-> catégories passent par une
--  jointure sur la section : une catégorie d'une autre section est ignorée.
--  Aucun compte administrateur, aucun message et aucune image ici.
-- =============================================================================

-- -----------------------------------------------------------------------------
--  Réglages (toutes les clés de App\Models\Setting::DEFAULTS)
-- -----------------------------------------------------------------------------
INSERT INTO settings (setting_key, setting_value) VALUES
('site_name',        'Alexandre Derbez'),
('site_class',       'BTS CIEL · IR'),
('hero_kicker',      '-- Bonjour, je suis'),
('hero_title',       'Étudiant BTS CIEL option IR · Cybersécurité & Réseaux'),
('hero_tagline',     'En 1re année au Campus St Jean-Baptiste de La Salle à Avignon. Je m''intéresse aux réseaux, à la sécurité des systèmes et au développement de jeux en Lua sur Roblox Studio.'),
('banner_enabled',   '1'),
('banner_title',     'Recherche de stage'),
('banner_text',      'en développement informatique / réseaux'),
('internship_start', '2026-05-18'),
('internship_end',   '2026-06-26'),
('location',         'Avignon (84)'),
-- Liens et adresse de contact : à renseigner depuis l'administration.
('linkedin_url',     ''),
('github_url',       ''),
('contact_email',    ''),
('meta_description', 'Portfolio d''Alexandre Derbez, étudiant en BTS CIEL option IR à Avignon : réseaux, cybersécurité et jeux Roblox en Lua. Recherche de stage en 2026.'),
-- Date de mise à jour du CV : remplie automatiquement à l'envoi du PDF.
('cv_updated_at',    ''),
('about_text',       'Je m''appelle Alexandre Derbez et je suis en **1re année de BTS CIEL option IR** (Cybersécurité, Informatique et réseaux, Électronique, option Informatique et Réseaux) au Campus St Jean-Baptiste de La Salle à Avignon, depuis 2025. J''y ai auparavant obtenu un **Bac Pro SN option RISC** (Systèmes Numériques, Réseaux Informatiques et Systèmes Communicants).

Pendant mon Bac Pro, j''ai effectué **6 stages en maintenance informatique** : à La Salle Avignon, à la Cité scolaire Frédéric Mistral d''Avignon et au laboratoire LNC du CNRS à Marseille. J''y ai appris à diagnostiquer et dépanner des postes, à préparer du matériel pour les utilisateurs et à travailler au sein d''une équipe technique, avec rigueur et sens du service.

Ce qui me passionne le plus, ce sont **les réseaux et la cybersécurité** : comprendre comment les machines communiquent, comment on organise et protège un réseau, et comment on sécurise un système. Le BTS me permet d''approfondir ces sujets en cours et en travaux pratiques.

En parallèle, je développe des jeux sur **Roblox Studio**, entièrement scriptés en **Lua**, objets compris. C''est un excellent terrain d''apprentissage : logique client / serveur, sauvegarde des données, interfaces, et surtout le réflexe de toujours vérifier côté serveur ce qu''envoie le joueur. Pour décrocher de l''écran, je pratique le **VTT**.

Je recherche actuellement un **stage en développement informatique ou en réseaux, du 18 mai au 26 juin 2026**. N''hésitez pas à me contacter : je serai ravi d''échanger sur vos projets.'),
('legal_publisher',  'Alexandre Derbez'),
('legal_host',       'Hostinger International Ltd.
61 Lordou Vironos Street, 6023 Larnaca, Chypre
https://www.hostinger.fr');

-- -----------------------------------------------------------------------------
--  Catégories de projets
--    section game : 1 Roblox · 2 Sci-fi & temporel · 3 Systèmes de jeu · 4 Autres
--    section tech : 5 Réseau · 6 Cybersécurité · 7 Systèmes · 8 TP & CTF
-- -----------------------------------------------------------------------------
INSERT INTO categories (id, section, name, slug, description, position) VALUES
(1, 'game', 'Roblox',            'roblox',          'Jeux et systèmes développés sur Roblox Studio, entièrement scriptés en Lua.', 1),
(2, 'game', 'Sci-fi & temporel', 'sci-fi-temporel', 'Projets autour de la science-fiction et du voyage dans le temps.', 2),
(3, 'game', 'Systèmes de jeu',   'systemes-de-jeu', 'Mécaniques complètes : progression, sauvegarde, classements, interfaces.', 3),
(4, 'game', 'Autres',            'autres',          'Autres projets de jeu.', 4),
(5, 'tech', 'Réseau',            'reseau',          'Conception, configuration et dépannage de réseaux.', 1),
(6, 'tech', 'Cybersécurité',     'cybersecurite',   'Sécurisation des systèmes et des réseaux, analyse et bonnes pratiques.', 2),
(7, 'tech', 'Systèmes',          'systemes',        'Systèmes d''exploitation, administration et fonctionnement bas niveau.', 3),
(8, 'tech', 'TP & CTF',          'tp-ctf',          'Travaux pratiques du BTS et défis de sécurité (Capture The Flag).', 4);

-- -----------------------------------------------------------------------------
--  Tags (technologies), partagés entre les sections
-- -----------------------------------------------------------------------------
INSERT INTO tags (id, name, slug) VALUES
-- Développement Roblox
( 1, 'Lua',            'lua'),
( 2, 'Luau',           'luau'),
( 3, 'Roblox Studio',  'roblox-studio'),
( 4, 'DataStore',      'datastore'),
( 5, 'RemoteEvents',   'remoteevents'),
( 6, 'ModuleScripts',  'modulescripts'),
( 7, 'TweenService',   'tweenservice'),
( 8, 'Interface (UI)', 'interface-ui'),
( 9, 'Physique',       'physique'),
(10, 'Raycast',        'raycast'),
-- Réseaux, systèmes et outils
(11, 'Réseau',         'reseau'),
(12, 'Linux',          'linux'),
(13, 'Windows',        'windows'),
(14, 'Cisco',          'cisco'),
(15, 'Packet Tracer',  'packet-tracer'),
(16, 'Wireshark',      'wireshark'),
(17, 'Git',            'git'),
(18, 'OS',             'os');

-- -----------------------------------------------------------------------------
--  Projets de jeux (section game)
--    1 TARDIS · 2 Portail de la TVA · 3 Cardverse · 4 OS Fondationnaire (brouillon)
-- -----------------------------------------------------------------------------

-- 1. TARDIS (publié, mis en avant)
INSERT INTO projects (id, section, title, slug, summary, context, work_done, learnings,
                      youtube_id, external_url, external_label, status, project_date,
                      is_published, is_featured, position) VALUES
(1, 'game', 'TARDIS', 'tardis',
'Machine à voyager dans le temps inspirée de Doctor Who : un intérieur plus grand que l''extérieur, une console de pilotage interactive et une séquence complète de dématérialisation puis de rematérialisation, le tout scripté en Lua.',
'Le TARDIS est la machine à voyager dans le temps de la série britannique *Doctor Who* : une cabine de police bleue, célèbre pour être « plus grande à l''intérieur qu''à l''extérieur ». J''ai voulu recréer cet objet emblématique dans Roblox Studio, pour qu''un joueur puisse entrer dans la cabine, piloter la machine depuis sa console et la voir disparaître puis réapparaître ailleurs.

Ce projet m''intéressait parce qu''il pose plusieurs vrais problèmes techniques :

- donner l''illusion d''un intérieur immense dans une cabine minuscule ;
- rendre une console interactive utilisable par plusieurs joueurs en même temps ;
- synchroniser une animation longue, la dématérialisation, entre le serveur et tous les joueurs ;
- garder le contrôle côté serveur, pour qu''un joueur ne puisse pas déclencher un voyage ou modifier l''état de la machine en trichant.

Comme tous mes jeux, le TARDIS est **entièrement scripté en Lua** (le Luau de Roblox), objets compris : les commandes, les portes, les effets et la logique de vol sont codés par mes soins.

C''est aussi un projet de passionné : j''ai cherché à retrouver l''ambiance de la série dans la salle de contrôle, tout en gardant un fonctionnement simple à prendre en main pour un joueur qui ne la connaît pas.',
'### L''intérieur plus grand que l''extérieur

L''intérieur n''est pas réellement dans la cabine : c''est une salle de contrôle construite à part, à l''écart de la carte. Quand un joueur franchit la porte, un script serveur détecte son entrée et le téléporte dans la salle en modifiant le `CFrame` de son personnage. À la sortie, l''opération inverse le replace devant la cabine, à l''endroit où elle se trouve à ce moment-là.

### La console de pilotage

- Chaque commande de la console (leviers, boutons, choix de la destination) réagit à l''aide d''un `ProximityPrompt` ou d''un `ClickDetector`.
- L''action est transmise au serveur par un `RemoteEvent`. Le serveur **vérifie** que le joueur se trouve bien dans la salle de contrôle, que la machine n''est pas déjà en vol et que la demande est cohérente avant d''agir.
- L''état de la machine (au sol, en dématérialisation, en vol, en rematérialisation) est centralisé dans un `ModuleScript` qui joue le rôle de machine à états : une action n''est acceptée que dans l''état prévu.

### Dématérialisation et rematérialisation

La séquence fait clignoter puis disparaître progressivement la cabine extérieure grâce à `TweenService`, qui anime la transparence des pièces, avec des effets sonores et lumineux dans la salle de contrôle. Le serveur déplace ensuite la cabine à sa nouvelle position, puis la séquence inverse la fait réapparaître. Les effets purement visuels sont joués par des `LocalScript` côté client, sur ordre du serveur, pour rester fluides.

### Interface

Une petite interface (`ScreenGui`) affiche aux joueurs présents dans la salle l''état de la machine et la destination choisie.',
'Ce projet m''a surtout appris à raisonner en **client / serveur**. Dans Roblox, tout ce qui s''exécute sur l''appareil du joueur peut être modifié par un tricheur. La règle que j''en retiens : ne jamais faire confiance au client. Le client demande, le serveur vérifie et décide.

Ce que je retiens en particulier :

- **La validation côté serveur** : chaque `RemoteEvent` reçu est contrôlé (qui l''envoie, depuis où, dans quel état se trouve la machine). C''est le même réflexe qu''en cybersécurité : filtrer et valider toute entrée.
- **La machine à états** : modéliser le TARDIS comme une suite d''états bien définis évite toute une famille de bugs, comme un double déclenchement ou un joueur bloqué en plein vol.
- **Les `CFrame`** : positionner et orienter des objets et des personnages dans l''espace 3D, et réaliser des téléportations propres.
- **La séparation des rôles** : la logique dans des `ModuleScript` réutilisables, les effets visuels dans des `LocalScript`, les décisions dans des scripts serveur.
- **Les animations avec `TweenService`** : enchaîner des animations sans bloquer le reste du jeu.

Si je devais recommencer, je commenterais davantage mon code dès le départ et je découperais encore plus les modules, afin de pouvoir réutiliser la console et la machine à états dans d''autres projets. J''ai aussi compris qu''un projet ambitieux se construit par étapes : d''abord une version simple qui fonctionne, ensuite les effets et les finitions.',
NULL, NULL, NULL, 'in_progress', NULL, 1, 1, 1);

-- 2. Portail de la TVA (publié, mis en avant)
INSERT INTO projects (id, section, title, slug, summary, context, work_done, learnings,
                      youtube_id, external_url, external_label, status, project_date,
                      is_published, is_featured, position) VALUES
(2, 'game', 'Portail de la TVA', 'portail-de-la-tva',
'Portail temporel inspiré de la Time Variance Authority de la série Loki : ouverture animée du portail, effets visuels synchronisés pour tous les joueurs et téléportation fiable, entièrement scripté en Lua.',
'Dans la série *Loki*, la Time Variance Authority (TVA) utilise des portails temporels pour se déplacer d''une époque à l''autre : une porte lumineuse s''ouvre, les agents la traversent et réapparaissent ailleurs. J''ai voulu reproduire ce portail dans Roblox Studio, avec une ouverture spectaculaire et une téléportation fiable des joueurs.

Comme le TARDIS, c''est un projet autour du voyage dans le temps, mais l''objectif est différent : ici, on ne pilote pas un véhicule, on ouvre un passage que les joueurs franchissent. Les difficultés principales étaient :

- rendre l''ouverture du portail crédible et fluide, de la même façon pour tous les joueurs ;
- téléporter un ou plusieurs joueurs sans les bloquer dans le décor ni les faire tomber ;
- empêcher qu''un joueur ouvre un portail ou se téléporte là où il ne devrait pas ;
- gérer la fermeture du portail et les cas limites : joueur qui quitte la partie pendant le passage, portail déjà ouvert, demandes répétées.

Comme pour mes autres jeux, tout est **scripté en Lua**, de l''objet qui ouvre le portail jusqu''aux effets visuels.',
'### Ouverture animée du portail

L''ouverture est découpée en étapes : apparition d''un point lumineux, agrandissement du cercle, puis stabilisation du portail. Chaque étape est une animation `TweenService` sur la taille, la transparence et la couleur des pièces, complétée par des particules (`ParticleEmitter`), des lumières (`PointLight`) et des sons. Le serveur décide de l''ouverture et prévient les clients ; les effets les plus lourds sont joués localement par un `LocalScript` pour garder un rendu fluide.

### Déclenchement et contrôles côté serveur

- Le portail s''ouvre depuis un objet ou une commande prévus pour cela ; la demande passe par un `RemoteEvent`.
- Le serveur vérifie que le joueur a le droit d''ouvrir le portail, qu''il se trouve à proximité et qu''un délai minimum entre deux ouvertures est respecté, pour éviter les abus.
- Un portail ne peut pas être ouvert deux fois en même temps : son état (fermé, en ouverture, ouvert, en fermeture) est suivi par le serveur.

### Téléportation des joueurs

Quand un joueur traverse le portail, une zone de détection placée dans l''ouverture prévient le serveur. Celui-ci vérifie que le portail est bien ouvert, puis déplace le personnage en modifiant le `CFrame` de son `HumanoidRootPart` vers le point d''arrivée, légèrement décalé pour que plusieurs joueurs n''apparaissent pas au même endroit. Côté client, un court fondu à l''écran masque le déplacement.

### Fermeture

Après un temps défini, le portail se referme avec l''animation inverse et revient à l''état fermé, prêt pour une nouvelle ouverture.',
'Ce projet m''a appris à **synchroniser** un effet entre le serveur et tous les joueurs. Si chaque client lance l''animation à son propre rythme, tout le monde ne voit pas la même chose au même moment. J''ai compris l''intérêt de laisser le serveur décider *quand* les choses se passent, et les clients décider *comment* elles s''affichent.

Les points clés que je retiens :

- **Ne jamais faire confiance au client** : un tricheur peut envoyer n''importe quel `RemoteEvent` à n''importe quel moment. La vérification de la position, de l''état du portail et du délai entre deux demandes se fait donc côté serveur.
- **Limiter la fréquence des requêtes** : c''est le même principe que la protection contre le brute-force ou le spam sur un site web.
- **Penser aux cas limites** : un joueur qui se déconnecte pendant la téléportation, deux joueurs qui entrent en même temps, un portail qui se ferme au moment où quelqu''un arrive.
- **Les performances** : trop de particules et d''effets simultanés peuvent ralentir les appareils modestes, notamment les téléphones. J''ai appris à doser les effets.
- **La téléportation avec les `CFrame`** : maîtriser la position et l''orientation à l''arrivée, sans que le personnage reste coincé.

Ce sont des réflexes qui me servent directement en BTS CIEL : valider les entrées, limiter les abus et prévoir les erreurs avant qu''elles n''arrivent.',
NULL, NULL, NULL, 'in_progress', NULL, 1, 1, 2);

-- 3. Cardverse (publié, mis en avant)
INSERT INTO projects (id, section, title, slug, summary, context, work_done, learnings,
                      youtube_id, external_url, external_label, status, project_date,
                      is_published, is_featured, position) VALUES
(3, 'game', 'Cardverse', 'cardverse',
'Jeu de cartes à collectionner sur Roblox : factions, progression par l''expérience (XP), saisons et classements des joueurs, avec sauvegarde des données et une logique de jeu contrôlée par le serveur.',
'Cardverse est un jeu de cartes à collectionner que je développe sur Roblox Studio. Le joueur rassemble des cartes, les organise selon leur **faction**, gagne de l''**expérience (XP)** pour progresser et se mesure aux autres dans des **classements** renouvelés à chaque **saison**.

C''est mon projet le plus orienté « systèmes » : il y a moins d''effets visuels que dans le TARDIS ou le Portail de la TVA, mais beaucoup plus de données à gérer et à protéger. Un jeu de collection n''a d''intérêt que si la progression est fiable : un joueur ne doit ni perdre ses cartes, ni pouvoir s''en attribuer en trichant.

Les objectifs que je me suis fixés :

- une collection de cartes sauvegardée et retrouvée à chaque connexion ;
- des factions qui donnent une identité aux cartes et orientent la stratégie ;
- une progression par niveaux (XP) claire et motivante ;
- des saisons, pour relancer régulièrement l''intérêt du jeu ;
- des classements entre joueurs, mis à jour automatiquement ;
- une interface lisible sur ordinateur comme sur téléphone.

Comme mes autres jeux, Cardverse est **entièrement scripté en Lua**, cartes et objets compris : chaque carte est décrite par des données, et le jeu s''appuie sur ces données plutôt que sur des objets assemblés à la main.',
'### Les cartes et les factions

Les cartes sont décrites dans des `ModuleScript`, sous forme de tables Lua (nom, faction, rareté, caractéristiques). Le reste du jeu lit ces tables : ajouter une carte revient à ajouter une entrée, sans toucher au code des mécaniques. Les factions sont définies de la même façon, ce qui permet de filtrer la collection et d''appliquer des règles propres à chaque faction.

### Progression XP et sauvegarde

- Les gains d''XP et les montées de niveau sont **calculés uniquement par le serveur** : le client se contente d''afficher le résultat.
- La collection, le niveau et l''XP de chaque joueur sont enregistrés avec `DataStoreService` : chargement à l''arrivée du joueur, sauvegarde à son départ et à intervalles réguliers.
- Les appels au DataStore sont protégés par `pcall`, avec de nouvelles tentatives en cas d''échec, pour ne pas perdre de données si le service est momentanément indisponible.

### Saisons et classements

Les classements reposent sur un `OrderedDataStore`, qui renvoie directement les meilleurs scores déjà triés. Chaque saison utilise sa propre clé de classement : à la fin d''une saison, un nouveau classement démarre sans effacer l''historique.

### Communication client / serveur

Les actions du joueur (consulter sa collection, jouer une carte, réclamer une récompense) passent par des `RemoteEvent` et des `RemoteFunction`. Le serveur vérifie systématiquement que le joueur possède bien la carte, que l''action est permise à ce moment et que les valeurs reçues sont cohérentes.

### Interface

L''interface (collection, profil, classement) est construite avec des `ScreenGui` et animée avec `TweenService`. Elle s''adapte à la taille de l''écran grâce aux outils de mise en page de Roblox (`UIGridLayout`, `UIListLayout`, `UIAspectRatioConstraint`).',
'Cardverse m''a appris à **concevoir avant de coder**. Avec autant de données (cartes, factions, XP, saisons), improviser mène vite à un code difficile à maintenir. J''ai appris à séparer les données (les tables des cartes) de la logique (les règles du jeu), et la logique de l''affichage (l''interface).

Ce que je retiens :

- **La persistance des données** : sauvegarder correctement, gérer les échecs du service, éviter d''écraser une sauvegarde récente par une plus ancienne. C''est une première approche concrète de la fiabilité d''un système.
- **La sécurité d''une économie de jeu** : dans un jeu de collection, la triche vise surtout les récompenses. Tout ce qui crée de la valeur (XP, cartes) est donc décidé par le serveur, jamais par le client.
- **Le tri et le classement** : l''`OrderedDataStore` m''a fait comprendre l''intérêt de choisir une structure de données adaptée à la question que l''on veut poser.
- **Les `ModuleScript`** : partager du code et des données entre plusieurs scripts sans les dupliquer.
- **Une interface adaptative** : un même écran doit rester utilisable sur un grand moniteur comme sur un téléphone.

Ces notions (données structurées, sauvegarde, contrôle des accès, validation des entrées) sont exactement celles que je retrouve en BTS, côté réseaux comme côté développement. Cardverse est aussi le projet sur lequel j''ai le plus appris à corriger et améliorer un code existant plutôt qu''à tout réécrire.',
NULL, NULL, NULL, 'in_progress', NULL, 1, 1, 3);

-- 4. OS Fondationnaire (BROUILLON : description à rédiger par Alexandre)
INSERT INTO projects (id, section, title, slug, summary, context, work_done, learnings,
                      youtube_id, external_url, external_label, status, project_date,
                      is_published, is_featured, position) VALUES
(4, 'game', 'OS Fondationnaire', 'os-fondationnaire',
'Description à compléter depuis l''administration.',
'À compléter : à quoi sert cet OS dans le jeu ?

- À compléter : dans quel jeu ou quelle expérience Roblox est-il utilisé ?
- À compléter : d''où vient le nom « Fondationnaire » (univers, inspiration) ?
- À compléter : qui l''utilise (tous les joueurs, un rôle particulier) et pour faire quoi ?',
'À compléter : qu''as-tu codé, et comment ?

- À compléter : quelles fonctionnalités (fenêtres, applications, connexion, terminal…) ?
- À compléter : comment l''interface est-elle construite (`ScreenGui`, `SurfaceGui`…) ?
- À compléter : quels échanges client / serveur (`RemoteEvent`) et quelles vérifications côté serveur ?
- À compléter : des données sont-elles sauvegardées (`DataStoreService`) ?',
'À compléter : qu''as-tu appris en réalisant ce projet ?

- À compléter : la principale difficulté rencontrée et la façon dont tu l''as résolue.
- À compléter : ce que tu ferais différemment aujourd''hui.
- À compléter : les compétences réutilisables en BTS CIEL ou en stage.',
NULL, NULL, NULL, 'in_progress', NULL, 0, 1, 4);

-- -----------------------------------------------------------------------------
--  Projets techniques (section tech)
--    5 Création d'un réel OS (brouillon)
-- -----------------------------------------------------------------------------
INSERT INTO projects (id, section, title, slug, summary, context, work_done, learnings,
                      youtube_id, external_url, external_label, status, project_date,
                      is_published, is_featured, position) VALUES
(5, 'tech', 'Création d''un réel OS', 'creation-d-un-reel-os',
'Système d''exploitation développé de zéro. Description à compléter depuis l''administration : langage, démarrage, avancement.',
'À compléter : pourquoi as-tu voulu créer ton propre système d''exploitation ?

- À compléter : quel est l''objectif (comprendre le fonctionnement d''un ordinateur, relever un défi, usage précis) ?
- À compléter : pour quelle architecture (x86, x86-64, ARM…) ?
- À compléter : dans quel environnement le testes-tu (QEMU, VirtualBox, machine réelle) ?',
'À compléter : qu''est-ce qui fonctionne déjà ?

- À compléter : langage(s) utilisé(s) (C, assembleur, Rust…).
- À compléter : démarrage (chargeur d''amorçage maison, GRUB / Multiboot, UEFI).
- À compléter : ce qui est réalisé (affichage texte, clavier, interruptions, gestion de la mémoire, système de fichiers…).
- À compléter : ce qui est en cours ou prévu.',
'À compléter : qu''as-tu appris en réalisant ce projet ?

- À compléter : les notions découvertes (mémoire, processeur, matériel).
- À compléter : la principale difficulté rencontrée et la façon dont tu l''as résolue.
- À compléter : les liens avec le BTS CIEL (systèmes, électronique, sécurité).',
NULL, NULL, NULL, 'in_progress', NULL, 0, 1, 1);

-- -----------------------------------------------------------------------------
--  Liaisons projets <-> catégories
--  La jointure sur la section garantit qu'un projet de jeux n'est rangé que
--  dans des catégories de jeux (et inversement).
-- -----------------------------------------------------------------------------
INSERT INTO project_categories (project_id, category_id)
SELECT p.id, c.id
FROM projects p
JOIN categories c ON c.section = p.section
WHERE (p.slug = 'tardis'                AND c.slug IN ('roblox', 'sci-fi-temporel'))
   OR (p.slug = 'portail-de-la-tva'     AND c.slug IN ('roblox', 'sci-fi-temporel'))
   OR (p.slug = 'cardverse'             AND c.slug IN ('roblox', 'systemes-de-jeu'))
   OR (p.slug = 'os-fondationnaire'     AND c.slug IN ('roblox', 'systemes-de-jeu'))
   OR (p.slug = 'creation-d-un-reel-os' AND c.slug IN ('systemes'));

-- -----------------------------------------------------------------------------
--  Liaisons projets <-> tags
-- -----------------------------------------------------------------------------
INSERT INTO project_tags (project_id, tag_id)
SELECT p.id, t.id
FROM projects p
CROSS JOIN tags t
WHERE (p.slug = 'tardis'                AND t.slug IN ('lua', 'luau', 'roblox-studio', 'remoteevents', 'modulescripts', 'tweenservice', 'interface-ui'))
   OR (p.slug = 'portail-de-la-tva'     AND t.slug IN ('lua', 'luau', 'roblox-studio', 'remoteevents', 'tweenservice'))
   OR (p.slug = 'cardverse'             AND t.slug IN ('lua', 'luau', 'roblox-studio', 'datastore', 'remoteevents', 'modulescripts', 'interface-ui'))
   OR (p.slug = 'os-fondationnaire'     AND t.slug IN ('lua', 'roblox-studio', 'os'))
   OR (p.slug = 'creation-d-un-reel-os' AND t.slug IN ('os'));

-- -----------------------------------------------------------------------------
--  Parcours (timeline). Affichage par date de début décroissante, puis position.
-- -----------------------------------------------------------------------------
INSERT INTO timeline_entries (id, type, title, organization, location, start_date, end_date,
                              date_precision, description, is_published, position) VALUES
(1, 'education', 'BTS CIEL option IR', 'Campus St Jean-Baptiste de La Salle', 'Avignon',
 '2025-09-01', NULL, 'year',
'BTS Cybersécurité, Informatique et réseaux, Électronique, option Informatique et Réseaux, en 1re année.

Au programme : réseaux (adressage, commutation, routage), cybersécurité, administration de systèmes Windows et Linux, électronique et développement.',
 1, 0),
(2, 'internship', '6 stages en maintenance informatique',
 'La Salle Avignon · Cité scolaire Frédéric Mistral · CNRS LNC Marseille', 'Avignon et Marseille',
 '2022-09-01', '2025-06-30', 'year',
'Six stages réalisés pendant le Bac Pro, dans des établissements scolaires et un laboratoire du CNRS. Missions types de maintenance informatique :

- diagnostic et dépannage de postes de travail (matériel et logiciel) ;
- remplacement de composants : disque, mémoire, alimentation ;
- installation et configuration de Windows et des logiciels ;
- préparation et mise en service de postes pour les utilisateurs ;
- vérification du câblage et du raccordement au réseau ;
- assistance aux utilisateurs et suivi des interventions.

Le détail des missions de chaque stage est à compléter.',
 1, 1),
(3, 'education', 'Bac Pro SN option RISC', 'Campus St Jean-Baptiste de La Salle', 'Avignon',
 '2022-09-01', '2025-07-04', 'year',
'Baccalauréat professionnel Systèmes Numériques, option Réseaux Informatiques et Systèmes Communicants (RISC). Diplôme obtenu.

Formation centrée sur l''installation, la mise en service et la maintenance des équipements informatiques et réseaux.',
 1, 2);

-- -----------------------------------------------------------------------------
--  Compétences : thèmes > sous-catégories > compétences.
--  Niveaux CONFIRMÉS par Alexandre (novembre 2026).
--  Échelle : 1 Notions · 2 Bases · 3 Intermédiaire · 4 Avancé · 5 Maîtrise.
--  Langues : correspondance CECRL indicative A1 = 1, A2 = 2, B1 = 3, B2 = 4,
--  C1/C2 = 5 ; le libellé (level_label) remplace le libellé standard.
--  level NULL : compétence sans barre de niveau (savoir-être, autres atouts).
-- -----------------------------------------------------------------------------
INSERT INTO skill_themes (id, name, slug, icon, position) VALUES
(1, 'Informatique & réseaux', 'informatique-reseaux', 'network', 1),
(2, 'Développement',          'developpement',        'code',    2),
(3, 'Électronique',           'electronique',         'chip',    3),
(4, 'Langues',                'langues',              'globe',   4),
(5, 'Savoir-être',            'savoir-etre',          'user',    5),
(6, 'Autres atouts',          'autres-atouts',        'star',    6);

-- Les identifiants 1 à 5 sont ceux des anciennes catégories (liens conservés).
INSERT INTO skill_categories (id, theme_id, name, slug, icon, position) VALUES
-- 1. Informatique & réseaux
( 1, 1, 'Réseau',          'reseau',          'network',  1),
( 2, 1, 'Systèmes',        'systemes',        'server',   2),
( 4, 1, 'Cybersécurité',   'cybersecurite',   'shield',   3),
( 5, 1, 'Outils',          'outils',          'tools',    4),
-- 2. Développement
( 3, 2, 'Scripting / Lua', 'scripting-lua',   'code',     1),
( 6, 2, 'Scripting / Web', 'scripting-web',   'code',     2),
( 7, 2, 'Autres langages', 'autres-langages', 'terminal', 3),
-- 3 à 6. Thèmes à une seule sous-catégorie (même nom : le titre n'est pas répété)
( 8, 3, 'Électronique',    'electronique',    'chip',     1),
( 9, 4, 'Langues',         'langues',         'globe',    1),
(10, 5, 'Savoir-être',     'savoir-etre',     'user',     1),
(11, 6, 'Autres atouts',   'autres-atouts',   'star',     1);

INSERT INTO skills (id, category_id, name, level, level_label, description, position) VALUES
-- Réseau
( 1,  1, 'Adressage IPv4 et sous-réseaux',    2, NULL, 'Plans d''adressage, masques et passerelles, calcul de sous-réseaux.', 1),
( 2,  1, 'Câblage et brassage',               3, NULL, 'Réalisation et test de câbles RJ45, brassage en baie.', 2),
( 3,  1, 'Commutation et VLAN',               2, NULL, 'Configuration de base de commutateurs et de VLAN en travaux pratiques.', 3),
( 4,  1, 'Routage',                           2, NULL, 'Routes statiques et premières notions de routage dynamique.', 4),
( 5,  1, 'Services réseau (DHCP, DNS)',       2, NULL, 'Rôle de ces services et mise en place simple en travaux pratiques.', 5),
-- Systèmes
( 6,  2, 'Maintenance matériel',              4, NULL, 'Diagnostic, remplacement de composants et remise en service de postes, pratiqués en 6 stages.', 1),
( 7,  2, 'Windows (poste de travail)',        3, NULL, 'Installation, configuration, pilotes et dépannage courant.', 2),
( 8,  2, 'Windows Server / Active Directory', 2, NULL, 'Notions d''Active Directory : comptes, groupes et partages.', 3),
( 9,  2, 'Linux',                             3, NULL, 'Ligne de commande, gestion des fichiers, des droits et des paquets.', 4),
(10,  2, 'Virtualisation',                    3, NULL, 'Création et utilisation de machines virtuelles pour les travaux pratiques.', 5),
-- Cybersécurité
(15,  4, 'Hygiène informatique',              3, NULL, 'Mots de passe, mises à jour et sauvegardes : bonnes pratiques appliquées en stage.', 1),
(16,  4, 'Validation côté serveur',           2, NULL, 'Contrôle des requêtes des joueurs et lutte contre la triche dans mes jeux Roblox.', 2),
(17,  4, 'Menaces et attaques courantes',     1, NULL, 'Premières notions : hameçonnage, logiciels malveillants et attaques réseau, pour mieux s''en protéger.', 3),
(18,  4, 'Sécurisation d''un poste',          1, NULL, 'Premières notions : comptes utilisateurs, pare-feu, mises à jour et antivirus.', 4),
-- Outils
(19,  5, 'Cisco Packet Tracer',               2, NULL, 'Simulation et test de réseaux pour les travaux pratiques.', 1),
(20,  5, 'Wireshark',                         2, NULL, 'Capture et lecture de trames pour observer les échanges réseau.', 2),
(21,  5, 'Outils de diagnostic matériel',     3, NULL, 'Tests de mémoire, de disque et d''alimentation en maintenance.', 3),
(22,  5, 'Git',                               3, NULL, 'Suivi des versions d''un projet : commits, historique, dépôt distant.', 4),
(23,  5, 'GitHub',                            3, NULL, 'Hébergement de dépôts en ligne : envoi du code, historique des modifications et partage.', 5),
-- Scripting / Lua
(11,  3, 'Lua / Luau',                        4, NULL, 'Langage principal de mes jeux : tables, modules, programmation événementielle.', 1),
(12,  3, 'Roblox Studio',                     4, NULL, 'Scripts serveur et client, RemoteEvents, DataStore, interfaces et animations.', 2),
(13,  3, 'Architecture client / serveur',     3, NULL, 'Séparation des rôles et validation côté serveur dans mes jeux.', 3),
-- Scripting / Web
(24,  6, 'HTML',                              3, NULL, 'Structure d''une page : titres, listes, liens, images, formulaires et balises sémantiques.', 1),
(25,  6, 'CSS',                               3, NULL, 'Mise en forme et mise en page : couleurs, typographie, flexbox, grilles et adaptation au mobile.', 2),
(26,  6, 'JavaScript',                        2, NULL, 'Bases du langage : variables, fonctions, événements et modification de la page.', 3),
(27,  6, 'PHP',                               2, NULL, 'Bases côté serveur : pages dynamiques et traitement de formulaires.', 4),
(28,  6, 'SQL et bases de données (MySQL)',   2, NULL, 'Tables et relations, requêtes simples pour lire, ajouter et modifier des données.', 5),
-- Autres langages
(14,  7, 'Python',                            2, NULL, 'Bases : variables, conditions, boucles et fonctions, petits scripts.', 1),
(29,  7, 'C / C++',                           2, NULL, 'Bases : variables, conditions, boucles, fonctions et compilation de petits programmes.', 2),
(30,  7, 'Bash / ligne de commande',          2, NULL, 'Commandes courantes du terminal et petits scripts pour enchaîner des tâches simples.', 3),
(31,  7, 'Java',                              2, NULL, 'Bases : syntaxe, classes et objets, petits programmes.', 4),
-- Électronique
(32,  8, 'Mesures (multimètre, oscilloscope)', 1, NULL, 'Premières notions : mesure de tensions et de courants, observation d''un signal.', 1),
(33,  8, 'Microcontrôleurs (Arduino, ESP32…)', 2, NULL, 'Bases de la programmation d''une carte : entrées, sorties, capteurs et actionneurs simples.', 2),
(34,  8, 'Soudure',                           2, NULL, 'Bases de la soudure à l''étain de composants sur une carte électronique.', 3),
-- Langues (libellé CECRL à la place du libellé standard)
(35,  9, 'Français',                          5, 'Langue maternelle', NULL, 1),
(36,  9, 'Anglais',                           2, 'A2',                NULL, 2),
(37,  9, 'Espagnol',                          1, 'A1',                NULL, 3),
-- Savoir-être (sans niveau : la description donne un exemple concret)
(38, 10, 'Travail en équipe',                 NULL, NULL, 'J''ai mené des projets de création de jeux à plusieurs.', 1),
(39, 10, 'Autonomie',                         NULL, NULL, NULL, 2),
(40, 10, 'Relation avec les utilisateurs',    NULL, NULL, 'En stage, j''ai accueilli et aidé les utilisateurs, pour des problèmes du quotidien comme pour des problèmes plus complexes.', 3),
(41, 10, 'Rigueur et organisation',           NULL, NULL, 'Je trie et je respecte un ordre et une méthode de travail.', 4),
(42, 10, 'Curiosité et autoformation',        NULL, NULL, 'J''ai toujours envie de découvrir les nouvelles technologies.', 5),
-- Autres atouts (sans niveau)
(43, 11, 'Formation BIA (brevet d''initiation aéronautique)', NULL, NULL, 'Formation suivie, sans passage du diplôme.', 1);

-- -----------------------------------------------------------------------------
--  Centres d'intérêt (page À propos)
-- -----------------------------------------------------------------------------
INSERT INTO interests (id, label, description, icon, position) VALUES
(1, 'VTT',                              'Sorties en pleine nature : endurance, persévérance et goût de l''effort.', 'bike', 1),
(2, 'Développement de jeux sur Roblox', 'Créer des jeux complets en Lua, des mécaniques jusqu''à l''interface.', 'gamepad', 2),
(3, 'Réseaux et cybersécurité',         'Comprendre comment les machines communiquent et comment les protéger.', 'shield', 3),
(4, 'Systèmes d''exploitation',         'Découvrir le fonctionnement d''un ordinateur au plus bas niveau, jusqu''à créer mon propre OS.', 'terminal', 4);
