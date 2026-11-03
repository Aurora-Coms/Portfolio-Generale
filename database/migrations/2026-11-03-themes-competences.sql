-- =============================================================================
--  Migration du 3 novembre 2026 : thèmes de compétences
-- =============================================================================
--  À utiliser UNIQUEMENT sur un site déjà en ligne, dont la base a été créée
--  avec l'ancien database.sql (compétences rangées en simples catégories).
--  Pour une nouvelle installation, importez database.sql : il contient déjà
--  ce schéma et les compétences à jour.
--
--  Mode d'emploi : voir docs/MIGRATIONS.md (phpMyAdmin > SQL ou Importer).
--
--  Ce que fait le script :
--    1. crée la table skill_themes (thèmes généraux) ;
--    2. y ajoute les 6 thèmes : Informatique & réseaux, Développement,
--       Électronique, Langues, Savoir-être, Autres atouts ;
--    3. ajoute la colonne skill_categories.theme_id et range les catégories
--       existantes dans « Informatique & réseaux », sauf « Scripting / Lua »
--       qui va dans « Développement » ;
--    4. rend skills.level facultatif (NULL = pas de barre de niveau) et ajoute
--       la colonne skills.level_label (libellé du niveau, ex. « A2 »).
--
--  Aucune donnée n'est supprimée : projets, messages, compétences, niveaux et
--  descriptions restent tels quels. Les nouvelles sous-catégories et
--  compétences (Scripting / Web, Langues, Savoir-être…) s'ajoutent ensuite
--  depuis l'administration.
--
--  Relançable sans risque : chaque étape vérifie d'abord si elle a déjà été
--  faite (tables, colonnes, index et contraintes lus dans information_schema).
--  Aucune procédure stockée : les étapes conditionnelles passent par des
--  requêtes préparées (PREPARE / EXECUTE), acceptées par phpMyAdmin.
--
--  Compatible MySQL 8.0.19+ et MariaDB 10.4+ (Hostinger).
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
--  1. Table des thèmes (identique à database.sql)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS skill_themes (
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

-- -----------------------------------------------------------------------------
--  2. Les 6 thèmes. INSERT IGNORE : un thème déjà présent (même id ou même
--     slug) est laissé tel quel, rien n'est dupliqué si le script est relancé.
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO skill_themes (id, name, slug, icon, position) VALUES
(1, 'Informatique & réseaux', 'informatique-reseaux', 'network', 1),
(2, 'Développement',          'developpement',        'code',    2),
(3, 'Électronique',           'electronique',         'chip',    3),
(4, 'Langues',                'langues',              'globe',   4),
(5, 'Savoir-être',            'savoir-etre',          'user',    5),
(6, 'Autres atouts',          'autres-atouts',        'star',    6);

-- -----------------------------------------------------------------------------
--  3. Colonne skill_categories.theme_id
-- -----------------------------------------------------------------------------

-- 3a. Ajout de la colonne, d'abord facultative le temps de la remplir.
SET @pf_sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skill_categories' AND COLUMN_NAME = 'theme_id') = 0,
    'ALTER TABLE skill_categories ADD COLUMN theme_id INT UNSIGNED NULL AFTER id',
    'DO 0');
PREPARE pf_stmt FROM @pf_sql;
EXECUTE pf_stmt;
DEALLOCATE PREPARE pf_stmt;

-- 3b. Rattachement des catégories existantes : « Scripting / Lua » dans
--     « Développement », toutes les autres dans « Informatique & réseaux ».
--     Seules les catégories encore sans thème sont touchées.
UPDATE skill_categories c
SET c.theme_id = COALESCE(
        (SELECT t.id FROM skill_themes t
          WHERE t.slug = IF(c.slug = 'scripting-lua' OR c.name = 'Scripting / Lua', 'developpement', 'informatique-reseaux')),
        (SELECT MIN(t2.id) FROM skill_themes t2))
WHERE c.theme_id IS NULL;

