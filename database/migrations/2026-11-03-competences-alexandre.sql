-- =============================================================================
--  Complément FACULTATIF du 3 novembre 2026 : compétences confirmées par Alexandre
-- =============================================================================
--  À lancer APRÈS 2026-11-03-themes-competences.sql, uniquement sur un site
--  déjà en ligne dont la base a été créée avec l'ancien database.sql.
--  Pour une nouvelle installation, importez database.sql : il contient déjà
--  ces compétences (ce script n'y changerait rien).
--
--  Mode d'emploi : voir docs/MIGRATIONS.md (phpMyAdmin > SQL ou Importer).
--
--  Ce que fait le script (sans rien supprimer) :
--    1. corrige les 9 niveaux confirmés par Alexandre, SEULEMENT si la
--       compétence a encore son niveau de départ (ancien database.sql) :
--       un niveau modifié depuis l'administration n'est jamais écrasé ;
--    2. renomme « Windows Server » en « Windows Server / Active Directory » et
--       réécrit 2 descriptions de cybersécurité (« Premières notions : … »),
--       là encore seulement si elles n'ont pas été modifiées ;
--    3. crée les sous-catégories manquantes : Scripting / Web, Autres langages,
--       Électronique, Langues, Savoir-être, Autres atouts ;
--    4. déplace Python de « Scripting / Lua » vers « Autres langages » ;
--    5. ajoute les 21 compétences manquantes (GitHub, HTML, CSS, langues,
--       savoir-être…). Une compétence qui existe déjà sous le même nom, dans
--       n'importe quelle sous-catégorie, n'est pas ajoutée une 2e fois.
--
--  Recherche des sous-catégories par slug OU par nom (une sous-catégorie
--  créée à la main avec le même nom est réutilisée). Les nouvelles lignes
--  sont ajoutées à la fin de leur thème ou de leur sous-catégorie ; les
--  positions existantes ne sont pas renumérotées (l'ordre affiché est le même).
--
--  Relançable sans risque juste après la migration : chaque étape vérifie
--  d'abord si elle a déjà été faite. Les variables (@pf_…) ne contiennent que
--  des nombres (identifiants, positions, compteurs) : les textes sont toujours
--  comparés à des valeurs écrites dans le script (pas de conflit de collation).
--
--  Compatible MySQL 8.0.19+ et MariaDB 10.4+ (Hostinger).
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
--  0. Garde-fou : la migration 2026-11-03-themes-competences.sql doit avoir
--     été lancée avant. Sinon, phpMyAdmin s'arrête ici avec l'erreur
--     « Table '…lancer_d_abord_2026_11_03_themes_competences' doesn't exist »
--     et rien n'est modifié.
-- -----------------------------------------------------------------------------
SET @pf_sql := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND ((TABLE_NAME = 'skill_categories' AND COLUMN_NAME = 'theme_id')
          OR (TABLE_NAME = 'skills' AND COLUMN_NAME = 'level_label'))) = 2,
    'DO 0',
    'SELECT * FROM lancer_d_abord_2026_11_03_themes_competences');
PREPARE pf_stmt FROM @pf_sql;
EXECUTE pf_stmt;
DEALLOCATE PREPARE pf_stmt;
SET @pf_sql := NULL;

-- -----------------------------------------------------------------------------
--  1. Niveaux confirmés : ancien niveau de départ → niveau confirmé.
-- -----------------------------------------------------------------------------
UPDATE skills SET level = 2 WHERE name = 'Adressage IPv4 et sous-réseaux' AND level = 3;
UPDATE skills SET level = 3 WHERE name = 'Windows (poste de travail)'     AND level = 4;
UPDATE skills SET level = 3 WHERE name = 'Linux'                          AND level = 2;
UPDATE skills SET level = 3 WHERE name = 'Virtualisation'                 AND level = 2;
UPDATE skills SET level = 2 WHERE name = 'Validation côté serveur'        AND level = 3;
UPDATE skills SET level = 1 WHERE name = 'Menaces et attaques courantes'  AND level = 2;
UPDATE skills SET level = 1 WHERE name = 'Sécurisation d''un poste'       AND level = 2;
UPDATE skills SET level = 2 WHERE name = 'Cisco Packet Tracer'            AND level = 3;
UPDATE skills SET level = 3 WHERE name = 'Git'                            AND level = 2;

