# Migrations de la base de données

Une **migration** est un petit fichier SQL qui met à jour la structure d'une
base **déjà installée**, sans effacer son contenu. Les fichiers sont rangés
par date dans `database/migrations/` (dossier à ne **pas** envoyer dans
`public_html`).

## Quand l'utiliser ?

| Situation | Que faire ? |
|---|---|
| Nouvelle installation (base vide) | Importer `database.sql` seulement : il contient déjà toutes les migrations et les données de départ à jour. **Aucune migration à lancer.** |
| Site **déjà en ligne**, installé avec une ancienne version de `database.sql` | Lancer **chaque migration plus récente que l'installation**, dans l'ordre des dates. **Ne jamais ré-importer `database.sql`** : il supprime puis recrée toutes les tables (projets, messages et réglages seraient perdus). |

Avant toute migration, faites une **sauvegarde** : phpMyAdmin > votre base >
onglet **Exporter** > « Rapide », format SQL > **Exporter**. Gardez le fichier
téléchargé jusqu'à ce que le site fonctionne.

## Comment l'appliquer (phpMyAdmin de Hostinger)

1. hPanel > **Bases de données** > **phpMyAdmin** > **Entrer phpMyAdmin**.
2. Cliquez sur votre base dans la colonne de gauche.
3. Au choix :
   - onglet **Importer** > « Choisir un fichier » > le fichier `.sql` de la
     migration > **Importer** (en bas de page) ;
   - ou onglet **SQL** > collez tout le contenu du fichier > **Exécuter**.
4. phpMyAdmin doit afficher des lignes vertes (« … requêtes exécutées ») et
   aucune erreur rouge. La dernière requête affiche un petit tableau de
   contrôle.
5. Ouvrez le site et l'administration pour vérifier.

Les migrations de ce projet sont **relançables** : si l'import a été
interrompu, ou en cas de doute, relancez le même fichier. Les étapes déjà
faites sont simplement ignorées.

## Liste des migrations

### `2026-11-03-themes-competences.sql` : thèmes de compétences

Compétences réorganisées en **thèmes > sous-catégories > compétences**.

- Crée la table `skill_themes` et les 6 thèmes : Informatique & réseaux,
  Développement, Électronique, Langues, Savoir-être, Autres atouts.
- Ajoute `skill_categories.theme_id` : les catégories existantes (désormais
  « sous-catégories ») sont rangées dans **Informatique & réseaux**, sauf
  **Scripting / Lua** qui va dans **Développement**.
- `skills.level` devient facultatif (vide = compétence affichée sans barre de
  niveau) et `skills.level_label` est ajoutée (libellé affiché à la place du
  libellé standard, ex. « A2 » ou « Langue maternelle »).
- **Rien n'est supprimé ni modifié** dans vos compétences : noms, niveaux et
  descriptions restent tels quels.

Après la migration, les thèmes Électronique, Langues, Savoir-être et Autres
atouts sont **vides** et n'apparaissent donc pas encore sur la page publique.
Complétez depuis l'administration (Compétences > Sous-catégories, puis
Compétences) en reprenant les niveaux d'Alexandre listés dans les données de
départ de `database.sql` (section « Compétences »).

Compatibilité : MySQL 8.0.19 ou plus récent, MariaDB 10.4 ou plus récent
(Hostinger utilise l'un ou l'autre). Aucune procédure stockée : les étapes
conditionnelles passent par `PREPARE` / `EXECUTE`, acceptés par phpMyAdmin.