-- 3c. La colonne devient obligatoire (si elle ne l'est pas déjà).
SET @pf_sql := IF(
    (SELECT IS_NULLABLE FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skill_categories' AND COLUMN_NAME = 'theme_id') = 'YES',
    'ALTER TABLE skill_categories MODIFY theme_id INT UNSIGNED NOT NULL',
    'DO 0');
PREPARE pf_stmt FROM @pf_sql;
EXECUTE pf_stmt;
DEALLOCATE PREPARE pf_stmt;

-- 3d. Index (thème, ordre).
SET @pf_sql := IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skill_categories' AND INDEX_NAME = 'idx_skill_categories_theme') = 0,
    'ALTER TABLE skill_categories ADD KEY idx_skill_categories_theme (theme_id, position)',
    'DO 0');
PREPARE pf_stmt FROM @pf_sql;
EXECUTE pf_stmt;
DEALLOCATE PREPARE pf_stmt;

-- 3e. Clé étrangère : un thème qui contient des sous-catégories ne peut pas
--     être supprimé (ON DELETE RESTRICT).
SET @pf_sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'skill_categories'
        AND CONSTRAINT_NAME = 'fk_skill_categories_theme' AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0,
    'ALTER TABLE skill_categories ADD CONSTRAINT fk_skill_categories_theme FOREIGN KEY (theme_id) REFERENCES skill_themes (id) ON DELETE RESTRICT',
    'DO 0');
PREPARE pf_stmt FROM @pf_sql;
EXECUTE pf_stmt;
DEALLOCATE PREPARE pf_stmt;

-- -----------------------------------------------------------------------------
--  4. Compétences : niveau facultatif et libellé du niveau
-- -----------------------------------------------------------------------------

-- 4a. skills.level accepte NULL (compétence sans barre de niveau).
SET @pf_sql := IF(
    (SELECT IS_NULLABLE FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skills' AND COLUMN_NAME = 'level') = 'NO',
    'ALTER TABLE skills MODIFY level TINYINT UNSIGNED NULL COMMENT ''1 à 5, NULL = sans barre de niveau''',
    'DO 0');
PREPARE pf_stmt FROM @pf_sql;
EXECUTE pf_stmt;
DEALLOCATE PREPARE pf_stmt;

-- 4b. Nouvelle colonne skills.level_label (ex. « A2 », « Langue maternelle »).
SET @pf_sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'skills' AND COLUMN_NAME = 'level_label') = 0,
    'ALTER TABLE skills ADD COLUMN level_label VARCHAR(40) NULL COMMENT ''libellé personnalisé du niveau (A2, Langue maternelle…)'' AFTER level',
    'DO 0');
PREPARE pf_stmt FROM @pf_sql;
EXECUTE pf_stmt;
DEALLOCATE PREPARE pf_stmt;

-- 4c. Contrainte du niveau réécrite explicitement : NULL ou 1 à 5.
--     L'ancienne contrainte (si elle existe) est retirée puis recréée.
SET @pf_sql := IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'skills' AND CONSTRAINT_NAME = 'chk_skills_level') > 0,
    'ALTER TABLE skills DROP CONSTRAINT chk_skills_level',
    'DO 0');
PREPARE pf_stmt FROM @pf_sql;
EXECUTE pf_stmt;
DEALLOCATE PREPARE pf_stmt;

ALTER TABLE skills ADD CONSTRAINT chk_skills_level CHECK (level IS NULL OR level BETWEEN 1 AND 5);

SET @pf_sql := NULL;

-- -----------------------------------------------------------------------------
--  Vérification : chaque thème et son nombre de sous-catégories.
-- -----------------------------------------------------------------------------
SELECT t.position, t.name AS theme, COUNT(c.id) AS sous_categories
FROM skill_themes t
LEFT JOIN skill_categories c ON c.theme_id = t.id
GROUP BY t.id, t.position, t.name
ORDER BY t.position, t.id;