-- -----------------------------------------------------------------------------
--  2. Nom et descriptions mis à jour (seulement s'ils sont encore d'origine).
-- -----------------------------------------------------------------------------
SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Windows Server / Active Directory');
UPDATE skills SET name = 'Windows Server / Active Directory'
WHERE name = 'Windows Server' AND @pf_n = 0;

UPDATE skills
SET description = 'Premières notions : hameçonnage, logiciels malveillants et attaques réseau, pour mieux s''en protéger.'
WHERE name = 'Menaces et attaques courantes'
  AND description = 'Hameçonnage, logiciels malveillants, attaques réseau : les comprendre pour s''en protéger.';

UPDATE skills
SET description = 'Premières notions : comptes utilisateurs, pare-feu, mises à jour et antivirus.'
WHERE name = 'Sécurisation d''un poste'
  AND description = 'Comptes utilisateurs, pare-feu, mises à jour et antivirus.';

-- -----------------------------------------------------------------------------
--  3. Sous-catégories manquantes (ajoutées à la fin de leur thème).
--     Thème trouvé par slug, sinon par nom ; rien n'est créé si le thème
--     n'existe plus.
-- -----------------------------------------------------------------------------

-- Développement > Scripting / Web
SET @pf_theme := (SELECT id FROM skill_themes WHERE slug = 'developpement' OR name = 'Développement'
                   ORDER BY slug = 'developpement' DESC, id LIMIT 1);
SET @pf_n   := (SELECT COUNT(*) FROM skill_categories WHERE slug = 'scripting-web' OR name = 'Scripting / Web');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skill_categories WHERE theme_id = @pf_theme);
INSERT INTO skill_categories (theme_id, name, slug, icon, position)
SELECT @pf_theme, 'Scripting / Web', 'scripting-web', 'code', @pf_pos FROM DUAL
WHERE @pf_theme IS NOT NULL AND @pf_n = 0;

-- Développement > Autres langages
SET @pf_n   := (SELECT COUNT(*) FROM skill_categories WHERE slug = 'autres-langages' OR name = 'Autres langages');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skill_categories WHERE theme_id = @pf_theme);
INSERT INTO skill_categories (theme_id, name, slug, icon, position)
SELECT @pf_theme, 'Autres langages', 'autres-langages', 'terminal', @pf_pos FROM DUAL
WHERE @pf_theme IS NOT NULL AND @pf_n = 0;

-- Électronique > Électronique (même nom que le thème : titre non répété)
SET @pf_theme := (SELECT id FROM skill_themes WHERE slug = 'electronique' OR name = 'Électronique'
                   ORDER BY slug = 'electronique' DESC, id LIMIT 1);
SET @pf_n   := (SELECT COUNT(*) FROM skill_categories WHERE slug = 'electronique' OR name = 'Électronique');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skill_categories WHERE theme_id = @pf_theme);
INSERT INTO skill_categories (theme_id, name, slug, icon, position)
SELECT @pf_theme, 'Électronique', 'electronique', 'chip', @pf_pos FROM DUAL
WHERE @pf_theme IS NOT NULL AND @pf_n = 0;

-- Langues > Langues
SET @pf_theme := (SELECT id FROM skill_themes WHERE slug = 'langues' OR name = 'Langues'
                   ORDER BY slug = 'langues' DESC, id LIMIT 1);
SET @pf_n   := (SELECT COUNT(*) FROM skill_categories WHERE slug = 'langues' OR name = 'Langues');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skill_categories WHERE theme_id = @pf_theme);
INSERT INTO skill_categories (theme_id, name, slug, icon, position)
SELECT @pf_theme, 'Langues', 'langues', 'globe', @pf_pos FROM DUAL
WHERE @pf_theme IS NOT NULL AND @pf_n = 0;

-- Savoir-être > Savoir-être
SET @pf_theme := (SELECT id FROM skill_themes WHERE slug = 'savoir-etre' OR name = 'Savoir-être'
                   ORDER BY slug = 'savoir-etre' DESC, id LIMIT 1);
