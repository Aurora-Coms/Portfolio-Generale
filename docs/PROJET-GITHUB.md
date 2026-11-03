# Projet GitHub « Portfolio » : synchronisation automatique

Le suivi du portfolio se fait dans le projet GitHub **« Portfolio »** de
l'organisation Aurora-Coms : <https://github.com/orgs/Aurora-Coms/projects/6>.

Les 22 tickets de départ sont décrits dans `.github/project-items.json` (statut,
priorité, taille, dates) et existent déjà comme **issues** du dépôt. Une
**GitHub Action** (`.github/workflows/project-sync.yml`) les range dans le projet
et remplit leurs champs, puis tient le projet à jour toute seule : nouvelle issue,
issue fermée, pull request ouverte ou fusionnée.

Pourquoi une Action et un jeton ? Le jeton fourni automatiquement aux Actions
(`GITHUB_TOKEN`) n'a **jamais** accès aux projets d'une organisation. Il faut donc
créer un jeton personnel qui a ce droit et le confier au dépôt sous forme de
**secret**. C'est la seule manipulation à faire, une fois.

Sommaire :

1. [En bref](#1-en-bref)
2. [Avant de commencer](#2-avant-de-commencer)
3. [Créer le jeton](#3-créer-le-jeton)
4. [Enregistrer le jeton : secret PROJECT_TOKEN](#4-enregistrer-le-jeton--secret-project_token)
5. [Lancer la synchronisation](#5-lancer-la-synchronisation)
6. [Ce qui se passe ensuite tout seul](#6-ce-qui-se-passe-ensuite-tout-seul)
7. [Les champs et les vues du projet](#7-les-champs-et-les-vues-du-projet)
8. [Ajouter un ticket plus tard](#8-ajouter-un-ticket-plus-tard)
9. [Dépannage](#9-dépannage)
10. [Sécurité de cette automatisation](#10-sécurité-de-cette-automatisation)
11. [Fichiers et tests](#11-fichiers-et-tests)

---

## 1. En bref

- [ ] Fusionner la branche de travail dans `main` (l'Action doit être sur la branche par défaut).
- [ ] Créer un jeton fine-grained : propriétaire **Aurora-Coms**, dépôt **Portfolio-Generale**,
      Issues / Pull requests / Metadata en **lecture**, **Projects** (organisation) en **lecture et écriture**.
- [ ] Le faire approuver par l'organisation si elle le demande.
- [ ] L'ajouter au dépôt : Settings › Secrets and variables › Actions › secret **`PROJECT_TOKEN`**.
- [ ] Onglet **Actions** › « Synchroniser le projet GitHub » › **Run workflow**.
- [ ] Régler les vues du projet (section 7).

---

## 2. Avant de commencer

- **L'Action doit être sur la branche par défaut (`main`).** GitHub n'affiche le
  bouton « Run workflow » et ne réagit aux issues et aux pull requests que pour
  les workflows présents sur la branche par défaut. Tant que la pull request
  `claude/portfolio-site` ⇒ `main` n'est pas fusionnée, il ne se passe rien :
  c'est normal.
- **Droits nécessaires** : être administrateur du dépôt pour ajouter un secret.
  Sinon, demandez à un propriétaire de l'organisation Aurora-Coms.
- **Actions autorisées** : dépôt › Settings › Actions › General › « Actions
  permissions » doit autoriser au moins les actions créées par GitHub
  (`actions/checkout` et `actions/github-script`).

---

## 3. Créer le jeton

Un jeton (« personal access token ») est un mot de passe limité, propre à une
utilisation. Traitez-le comme un mot de passe : ne le collez **jamais** dans le
code, une issue, un commit ou une conversation. Il ne va qu'à un seul endroit :
le secret du dépôt (section 4).

### Option A (recommandée) : jeton fine-grained

Il ne donne accès qu'à ce dont l'Action a besoin.

1. Sur GitHub, cliquez sur votre avatar (en haut à droite) › **Settings** ›
   tout en bas du menu de gauche **Developer settings** › **Personal access
   tokens** › **Fine-grained tokens** › **Generate new token**.
   Lien direct : <https://github.com/settings/personal-access-tokens/new>.
2. **Token name** : `portfolio-project-sync`. **Description** : « Synchronisation
   du projet GitHub Portfolio ».
3. **Resource owner** : choisissez **Aurora-Coms** (et non votre compte
   personnel). Si Aurora-Coms n'apparaît pas, l'organisation n'autorise pas encore
   ces jetons : un propriétaire doit l'activer (paramètres de l'organisation ›
   Personal access tokens › Settings), ou utilisez l'option B.
4. **Expiration** : 90 jours par exemple (l'organisation peut imposer un
   maximum). Notez la date : GitHub vous préviendra par e-mail avant
   l'expiration, il faudra alors refaire un jeton et mettre à jour le secret.
5. **Repository access** : **Only select repositories** › choisissez
   **Aurora-Coms/Portfolio-Generale**.
6. **Permissions** (selon la version de l'interface : listes « Repository
   permissions » et « Organization permissions », ou bouton **Add permissions**) :

   | Où | Permission | Niveau | Pourquoi |
   |---|---|---|---|
   | Dépôt | **Issues** | Read-only | lire les tickets (état, étiquettes) |
   | Dépôt | **Pull requests** | Read-only | lire les PR (brouillon, fusion, « Closes #n ») |
   | Dépôt | **Metadata** | Read-only | obligatoire, ajoutée d'office |
   | Organisation | **Projects** | **Read and write** | ajouter les éléments au projet et remplir leurs champs |

   Rien d'autre : pas de « Contents », pas d'« Administration ».
7. **Generate token**, puis **copiez** le jeton (il commence par
   `github_pat_`). Il ne sera plus jamais affiché : si vous le perdez, il faut en
   refaire un.
8. **Approbation** : si l'organisation exige d'approuver les jetons, le vôtre
   reste **en attente** (« Pending ») et ne fonctionne pas encore. Un
   propriétaire d'Aurora-Coms l'approuve dans les paramètres de l'organisation ›
   **Personal access tokens** › **Pending requests**. Tant que ce n'est pas fait,
   l'Action affiche « n'a pas le droit Projects » ou « Projet n°6 introuvable ».

### Option B : jeton classique

À utiliser seulement si l'organisation refuse les jetons fine-grained : un jeton
classique donne accès à **tous** vos dépôts.

1. Avatar › **Settings** › **Developer settings** › **Personal access tokens** ›
   **Tokens (classic)** › **Generate new token** › **Generate new token
   (classic)**. Lien direct : <https://github.com/settings/tokens/new>.
2. **Note** : `portfolio-project-sync` ; **Expiration** : 90 jours.
3. Cochez les portées (« scopes ») **`repo`** et **`project`** (pas seulement
   `read:project`, qui ne permet pas d'écrire). Rien d'autre.
4. **Generate token**, copiez le jeton (`ghp_…`).
5. Si l'organisation utilise l'authentification unique (SAML SSO) : dans la
   liste des jetons, **Configure SSO** › **Authorize** à côté d'Aurora-Coms.

---

## 4. Enregistrer le jeton : secret PROJECT_TOKEN

1. Ouvrez le dépôt **Aurora-Coms/Portfolio-Generale** › onglet **Settings** ›
   menu de gauche **Secrets and variables** › **Actions**.
   Lien direct : <https://github.com/Aurora-Coms/Portfolio-Generale/settings/secrets/actions>.
2. Onglet **Secrets** (et non « Variables ») › **New repository secret**.
3. **Name** : `PROJECT_TOKEN` (exactement, en majuscules).
   **Secret** : collez le jeton. › **Add secret**.

La valeur n'est plus jamais affichée, même à vous. Pour la remplacer (jeton
expiré ou régénéré) : cliquez sur le crayon à côté de `PROJECT_TOKEN` ›
**Update secret**.

---

## 5. Lancer la synchronisation

1. Onglet **Actions** du dépôt › dans la liste de gauche **Synchroniser le
   projet GitHub** › bouton **Run workflow** (à droite).
2. **Branch** : `main`. Laissez **force** décoché la première fois.
3. **Run workflow**. Une ligne apparaît après quelques secondes : cliquez
   dessus pour suivre l'exécution.

La première synchronisation dure environ **3 minutes** : 22 tickets, jusqu'à
huit écritures chacun, espacées de 0,8 seconde pour respecter les limites de
GitHub. Les suivantes ne modifient que ce qui a changé.

### Lire le résultat

Ouvrez l'exécution › tâche **Ranger les tickets dans le projet** › étape
**Synchroniser le projet « Portfolio »**. Le journal affiche :

- `Projet « Portfolio » trouvé : …` : le jeton fonctionne ;
- `Champs reconnus : Status ⇒ « Status », Priority ⇒ « Priority », …` : les
  champs du projet que l'Action a trouvés (et ceux qui manquent) ;
- une ligne par ticket : `#3 « Design … » : ajouté au projet · Status : Done ·
  Priority : P0 · …`, ou `déjà à jour` ;
- `… conservé (« In review » dans le projet, « In progress » demandé)` : une
  valeur choisie à la main a été respectée ;
- `Bilan : …` en fin d'étape.

La page de l'exécution (onglet **Summary**) reprend tout dans un tableau. Une
coche verte signifie que tout est synchronisé ; une croix rouge s'accompagne
d'un message en français qui dit quoi faire (section 9).

### Mode normal ou « force » ?

| | Mode normal (par défaut) | Avec **force** coché |
|---|---|---|
| Ticket absent du projet | ajouté, tous les champs du JSON remplis | idem |
| Champ vide dans le projet | rempli depuis le JSON | rempli depuis le JSON |
| Champ déjà rempli (par vous ou une exécution précédente) | **conservé** | **remplacé** par la valeur du JSON |
| Issue fermée | Status **Done** | Status **Done** |

Cochez **force** seulement après avoir modifié `.github/project-items.json` et
si vous voulez que ces valeurs remplacent celles du projet. Attention : cela
annule aussi les changements faits à la main sur les tickets du JSON.

---

## 6. Ce qui se passe ensuite tout seul

Une fois le secret en place, l'Action réagit aux événements du dépôt (comptez
une demi-minute) :

| Événement | Effet dans le projet |
|---|---|
| Nouvelle issue | ajoutée en **Backlog** ; **P1** si elle porte l'étiquette `bug` ; si elle est décrite dans `project-items.json`, ses champs sont repris |
| Étiquette `bug` ajoutée | ajoutée si elle était absente (Backlog, P1) ; Priority remplie si elle était vide |
| Issue fermée | **Done** |
| Issue rouverte | **In progress** |
| PR ouverte, rouverte ou « Ready for review » | la PR est ajoutée en **In review**, ainsi que les tickets cités par `Closes #n` |
| PR ouverte en brouillon (draft) | la PR et ses tickets passent en **In progress** |
| PR fusionnée dans `main` | la PR et ses tickets passent en **Done** |
| PR fermée sans fusion | la PR passe en Done ; ses tickets qui étaient en In review reviennent en **In progress** |
| Autre étiquette, autre action | rien (l'exécution est ignorée) |

Règles à connaître :

- L'Action **ne remplace jamais** une valeur choisie à la main, sauf pour les
  statuts liés à un événement (fermeture, réouverture, PR). Un ticket déjà
  **Done** ou fermé n'est pas renvoyé en relecture par une nouvelle PR.
- Elle relit l'état réel du ticket sur GitHub avant d'écrire : si plusieurs
  événements arrivent en rafale, le projet reflète l'état final.
- Elle ne modifie **que le projet** : jamais les issues, les étiquettes ni le
  code.
- Un élément **archivé** dans le projet est laissé tel quel.

### Lier une PR à ses tickets

Dans la **description** de la PR, écrivez une ligne par ticket :

```text
Closes #4
Fixes #9, #12 et #13
```

Mots reconnus : `Close`, `Closes`, `Closed`, `Fix`, `Fixes`, `Fixed`,
`Resolve`, `Resolves`, `Resolved` (ceux de GitHub), ainsi que `Résout`,
`Corrige` et `Ferme`. Les liens complets
(`Closes https://github.com/Aurora-Coms/Portfolio-Generale/issues/4`) marchent
aussi. Une mention dans un commentaire HTML (`<!-- … -->`) ou un bloc de code
est ignorée.

Préférez les mots anglais : **seuls eux font fermer l'issue par GitHub** à la
fusion. Avec « Résout #4 », le ticket passe bien en Done dans le projet mais
l'issue reste ouverte : fermez-la à la main.

---

## 7. Les champs et les vues du projet

### Les champs

| Champ | Type | Valeurs | Rempli par l'Action à partir de |
|---|---|---|---|
| **Status** | Single select | Backlog, Ready, In progress, In review, Done | `status` du JSON, puis les événements (section 6) |
| **Priority** | Single select | P0 (indispensable), P1 (important), P2 (plus tard) | `priority` du JSON ; **P1** d'office pour un `bug` |
| **Size** | Single select | XS, S, M, L, XL | `size` du JSON |
| **Estimate** | Number | 1, 2, 3, 5, 8 | `estimate` du JSON, sinon déduit de Size : XS 1, S 2, M 3, L 5, XL 8 |
| **Start date** | Date | AAAA-MM-JJ | `start` du JSON |
| **Target date** (ou End date) | Date | AAAA-MM-JJ | `end` du JSON |
| **Iteration** | Iteration | Itération 1, 2… | l'itération qui contient la date de début (ou `iteration` du JSON, par son titre) |

Les noms sont reconnus sans tenir compte des majuscules, des accents ni des
emojis, avec des équivalents :

- Status : Statut, État ; options Backlog ⇄ Todo / À faire, Ready ⇄ Todo,
  In progress ⇄ En cours, In review ⇄ Review / En revue, Done ⇄ Terminé ;
- Priority : Priorité ; Size : Taille ; Estimate : Estimation, Points ;
- dates de début : Start date, Start, Date de début ;
- dates de fin : Target date, End date, Due date, Date de fin, Échéance ;
- Iteration : Itération, Sprint.

Un champ ou une option introuvable n'arrête rien : l'Action le signale par un
avertissement jaune et remplit le reste. Le type compte aussi : un champ texte
nommé « Estimate » est ignoré, il faut un champ **Number**.

Pour créer ou compléter un champ : dans le projet, menu **⋯** (en haut à
droite) › **Settings** › **Custom fields** › **+ New field** (nom, type) ; pour
ajouter une option, cliquez sur le champ dans cette même page.

### Régler une vue

Cliquez sur l'onglet de la vue, puis sur la petite flèche **▾** à côté de son
nom : **Layout** (Table, Board, Roadmap), **Fields** (colonnes affichées),
**Group by**, **Sort by**, **Column by** (tableau), **Date fields** (feuille de
route). Le filtre se tape dans la barre au-dessus de la vue. Une pastille
signale les changements non enregistrés : **Save changes** pour les garder pour
tout le monde.

### Prioritized backlog : quoi faire ensuite

Tout le travail restant, du plus important au moins important.

- **Layout** : Table.
- **Group by** : Priority (groupes P0, P1, P2).
- **Sort by** : Status, puis Target date.
- **Filtre** : `-status:Done` (cache ce qui est terminé).
- **Fields** : Title, Status, Priority, Size, Estimate, Iteration, Start date,
  Target date, Labels, Assignees.
- **Somme** : menu ▾ › **Field sum** › Estimate, pour voir la charge de chaque
  priorité dans l'en-tête du groupe.
- **Alimentée par** : Priority, Status, Size et Estimate.

### Status board : où en est chaque ticket

Le tableau en colonnes, façon post-it.

- **Layout** : Board ; **Column by** : Status (Backlog, Ready, In progress,
  In review, Done).
- **Group by** : aucun (ou Priority pour des couloirs P0 / P1 / P2).
- **Sort by** : Priority.
- **Fields** affichés sur les cartes : Priority, Size, Labels, Iteration.
- **Limite de colonne** (menu de la colonne In progress › Set limit) : 3, pour
  ne pas tout commencer à la fois.
- **Alimentée par** : Status (JSON, puis événements). Glisser une carte change
  son statut : l'Action respecte ce choix.

### Roadmap : le planning

La frise des tickets dans le temps.

- **Layout** : Roadmap ; **Date fields** : Start date ⇒ Target date (ou End date).
- **Zoom** : Month (ou Quarter pour une vue d'ensemble).
- **Markers** : Iteration (bandes des itérations) et, si vous en utilisez, les
  jalons (milestones).
- **Group by** : Status (ou Priority) ; **Sort by** : Start date.
- **Filtre** facultatif : `-status:Done` pour ne garder que le reste à faire.
- **Alimentée par** : `start` et `end` du JSON, Iteration. Un ticket sans dates
  apparaît sans barre : renseignez ses dates dans le projet ou dans le JSON.

### Bugs : les anomalies

- **Layout** : Table ; **Filtre** : `label:bug`.
- **Group by** : Status ; **Sort by** : Priority.
- **Fields** : Title, Status, Priority, Assignees, Linked pull requests, Labels.
- **Alimentée par** : l'étiquette `bug` (posée sur l'issue), la priorité **P1**
  automatique et le passage en Done à la fermeture.

### In review : en cours de relecture

- **Layout** : Table (ou Board) ; **Filtre** : `status:"In review"`.
- **Sort by** : Priority.
- **Fields** : Title, Assignees, Reviewers, Linked pull requests, Priority.
- **Alimentée par** : les pull requests (ouverture, Ready for review) et les
  tickets qu'elles citent avec `Closes #n`.

### Flux intégrés du projet (Workflows)

Le projet a ses propres automatismes : menu **⋯** › **Workflows**. Réglages
conseillés pour qu'ils ne contredisent pas l'Action :

| Flux intégré | Conseil |
|---|---|
| Item added to project | **désactivé**, ou réglé sur « Status : Backlog » (sinon il peut remplacer le statut écrit par l'Action juste après l'ajout) |
| Item closed | activé, « Done » (même effet que l'Action) |
| Pull request merged | activé, « Done » |
| Item reopened | désactivé, ou « In progress » |
| Auto-add to project | inutile : l'Action ajoute déjà les issues et les PR |
| Auto-archive items | facultatif ; les éléments archivés sont ignorés par l'Action |

---

## 8. Ajouter un ticket plus tard

**Créer une issue suffit** : onglet **Issues** › **New issue**. Elle arrive
dans le projet en **Backlog** (en **P1** si vous lui mettez l'étiquette `bug`).
Ensuite, au choix :

- **Directement dans le projet** (le plus simple) : renseignez Priority, Size,
  Start date et Target date dans la vue Prioritized backlog. L'Action ne
  touchera pas à ces valeurs.
- **Dans le dépôt**, pour garder une trace écrite : ajoutez un objet à la liste
  `items` de `.github/project-items.json`, avec le numéro de l'issue, puis
  fusionnez dans `main` et relancez l'Action (sans force : seuls les champs
  vides sont remplis).

```json
{
  "key": "galerie-video",
  "title": "Vidéos des projets dans la galerie",
  "labels": ["enhancement"],
  "status": "Ready",
  "priority": "P1",
  "size": "M",
  "start": "2026-12-01",
  "end": "2026-12-12",
  "issue": 23
}
```

| Clé | Rôle |
|---|---|
| `issue` | numéro de l'issue (obligatoire pour la synchronisation) |
| `status` | Backlog, Ready, In progress, In review ou Done |
| `priority` | P0, P1 ou P2 |
| `size` | XS, S, M, L ou XL |
| `estimate` | facultatif : nombre ; sinon déduit de `size` |
| `start`, `end` | facultatif : dates AAAA-MM-JJ |
| `iteration` | facultatif : titre exact d'une itération ; sinon celle qui contient `start` |
| `key`, `title`, `labels`, `body` | description du ticket (la synchronisation ne s'en sert pas : elle ne modifie pas les issues) |

Pour travailler sur le ticket : ouvrez une pull request avec `Closes #23` dans
la description. Le ticket passe en In review, puis en Done à la fusion.

---

## 9. Dépannage

Les messages de l'Action sont en français et disent quoi faire. Les cas les plus
courants :

| Ce que vous voyez | Cause | Solution |
|---|---|---|
| L'Action n'apparaît pas dans l'onglet Actions, pas de bouton « Run workflow » | le workflow n'est pas encore sur `main` | fusionner la pull request dans `main` (section 2) |
| « Secret PROJECT_TOKEN manquant » | secret absent, mal nommé ou créé dans « Variables » | section 4 : nom exact `PROJECT_TOKEN`, onglet **Secrets** |
| « refusé par GitHub (invalide, expiré ou révoqué) » | jeton expiré, supprimé ou mal copié | refaire un jeton (section 3) puis **Update secret** |
| « n'a pas le droit « Projects » sur Aurora-Coms » | jeton créé pour votre compte au lieu d'Aurora-Coms, permission Projects absente, jeton en attente d'approbation, ou jeton classique sans `project` | refaire le jeton avec **Resource owner : Aurora-Coms** et **Projects : Read and write** ; faire approuver la demande ; option B : portée `project` ; SSO : **Authorize** |
| « Projet n°6 introuvable » | mauvais numéro, ou jeton sans droit Projects (GitHub cache alors le projet) | vérifier l'adresse du projet et `project.number` dans le JSON, puis les permissions ci-dessus |
| « peut lire le projet mais pas le modifier » | Projects en « Read-only » (ou `read:project`) | modifier le jeton (Edit) : **Projects : Read and write** ; l'organisation peut demander une nouvelle approbation |
| « n'a pas accès au dépôt Aurora-Coms/Portfolio-Generale » | dépôt non sélectionné dans « Repository access » | modifier le jeton : ajouter Portfolio-Generale, Issues et Pull requests en Read-only |
| Avertissement « Champ « Size » introuvable » | champ absent, renommé autrement, ou d'un autre type | créer le champ (section 7) ou le renommer ; le reste est quand même rempli |
| Avertissement « Option « In review » introuvable » | l'option n'existe pas dans le champ | ajouter l'option dans les réglages du champ |
| Le statut change tout seul juste après l'ajout | flux intégré « Item added to project » | le désactiver ou le régler sur Backlog (section 7) |
| Un ticket n'est pas mis à jour | valeur choisie à la main (conservée en mode normal) ou élément archivé | relancer avec **force**, ou restaurer l'élément : menu ⋯ du projet › **Archived items** › Restore |
| Avertissement « Limite de débit de l'API GitHub atteinte » | trop d'écritures en peu de temps | rien à faire : l'Action attend et réessaie ; sinon relancer plus tard (**Re-run jobs**) |
| Ticket en Done mais issue encore ouverte | PR liée avec « Résout #n » (mot français) | fermer l'issue à la main ; la prochaine fois, écrire `Closes #n` |

Pour relancer une exécution échouée après correction : ouvrez-la dans l'onglet
Actions › **Re-run jobs** (ou relancez « Run workflow »).

---

## 10. Sécurité de cette automatisation

- **Moindre privilège** : le `GITHUB_TOKEN` automatique est en lecture seule
  (`contents`, `issues`, `pull-requests` : read) et ne sert qu'à récupérer le
  script. `PROJECT_TOKEN` ne peut que lire ce dépôt et écrire dans les projets ;
  il expire.
- **Le secret ne sort pas de l'étape qui l'utilise** : l'étape de vérification
  ne reçoit que « true » ou « false », jamais la valeur ; GitHub masque de toute
  façon les secrets dans les journaux.
- **`pull_request_target` sans risque ici** : ce déclencheur donne accès aux
  secrets même pour une PR venant d'un fork, ce qui est dangereux seulement si
  le workflow exécute le code de la PR. Ici, le checkout récupère uniquement le
  dossier `.github` de la **branche par défaut**, sans conserver d'identifiants ;
  rien n'est installé ni compilé ; le titre et la description de la PR ne sont
  jamais insérés dans une commande : le script les lit comme du texte et n'en
  extrait que des numéros.
- **Actions épinglées** par empreinte de commit (`actions/checkout@11d5960…`,
  `actions/github-script@f28e40c…`) : une étiquette comme `v4` peut être
  déplacée par un attaquant, une empreinte non. Pour mettre à jour : prendre
  l'empreinte de la nouvelle version sur la page « Releases » de l'action,
  remplacer l'empreinte et le commentaire de version dans le workflow.
- **Journaux propres** : les titres d'issues sont nettoyés (pas de retour à la
  ligne, donc pas de fausse commande de workflow) et échappés dans le résumé
  HTML.
- **Jeton compromis ?** Supprimez-le aussitôt (Settings › Developer settings ›
  Personal access tokens › **Delete**), refaites-en un et mettez à jour le
  secret.

---

## 11. Fichiers et tests

| Fichier | Rôle |
|---|---|
| `.github/project-items.json` | les tickets et leurs champs |
| `.github/workflows/project-sync.yml` | déclencheurs, droits, étapes de l'Action |
| `.github/scripts/sync-project.js` | la synchronisation (exécutée par `actions/github-script`) |
| `tests/project-sync.test.js` | tests du script, sans réseau ni dépendance |

Lancer les tests (Node.js 18 ou plus) :

```bash
node tests/project-sync.test.js
```

Ils simulent l'API de GitHub (projet aux champs nommés de plusieurs façons,
issues, PR) et vérifient : la reconnaissance des champs et options, les
écritures attendues pour chaque événement, le respect des valeurs choisies à la
main, les champs manquants, et l'échec propre sans jeton ou sans permission.

Planification facultative : le workflow contient un bloc `schedule` en
commentaire. Décommenté, il relance une synchronisation complète chaque lundi
matin (utile pour rattraper un événement manqué).
