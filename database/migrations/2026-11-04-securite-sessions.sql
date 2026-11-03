-- =============================================================================
--  Migration : « version de session » des comptes administrateur
-- =============================================================================
--  Pour un site déjà installé avec une version antérieure de database.sql
--  (phpMyAdmin > onglet SQL, ou Importer). Inutile après une installation
--  neuve : database.sql contient déjà la colonne. Peut être relancée sans
--  risque (elle ne fait rien si la colonne existe).
--
--  Effet : changer le mot de passe ou la double authentification ferme les
--  sessions ouvertes sur les autres appareils. Aucune donnée n'est supprimée.
--  Compatible MySQL 8 et MariaDB 10.4+ (MySQL 8 n'a pas « ADD COLUMN IF NOT
--  EXISTS » : la colonne est testée dans information_schema).
-- =============================================================================

SET @pf_has_session_version := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'session_version'
);
SET @pf_sql := IF(@pf_has_session_version = 0,
    'ALTER TABLE admins ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1 COMMENT ''Incrémentée pour déconnecter les autres sessions'' AFTER totp_last_step',
    'DO 0');
PREPARE pf_stmt FROM @pf_sql;
EXECUTE pf_stmt;
DEALLOCATE PREPARE pf_stmt;