SET @pf_n   := (SELECT COUNT(*) FROM skill_categories WHERE slug = 'savoir-etre' OR name = 'Savoir-être');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skill_categories WHERE theme_id = @pf_theme);
INSERT INTO skill_categories (theme_id, name, slug, icon, position)
SELECT @pf_theme, 'Savoir-être', 'savoir-etre', 'user', @pf_pos FROM DUAL
WHERE @pf_theme IS NOT NULL AND @pf_n = 0;

-- Autres atouts > Autres atouts
SET @pf_theme := (SELECT id FROM skill_themes WHERE slug = 'autres-atouts' OR name = 'Autres atouts'
                   ORDER BY slug = 'autres-atouts' DESC, id LIMIT 1);
SET @pf_n   := (SELECT COUNT(*) FROM skill_categories WHERE slug = 'autres-atouts' OR name = 'Autres atouts');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skill_categories WHERE theme_id = @pf_theme);
INSERT INTO skill_categories (theme_id, name, slug, icon, position)
SELECT @pf_theme, 'Autres atouts', 'autres-atouts', 'star', @pf_pos FROM DUAL
WHERE @pf_theme IS NOT NULL AND @pf_n = 0;

-- -----------------------------------------------------------------------------
--  4. Python : de « Scripting / Lua » vers « Autres langages » (seulement s'il
--     est encore dans Scripting / Lua), à la fin de sa nouvelle sous-catégorie.
-- -----------------------------------------------------------------------------
SET @pf_from := (SELECT id FROM skill_categories WHERE slug = 'scripting-lua' OR name = 'Scripting / Lua'
                  ORDER BY slug = 'scripting-lua' DESC, id LIMIT 1);
SET @pf_cat  := (SELECT id FROM skill_categories WHERE slug = 'autres-langages' OR name = 'Autres langages'
                  ORDER BY slug = 'autres-langages' DESC, id LIMIT 1);
SET @pf_pos  := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
UPDATE skills SET category_id = @pf_cat, position = @pf_pos
WHERE name = 'Python' AND category_id = @pf_from AND @pf_cat IS NOT NULL;

-- -----------------------------------------------------------------------------
--  5. Compétences manquantes. Pour chacune : position = fin de la
--     sous-catégorie, ajout seulement si aucune compétence ne porte déjà ce nom.
--     Échelle : 1 Notions · 2 Bases · 3 Intermédiaire · 4 Avancé · 5 Maîtrise ;
--     niveau NULL = sans barre (savoir-être, autres atouts).
-- -----------------------------------------------------------------------------

-- Informatique & réseaux > Outils
SET @pf_cat := (SELECT id FROM skill_categories WHERE slug = 'outils' OR name = 'Outils'
                 ORDER BY slug = 'outils' DESC, id LIMIT 1);

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'GitHub');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'GitHub', 3, NULL, 'Hébergement de dépôts en ligne : envoi du code, historique des modifications et partage.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

-- Développement > Scripting / Web
SET @pf_cat := (SELECT id FROM skill_categories WHERE slug = 'scripting-web' OR name = 'Scripting / Web'
                 ORDER BY slug = 'scripting-web' DESC, id LIMIT 1);

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'HTML');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'HTML', 3, NULL, 'Structure d''une page : titres, listes, liens, images, formulaires et balises sémantiques.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'CSS');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'CSS', 3, NULL, 'Mise en forme et mise en page : couleurs, typographie, flexbox, grilles et adaptation au mobile.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'JavaScript');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'JavaScript', 2, NULL, 'Bases du langage : variables, fonctions, événements et modification de la page.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'PHP');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'PHP', 2, NULL, 'Bases côté serveur : pages dynamiques et traitement de formulaires.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'SQL et bases de données (MySQL)');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'SQL et bases de données (MySQL)', 2, NULL, 'Tables et relations, requêtes simples pour lire, ajouter et modifier des données.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

