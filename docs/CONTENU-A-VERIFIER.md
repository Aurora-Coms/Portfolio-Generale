# Contenu à vérifier

Les données de départ (fin de `database.sql`) ont été rédigées à partir des
informations fournies par Alexandre. Tout ce qui figure ci-dessous a été
**supposé, reformulé ou rédigé sans information précise** : à relire et à
corriger depuis l'administration (`/admin`) avant de diffuser le site.

Légende : **[supposé]** = information inventée de façon plausible ;
**[à compléter]** = champ volontairement laissé en trame ; **[vide]** = à
renseigner obligatoirement.

---

## 1. Réglages (Administration > Réglages)

| Clé | Valeur mise | À vérifier |
|---|---|---|
| `linkedin_url` | renseigné | https://www.linkedin.com/in/alexandre-derbez-9b32b4253/ (fourni par Alexandre) |
| `github_url` | renseigné | https://github.com/ADERBEZ (fourni par Alexandre) |
| `contact_email` | vide | **[vide]** adresse qui reçoit les messages du formulaire de contact |
| `hero_tagline` | « En 1re année au Campus St Jean-Baptiste de La Salle à Avignon… » | Accroche rédigée par Claude : la relire |
| `meta_description` | « Portfolio d'Alexandre Derbez, étudiant en BTS CIEL option IR à Avignon : réseaux, cybersécurité et jeux Roblox en Lua. Recherche de stage en 2026. » (146 caractères) | Texte affiché par Google sous le titre du site |
| `about_text` | 5 paragraphes à la 1re personne | Voir détail ci-dessous |
| `legal_host` | Hostinger International Ltd., 61 Lordou Vironos Street, 6023 Larnaca, Chypre | Vérifier l'adresse sur les mentions légales actuelles de Hostinger |
| `cv_updated_at` | vide | Rempli automatiquement à l'envoi du CV en PDF |

