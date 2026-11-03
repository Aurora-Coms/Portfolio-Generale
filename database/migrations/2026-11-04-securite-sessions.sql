-- =============================================================================
--  Migration : « version de session » des comptes administrateur
-- =============================================================================
--  À exécuter UNE SEULE FOIS sur un site déjà installé avec une version
--  antérieure de database.sql (phpMyAdmin > onglet SQL, ou Importer).
--  Inutile après une installation neuve : database.sql contient déjà la colonne.
--
--  Effet : changer le mot de passe ou désactiver la double authentification
--  déconnecte désormais les autres appareils. Aucune donnée n'est supprimée.
--  Compatible MySQL 8 et MariaDB 10.4+.
-- =============================================================================

ALTER TABLE admins
    ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Incrémentée pour déconnecter les autres sessions'
        AFTER totp_last_step;