-- Développement > Autres langages (Python y a été déplacé à l'étape 4)
SET @pf_cat := (SELECT id FROM skill_categories WHERE slug = 'autres-langages' OR name = 'Autres langages'
                 ORDER BY slug = 'autres-langages' DESC, id LIMIT 1);

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'C / C++');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'C / C++', 2, NULL, 'Bases : variables, conditions, boucles, fonctions et compilation de petits programmes.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Bash / ligne de commande');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'Bash / ligne de commande', 2, NULL, 'Commandes courantes du terminal et petits scripts pour enchaîner des tâches simples.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Java');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'Java', 2, NULL, 'Bases : syntaxe, classes et objets, petits programmes.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

-- Électronique
SET @pf_cat := (SELECT id FROM skill_categories WHERE slug = 'electronique' OR name = 'Électronique'
                 ORDER BY slug = 'electronique' DESC, id LIMIT 1);

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Mesures (multimètre, oscilloscope)');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'Mesures (multimètre, oscilloscope)', 1, NULL, 'Premières notions : mesure de tensions et de courants, observation d''un signal.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Microcontrôleurs (Arduino, ESP32…)');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'Microcontrôleurs (Arduino, ESP32…)', 2, NULL, 'Bases de la programmation d''une carte : entrées, sorties, capteurs et actionneurs simples.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Soudure');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'Soudure', 2, NULL, 'Bases de la soudure à l''étain de composants sur une carte électronique.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

-- Langues (libellé CECRL à la place du libellé standard)
SET @pf_cat := (SELECT id FROM skill_categories WHERE slug = 'langues' OR name = 'Langues'
                 ORDER BY slug = 'langues' DESC, id LIMIT 1);

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Français');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'Français', 5, 'Langue maternelle', NULL, @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Anglais');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'Anglais', 2, 'A2', NULL, @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Espagnol');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'Espagnol', 1, 'A1', NULL, @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

-- Savoir-être (sans niveau : la description donne un exemple concret)
SET @pf_cat := (SELECT id FROM skill_categories WHERE slug = 'savoir-etre' OR name = 'Savoir-être'
                 ORDER BY slug = 'savoir-etre' DESC, id LIMIT 1);

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Travail en équipe');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'Travail en équipe', NULL, NULL, 'J''ai mené des projets de création de jeux à plusieurs.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Autonomie');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'Autonomie', NULL, NULL, NULL, @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Relation avec les utilisateurs');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'Relation avec les utilisateurs', NULL, NULL, 'En stage, j''ai accueilli et aidé les utilisateurs, pour des problèmes du quotidien comme pour des problèmes plus complexes.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Rigueur et organisation');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'Rigueur et organisation', NULL, NULL, 'Je trie et je respecte un ordre et une méthode de travail.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Curiosité et autoformation');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'Curiosité et autoformation', NULL, NULL, 'J''ai toujours envie de découvrir les nouvelles technologies.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

-- Autres atouts (sans niveau)
SET @pf_cat := (SELECT id FROM skill_categories WHERE slug = 'autres-atouts' OR name = 'Autres atouts'
                 ORDER BY slug = 'autres-atouts' DESC, id LIMIT 1);

SET @pf_n := (SELECT COUNT(*) FROM skills WHERE name = 'Formation BIA (brevet d''initiation aéronautique)');
SET @pf_pos := (SELECT COALESCE(MAX(position), 0) + 1 FROM skills WHERE category_id = @pf_cat);
INSERT INTO skills (category_id, name, level, level_label, description, position)
SELECT @pf_cat, 'Formation BIA (brevet d''initiation aéronautique)', NULL, NULL, 'Formation suivie, sans passage du diplôme.', @pf_pos
FROM DUAL WHERE @pf_cat IS NOT NULL AND @pf_n = 0;

SET @pf_theme := NULL;
SET @pf_from := NULL;
SET @pf_cat := NULL;
SET @pf_pos := NULL;
SET @pf_n := NULL;

-- -----------------------------------------------------------------------------
--  Vérification : chaque sous-catégorie et son nombre de compétences
--  (43 au total avec les données de départ).
-- -----------------------------------------------------------------------------
SELECT t.name AS theme, c.name AS sous_categorie, COUNT(s.id) AS competences
FROM skill_themes t
JOIN skill_categories c ON c.theme_id = t.id
LEFT JOIN skills s ON s.category_id = c.id
GROUP BY t.id, t.position, t.name, c.id, c.position, c.name
ORDER BY t.position, t.id, c.position, c.id;
