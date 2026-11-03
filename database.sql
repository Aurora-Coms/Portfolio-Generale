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

-- Catégories de compétences (Réseau, Systèmes, Scripting / Lua…).
-- icon : clé d'icône parmi network, server, code, shield, tools, chip, terminal.
CREATE TABLE skill_categories (
    id          INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    name        VARCHAR(80)        NOT NULL,
    slug        VARCHAR(90)        NOT NULL,
    icon        VARCHAR(40)        NULL,
    position    SMALLINT UNSIGNED  NOT NULL DEFAULT 0,
    created_at  DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_skill_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compétences, avec un niveau de 1 (notions) à 5 (maîtrise).
-- ON DELETE RESTRICT : on ne peut pas supprimer une catégorie qui contient encore des compétences.
CREATE TABLE skills (
    id           INT UNSIGNED       NOT NULL AUTO_INCREMENT,
    category_id  INT UNSIGNED       NOT NULL,
    name         VARCHAR(80)        NOT NULL,
    level        TINYINT UNSIGNED   NOT NULL,
    description  VARCHAR(255)       NULL,
    position     SMALLINT UNSIGNED  NOT NULL DEFAULT 0,
    created_at   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_skills_category (category_id, position),
    CONSTRAINT fk_skills_category FOREIGN KEY (category_id) REFERENCES skill_categories (id) ON DELETE RESTRICT,
    CONSTRAINT chk_skills_level CHECK (level BETWEEN 1 AND 5)
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
-- @@SEED@@
