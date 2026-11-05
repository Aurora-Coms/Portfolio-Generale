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

**Attention : la structure est à jour, pas le contenu.** Après cette seule
migration, la page publique affiche encore les compétences de l'ancien
`database.sql`, dont **9 niveaux qu'Alexandre a corrigés** (novembre 2026).
Il manque aussi 6 sous-catégories et 21 compétences : les thèmes
Électronique, Langues, Savoir-être et Autres atouts sont **vides** et
n'apparaissent donc pas encore sur la page publique. Deux façons de
compléter :

1. **Recommandé** : lancer ensuite le complément facultatif
   `2026-11-03-competences-alexandre.sql` (section suivante), qui fait tout
   d'un coup sans écraser vos propres modifications ;
2. ou corriger à la main dans l'administration (liste ci-dessous).

À corriger à la main (Administration > Compétences, bouton « Modifier ») si
vous ne lancez pas le complément :

| Compétence | Niveau de l'ancien `database.sql` | Niveau confirmé |
|---|---|---|
| Adressage IPv4 et sous-réseaux | 3 Intermédiaire | **2** Bases |
| Windows (poste de travail) | 4 Avancé | **3** Intermédiaire |
| Linux | 2 Bases | **3** Intermédiaire |
| Virtualisation | 2 Bases | **3** Intermédiaire |
| Validation côté serveur | 3 Intermédiaire | **2** Bases |
| Menaces et attaques courantes | 2 Bases | **1** Notions |
| Sécurisation d'un poste | 2 Bases | **1** Notions |
| Cisco Packet Tracer | 3 Intermédiaire | **2** Bases |
| Git | 2 Bases | **3** Intermédiaire |

- Renommer « Windows Server » en **« Windows Server / Active Directory »**.
- Descriptions de « Menaces et attaques courantes » et « Sécurisation d'un
  poste » : reprendre les textes « Premières notions : … » de `database.sql`.
- Créer dans **Développement** les sous-catégories **Scripting / Web** et
  **Autres langages**, puis y déplacer **Python** (il est encore dans
  Scripting / Lua).
- Créer dans **Électronique**, **Langues**, **Savoir-être** et **Autres
  atouts** une sous-catégorie du même nom que le thème (son titre n'est alors
  pas répété sur la page publique).
- Ajouter **GitHub** dans Outils, puis les autres compétences manquantes,
  avec les niveaux, libellés (« A2 », « Langue maternelle »…) et descriptions
  des données de départ de `database.sql` (section « Compétences »).

Compatibilité : MySQL 8.0.19 ou plus récent, MariaDB 10.4 ou plus récent
(Hostinger utilise l'un ou l'autre). Aucune procédure stockée : les étapes
conditionnelles passent par `PREPARE` / `EXECUTE`, acceptés par phpMyAdmin.

### `2026-11-03-competences-alexandre.sql` : compétences confirmées (facultatif)

À lancer **après** `2026-11-03-themes-competences.sql` (même date : lancez
d'abord celle-là). Lancé trop tôt, le script s'arrête tout de suite sur
l'erreur « Table '…lancer_d_abord_2026_11_03_themes_competences' doesn't
exist » et ne modifie rien : lancez la migration des thèmes, puis
recommencez.

Il met les compétences d'un site déjà en ligne au même niveau que les
données de départ de `database.sql`, **sans rien supprimer** :

- corrige les 9 niveaux du tableau ci-dessus, **seulement** si la compétence
  a encore son niveau de départ : un niveau déjà modifié depuis
  l'administration n'est jamais écrasé ;
- renomme « Windows Server » et met à jour les 2 descriptions de
  cybersécurité, là aussi seulement si elles sont encore d'origine ;
- crée les 6 sous-catégories manquantes et y range Python ;
- ajoute les 21 compétences manquantes (GitHub, HTML, CSS, JavaScript, PHP,
  SQL, C / C++, Bash, Java, électronique, langues, savoir-être, BIA). Une
  compétence qui existe déjà sous le même nom n'est pas ajoutée une
  deuxième fois ; une sous-catégorie déjà créée à la main avec le même nom
  est réutilisée.

Vos propres sous-catégories et compétences sont conservées ; les nouvelles
s'ajoutent à la fin de leur thème ou de leur sous-catégorie. La dernière
requête affiche chaque sous-catégorie et son nombre de compétences (43 au
total si vous n'en avez ajouté ou retiré aucune).

Inutile sur une nouvelle installation : `database.sql` contient déjà tout
(lancé quand même, le script n'y change rien). À lancer de préférence une
seule fois, juste après la migration : il peut être relancé sans risque à ce
moment-là. Plus tard, il remettrait le niveau confirmé sur une compétence
volontairement remise à son ancien niveau, et ajouterait de nouveau une
compétence de la liste supprimée entre-temps.

### `2026-11-04-securite-sessions.sql` : fermeture des autres sessions

À exécuter sur un site installé avant le 4 novembre 2026
(inutile après une installation neuve). Ajoute la colonne `session_version` à la
table `admins` : changer le mot de passe, activer ou désactiver la double
authentification ferme alors les sessions ouvertes sur les autres appareils.
Aucune donnée n'est supprimée et la migration peut être relancée sans risque.
Tant qu'elle n'est pas faite, le site
fonctionne normalement, sans cette protection supplémentaire.

### `2026-11-05-statut-projet-futur.sql` : statut « Projet futur »

Pour un site installé avant le 5 novembre 2026 (inutile après une installation
neuve). Ajoute la valeur « Projet futur » au statut des projets. Aucune donnée
n'est modifiée et la migration peut être relancée sans risque.
