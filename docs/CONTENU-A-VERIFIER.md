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
| `linkedin_url` | vide | **[vide]** adresse du profil LinkedIn |
| `github_url` | vide | **[vide]** adresse GitHub (ou laisser vide si aucun compte) |
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
  - Portail de la TVA : Lua, Luau, Roblox Studio, RemoteEvents, TweenService
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

### Portail de la TVA (publié, mis en avant)
- **[supposé]** Ouverture en trois étapes (point lumineux, agrandissement, stabilisation) avec `TweenService`, `ParticleEmitter`, `PointLight` et sons.
- **[supposé]** Ouverture déclenchée depuis « un objet ou une commande prévus pour cela » : préciser lequel.
- **[supposé]** Vérifications serveur : droit d'ouvrir, proximité, délai minimum entre deux ouvertures, un seul portail ouvert à la fois.
- **[supposé]** Zone de détection dans l'ouverture, téléportation par `CFrame` du `HumanoidRootPart` avec décalage entre joueurs, fondu à l'écran côté client.
- **[supposé]** Fermeture automatique après un temps défini.
- **[supposé]** Destination : le texte parle d'un « point d'arrivée » sans préciser où mène le portail. À compléter.
- **[supposé]** Partie « Ce que j'ai appris » (synchronisation, cas limites, performances sur téléphone).

### Cardverse (publié, mis en avant)
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

### Création d'un réel OS (BROUILLON, mis en avant)
- **[à compléter]** Résumé, contexte, travail réalisé et apprentissages : trames « À compléter » (langage, architecture, démarrage, environnement de test, avancement).
- **[supposé]** Rangé dans la catégorie Systèmes.

---

## 5. Parcours (Administration > Parcours)

### BTS CIEL option IR
- **[supposé]** Date de début : 1er septembre 2025 (affichée « 2025 »).
- **[supposé]** Matières citées : réseaux (adressage, commutation, routage), cybersécurité, administration Windows et Linux, électronique, développement.

### 6 stages en maintenance informatique
- **[supposé]** Période : septembre 2022 à juin 2025 (affichée « 2022 → 2025 »). Les dates réelles de chaque stage ne sont pas connues.
- **[supposé]** Les six missions listées sont des **missions types** de maintenance (diagnostic, remplacement de composants, installation de Windows, préparation de postes, câblage, assistance) : garder seulement celles réellement effectuées.
- **[à compléter]** Le texte indique « Le détail des missions de chaque stage est à compléter ». Il est possible de créer une entrée par stage (lieu, dates, missions) et de supprimer l'entrée groupée.
- **[supposé]** Mention « un laboratoire du CNRS » pour le LNC de Marseille : vérifier le nom exact du laboratoire.

### Bac Pro SN option RISC
- **[supposé]** Période : 1er septembre 2022 au 4 juillet 2025 (affichée « 2022 → 2025 »).
- **[supposé]** Description : « Formation centrée sur l'installation, la mise en service et la maintenance des équipements informatiques et réseaux. »

---

## 6. Compétences (Administration > Compétences)

**Tous les niveaux sont [supposés]**, estimés pour un étudiant de 1re année
de BTS CIEL IR qui a fait 6 stages de maintenance et beaucoup de Lua.
Échelle : 1 Notions · 2 Bases · 3 Intermédiaire · 4 Avancé · 5 Maîtrise.
Toutes les descriptions ont aussi été rédigées par Claude.

| Catégorie | Compétence | Niveau | Remarque |
|---|---|---|---|
| Réseau | Adressage IPv4 et sous-réseaux | 3 | |
| Réseau | Câblage et brassage | 3 | Suppose des câbles RJ45 réalisés en Bac Pro |
| Réseau | Commutation et VLAN | 2 | |
| Réseau | Routage | 2 | « premières notions de routage dynamique » |
| Réseau | Services réseau (DHCP, DNS) | 2 | |
| Systèmes | Maintenance matériel | 4 | |
| Systèmes | Windows (poste de travail) | 4 | |
| Systèmes | Windows Server | 2 | Suppose des notions d'Active Directory |
| Systèmes | Linux | 2 | |
| Systèmes | Virtualisation | 2 | |
| Scripting / Lua | Lua / Luau | 4 | |
| Scripting / Lua | Roblox Studio | 4 | |
| Scripting / Lua | Architecture client / serveur | 3 | |
| Scripting / Lua | Python | 2 | **Non mentionné par Alexandre** : supprimer si non pratiqué |
| Cybersécurité | Hygiène informatique | 3 | |
| Cybersécurité | Validation côté serveur | 3 | |
| Cybersécurité | Menaces et attaques courantes | 2 | |
| Cybersécurité | Sécurisation d'un poste | 2 | |
| Outils | Cisco Packet Tracer | 3 | Suppose une utilisation en cours |
| Outils | Wireshark | 2 | Suppose une utilisation en cours |
| Outils | Outils de diagnostic matériel | 3 | |
| Outils | Git | 2 | **Non mentionné par Alexandre** : supprimer si non pratiqué |

---

## 7. Centres d'intérêt (Administration > Centres d'intérêt)

- **[supposé]** Toutes les descriptions courtes (ex. VTT : « Sorties en pleine nature : endurance, persévérance et goût de l'effort. »).
- **[supposé]** 4e centre d'intérêt « Systèmes d'exploitation » (icône terminal), déduit des deux projets d'OS. Le supprimer si ce n'est pas un vrai centre d'intérêt.

---

## 8. Ce qui n'est PAS dans les données de départ

- Aucun compte administrateur : utiliser `public_html/setup-admin.php` (puis le supprimer).
- Aucune image de projet, aucun CV en PDF, aucun message.
- Aucun chiffre (joueurs, visites, nombre de cartes) n'a été inventé.