### Texte « À propos » (`about_text`)
- **[supposé]** « J'y ai appris à diagnostiquer et dépanner des postes, à préparer du matériel pour les utilisateurs et à travailler au sein d'une équipe technique » : missions types, pas les missions réelles.
- **[supposé]** Le texte développe le sigle RISC (« Réseaux Informatiques et Systèmes Communicants ») et le sigle CIEL : vérifier qu'ils sont exacts.
- **[supposé]** « Le BTS me permet d'approfondir ces sujets en cours et en travaux pratiques ».
- **[supposé]** Ce que le développement Roblox apprend (logique client / serveur, sauvegarde des données, interfaces, vérification côté serveur) : à confirmer selon ce que tu as réellement codé.
- **[supposé]** La phrase de conclusion (« N'hésitez pas à me contacter… »).

---

## 2. Catégories et tags (Administration > Catégories, Tags)

- **[supposé]** Les descriptions des 8 catégories (ex. « Mécaniques complètes : progression, sauvegarde, classements, interfaces. »).
- La catégorie de jeux « Autres » et les catégories techniques « Réseau », « Cybersécurité », « TP & CTF » n'ont encore aucun projet publié : les remplir ou les supprimer.
- **[supposé]** Tags créés sans être liés à un projet : Physique, Raycast, Réseau, Linux, Windows, Cisco, Packet Tracer, Wireshark, Git. Les supprimer s'ils ne servent pas.
- Tags C et Assembleur **non créés** : le langage de « Création d'un réel OS » n'est pas connu. Les ajouter (avec Rust ou autre) une fois le projet décrit.
- **[supposé]** Liaisons projets ↔ tags :
  - TARDIS : Lua, Luau, Roblox Studio, RemoteEvents, ModuleScripts, TweenService, Interface (UI)
  - Portail du TVA : Lua, Luau, Roblox Studio, RemoteEvents, TweenService
  - Cardverse : Lua, Luau, Roblox Studio, DataStore, RemoteEvents, ModuleScripts, Interface (UI)
  - OS Fondationnaire : Lua, Roblox Studio, OS
  - Création d'un réel OS : OS

---

## 3. Projets de jeux (Administration > Projets)

Pour tous les projets : **statut « en cours »** [supposé], **date vide**
(inconnue), **aucune vidéo YouTube, aucun lien externe, aucune image**.
Ajouter des captures d'écran depuis la page Images de chaque projet.

Seuls les faits suivants viennent d'Alexandre : le nom des projets, leur
thème (Doctor Who, Loki / TVA, jeu de cartes avec factions, XP, saisons et
classements), le fait qu'ils sont entièrement scriptés en Lua, objets
compris. **Toute la description technique (« comment ») a été rédigée par
Claude** à partir des mécanismes Roblox habituellement nécessaires. Il faut
la confronter à ton code réel et supprimer ce qui ne correspond pas.

### TARDIS (publié, mis en avant)
- **[supposé]** Intérieur = salle séparée hors de la carte, avec téléportation par `CFrame` à l'entrée et à la sortie.
- **[supposé]** Commandes de la console via `ProximityPrompt` ou `ClickDetector`.
- **[supposé]** `RemoteEvent` avec vérifications serveur (joueur dans la salle, machine pas déjà en vol).
- **[supposé]** Machine à états dans un `ModuleScript` (au sol, dématérialisation, vol, rematérialisation).
- **[supposé]** Animation de la dématérialisation par `TweenService` sur la transparence, sons et lumières, effets joués par des `LocalScript`.
- **[supposé]** Interface `ScreenGui` affichant l'état et la destination.
- **[supposé]** Choix d'une destination sur la console.
- **[supposé]** Utilisation par plusieurs joueurs en même temps.
- **[supposé]** Toute la partie « Ce que j'ai appris », dont « Si je devais recommencer, je commenterais davantage mon code… ».

### Portail du TVA (publié, mis en avant)
- **[supposé]** Ouverture en trois étapes (point lumineux, agrandissement, stabilisation) avec `TweenService`, `ParticleEmitter`, `PointLight` et sons.
- **[supposé]** Ouverture déclenchée depuis « un objet ou une commande prévus pour cela » : préciser lequel.
- **[supposé]** Vérifications serveur : droit d'ouvrir, proximité, délai minimum entre deux ouvertures, un seul portail ouvert à la fois.
- **[supposé]** Zone de détection dans l'ouverture, téléportation par `CFrame` du `HumanoidRootPart` avec décalage entre joueurs, fondu à l'écran côté client.
- **[supposé]** Fermeture automatique après un temps défini.
- **[supposé]** Destination : le texte parle d'un « point d'arrivée » sans préciser où mène le portail. À compléter.
- **[supposé]** Partie « Ce que j'ai appris » (synchronisation, cas limites, performances sur téléphone).

### Cardverse (publié, mis en avant, terminé)
- **[supposé]** Cartes et factions décrites dans des `ModuleScript` sous forme de tables (nom, faction, rareté, caractéristiques). La notion de **rareté** n'a pas été donnée par Alexandre.
- **[supposé]** Calcul de l'XP uniquement par le serveur.
- **[supposé]** Sauvegarde avec `DataStoreService` (à l'arrivée, au départ, à intervalles réguliers), appels protégés par `pcall` avec nouvelles tentatives.
- **[supposé]** Classements avec `OrderedDataStore`, une clé par saison, historique conservé.
- **[supposé]** `RemoteEvent` et `RemoteFunction` pour consulter la collection, jouer une carte, réclamer une récompense.
- **[supposé]** Interface `ScreenGui` + `TweenService`, mise en page `UIGridLayout`, `UIListLayout`, `UIAspectRatioConstraint`, adaptée au téléphone.
- **[supposé]** Mode de jeu : le texte ne dit pas comment se déroule une partie (combat de cartes, ouverture de paquets, échanges…). À préciser, ainsi que le nom des factions.
- **[supposé]** Partie « Ce que j'ai appris », dont « Cardverse est aussi le projet sur lequel j'ai le plus appris à corriger et améliorer un code existant ».

### OS Fondationnaire (BROUILLON, mis en avant)
- **[à compléter]** Résumé : « Description à compléter depuis l'administration. »
- **[à compléter]** Contexte, travail réalisé et apprentissages : trames de questions commençant par « À compléter : ». Remplacer chaque question par la réponse, puis publier.
- **[supposé]** Rangé dans Roblox + Systèmes de jeu.

### Projet « Turret »
- Retiré à la demande d'Alexandre : il n'existe pas dans les données de départ.

---

## 4. Projets techniques

### Réalisation d'un système d'exploitation aidé de l'IA (PUBLIÉ, mis en avant, statut « Projet futur »)
- **[fourni]** Titre et nature du projet donnés par Alexandre : projet futur, réalisé avec l'aide de l'IA.
- **[rédigé]** Résumé et contexte rédigés à partir de cette phrase (motivation, rôle de l'IA comme assistant) : à relire. Les sections « Ce que j'ai fait » et « Ce que j'ai appris » sont vides (masquées) tant que le projet n'a pas commencé.
- **[supposé]** Rangé dans la catégorie Systèmes, tag « OS ».

### Images des projets
- **[fourni]** TARDIS : 3 captures (intérieur en couverture, extérieur, portes ouvertes). Portail du TVA : 1 capture. Textes alternatifs rédigés d'après les images : à relire.
- **[rédigé]** Cardverse et « Réalisation d'un système d'exploitation aidé de l'IA » : **illustrations** dessinées pour le site (ce ne sont pas des captures du jeu ni de l'OS). Les valeurs affichées (coûts, points, classement, niveau 12) sont décoratives. À remplacer par de vraies captures quand elles existent (Administration > Projets > Images).

---

## 5. Parcours (Administration > Parcours)

### BTS CIEL option IR
- **[supposé]** Date de début : 1er septembre 2025 (affichée « 2025 »).
- **[supposé]** Matières citées : réseaux (adressage, commutation, routage), cybersécurité, administration Windows et Linux, électronique, développement.

### Stages (3 entrées, novembre 2026)
Sources : CV de décembre 2023 et de février 2024, rapport de stage La Salle de juin 2023 (Google Drive d'Alexandre). Les CV de 2026 n'étaient lisibles que comme pièces jointes Gmail.
- **[fourni]** CNRS · Laboratoire de Neurosciences Cognitives (LNC), Marseille, du 27/02/2023 au 17/03/2023 : câblage, calibrage imprimante-ordinateur, mise à jour de logiciels, devis pour l'assemblage d'un ordinateur.
- **[supposé]** CNRS : phrase sur le programme MATLAB couplé à un logiciel qui lit l'électroencéphalogramme (idée suggérée pour la lettre de motivation de mars 2026) : à confirmer.
- **[fourni]** La Salle Avignon, service technique et sécurité, juin 2023 (dates du CV : 05/06 au 23/06/2023) : câbles réseau et bornes Wi-Fi, FOG, réinstallation et domaine, souris, encre, coques d'iPad, dépannage du foyer, mises à jour.
- **[supposé]** « 2de Bac Pro » pour ces deux stages (le CV de 2023 indique « 2023-2024 : 1ère pro SN »).
- **[à compléter]** « 4 autres stages » (La Salle Avignon et Cité scolaire Frédéric Mistral, 2024 → 2025) : missions **types** seulement. Le CV de février 2024 cite un stage du 29/01/2024 au 23/02/2024 sans lieu. Envoyer le CV 2026 (PDF dans le Drive ou dans la conversation) pour créer une entrée détaillée par stage.

### Bac Pro SN option RISC
- **[supposé]** Période : 1er septembre 2022 au 4 juillet 2025 (affichée « 2022 → 2025 »).
- **[supposé]** Description : « Formation centrée sur l'installation, la mise en service et la maintenance des équipements informatiques et réseaux. »

---

## 6. Compétences (Administration > Compétences)

Organisation : **thèmes > sous-catégories > compétences** (Administration >
Thèmes de compétences, Sous-catégories de compétences, Compétences).

- [x] **Niveaux CONFIRMÉS par Alexandre** (novembre 2026) : plus rien à
  vérifier de ce côté. Échelle : 1 Notions · 2 Bases · 3 Intermédiaire ·
  4 Avancé · 5 Maîtrise. Langues : libellé CECRL (A1, A2…) ou « Langue
  maternelle » à la place du libellé standard.
- [x] Savoir-être et autres atouts : sans niveau (pas de barre), comme demandé.
  « Persévérance », permis B et secourisme non retenus.
- [ ] **Seules les descriptions restent à relire** (une phrase par compétence,
  rédigée par Claude sauf mention contraire). Les corriger ou les vider
  depuis l'administration si elles ne correspondent pas à ce que tu fais
  réellement.

| Thème · sous-catégorie | Compétences dont la description est à relire | Remarque |
|---|---|---|
| Informatique & réseaux · Réseau | les 5 | descriptions d'origine conservées |
| Informatique & réseaux · Systèmes | les 5 | « Windows Server » renommée « Windows Server / Active Directory » |
| Informatique & réseaux · Cybersécurité | Menaces et attaques courantes, Sécurisation d'un poste | réécrites en « Premières notions : … » pour coller au niveau 1 |
| Informatique & réseaux · Cybersécurité | Hygiène informatique, Validation côté serveur | descriptions d'origine conservées |
| Informatique & réseaux · Outils | GitHub | **nouvelle** description, générique |
| Informatique & réseaux · Outils | Cisco Packet Tracer, Wireshark, Outils de diagnostic matériel, Git | descriptions d'origine conservées |
| Développement · Scripting / Lua | les 3 | descriptions d'origine conservées |
| Développement · Scripting / Web | HTML, CSS, JavaScript, PHP, SQL et bases de données (MySQL) | **nouvelles** descriptions, génériques (aucun projet cité) |
| Développement · Autres langages | C / C++, Bash / ligne de commande, Java | **nouvelles** descriptions, génériques |
| Développement · Autres langages | Python | description d'origine conservée (déplacée depuis Scripting / Lua) |
| Électronique | Mesures, Microcontrôleurs, Soudure | **nouvelles** descriptions, génériques |
| Savoir-être | Travail en équipe, Relation avec les utilisateurs, Rigueur et organisation, Curiosité et autoformation | tes exemples, reformulés à la 1re personne sans en changer le sens ; « Autonomie » sans exemple (à compléter si tu veux) |
| Autres atouts | Formation BIA | « Formation suivie, sans passage du diplôme. » (ton texte) |
| Langues | Français, Anglais, Espagnol | pas de description |

---

## 7. Centres d'intérêt (Administration > Centres d'intérêt)

- **[supposé]** Toutes les descriptions courtes (ex. VTT : « Sorties en pleine nature : endurance, persévérance et goût de l'effort. »).
- **[supposé]** 4e centre d'intérêt « Systèmes d'exploitation » (icône terminal), déduit des deux projets d'OS. Le supprimer si ce n'est pas un vrai centre d'intérêt.

---

## 8. Ce qui n'est PAS dans les données de départ

- Aucun compte administrateur : utiliser `public_html/setup-admin.php` (puis le supprimer).
- Aucune image de projet, aucun CV en PDF, aucun message.
- Aucun chiffre (joueurs, visites, nombre de cartes) n'a été inventé.
