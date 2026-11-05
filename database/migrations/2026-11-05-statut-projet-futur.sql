-- =============================================================================
--  Migration : statut « Projet futur » pour les projets
-- =============================================================================
--  Pour un site déjà installé avec une version antérieure de database.sql
--  (phpMyAdmin > onglet SQL, ou Importer). Inutile après une installation
--  neuve. Peut être relancée sans risque : elle redonne la même définition.
--  Aucune donnée n'est modifiée. Compatible MySQL 8 et MariaDB 10.4+.
-- =============================================================================

ALTER TABLE projects
    MODIFY COLUMN status ENUM('done','in_progress','planned') NOT NULL DEFAULT 'in_progress'
        COMMENT 'planned = projet futur';
