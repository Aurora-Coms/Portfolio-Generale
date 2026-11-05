# Feuille de route du portfolio

Cette liste suit l'avancement du site, de son développement à sa mise en ligne,
puis ses évolutions possibles. Elle sert de base au projet GitHub **« Portfolio »**
(les numéros entre parenthèses renvoient aux tickets de
`.github/project-items.json`, synchronisés vers le tableau du projet).

Légende : `[x]` fait · `[ ]` à faire · **(Alexandre)** = à faire par Alexandre
(contenu, comptes, hébergement : personne d'autre ne peut le faire à sa place).

---

## 1. Développement du site

- [x] Noyau sécurisé : routeur, CSRF automatique, sessions, PDO, gestion des erreurs (#1)
- [x] Base de données : schéma, contraintes, données de départ (#2)
- [x] Modèles d'accès aux données et leurs tests (`tests/models-test.php`)
- [x] Design « éditeur de code » : feuilles de style, gabarits, composants, guide de style (#3)
- [x] Double authentification TOTP, QR code écrit en PHP, codes de secours (#7)
- [x] Installation : `public_html/setup-admin.php` et `app/tools/create-admin.php`
- [x] Documentation : `README.md` (Hostinger pas à pas, LinkedIn), `docs/SECURITE.md`, cette feuille de route (#8)
- [ ] Pages publiques : accueil, projets, détail, parcours, compétences, à propos, contact (#4) · écrites, en cours de vérification
- [ ] Administration, lot A : connexion, tableau de bord, projets, images, catégories, tags (#5) · écrite, en cours de vérification
- [ ] Administration, lot B : parcours, compétences, à propos, messages, réglages, compte (#6) · écrite, en cours de vérification
- [ ] Revue de sécurité indépendante et tests d'intégration complets (#9)
- [ ] Pull request `claude/portfolio-site` vers `main`, relecture et fusion (#10)

---

## 2. Contenus à vérifier (Alexandre)

Tout ce qui a été rédigé sans information précise est listé dans
[`CONTENU-A-VERIFIER.md`](CONTENU-A-VERIFIER.md) et se corrige depuis `/admin`.

- [ ] Relire le texte « À propos », l'accroche de l'accueil et la description pour Google (#11)
- [ ] Vérifier les descriptions techniques de TARDIS, Portail du TVA et Cardverse (#11)
- [x] Niveaux des compétences confirmés par Alexandre (thèmes > sous-catégories, 43 compétences) (#11)
- [ ] Relire les descriptions des compétences (voir [`CONTENU-A-VERIFIER.md`](CONTENU-A-VERIFIER.md), section 6) (#11)
- [ ] Décrire le projet « OS Fondationnaire » puis cocher **Publié** (#12)
- [x] Projet « Réalisation d'un système d'exploitation aidé de l'IA » publié comme projet futur (#13)
- [ ] Préciser les 6 stages : une entrée par stage dans **Parcours** (lieu, dates, missions) (#14)
- [ ] Vérifier l'adresse de l'hébergeur dans **Réglages > Mentions légales**
- [ ] Supprimer les catégories et tags inutilisés, ou leur ajouter des projets

## 3. Images, vidéos et liens (Alexandre)

- [ ] Ajouter des captures d'écran à chaque projet (**Projets > Images**) avec un texte alternatif (#15)
- [ ] Ajouter les vidéos YouTube des jeux (lien à coller dans la fiche du projet) (#15)
- [ ] Renseigner les liens **LinkedIn** et **GitHub** dans **Réglages** (#16)
- [ ] Renseigner l'**adresse e-mail de contact** (celle qui reçoit les messages) (#16)
- [ ] Envoyer le **CV** au format PDF (5 Mo maximum) (#16)
- [ ] Ajouter des projets techniques : TP VLAN et routage (Packet Tracer), analyse Wireshark, write-up de CTF, serveur maison (#22)

## 4. Mise en ligne sur Hostinger (Alexandre)

Suivre le [README](../README.md#4-mise-en-ligne-sur-hostinger-pas-à-pas), étape par étape.

- [ ] Choisir PHP 8.3 (ou plus récent) et vérifier que `display_errors` est désactivé
- [ ] Créer la base MySQL et importer `database.sql` dans phpMyAdmin (#17)
- [ ] Préparer `app/config.php` : `app_key` (64 caractères hexadécimaux), base, `setup_token` temporaire (#17)
- [ ] Envoyer le contenu de `public_html/` et le dossier `app/` **à côté** (jamais dedans), vérifier les `.htaccess` (#17)
- [ ] Activer le SSL gratuit et vérifier la redirection vers `https://` (#18)
- [ ] Créer le compte administrateur avec `/setup-admin.php` (#19)
- [ ] **Supprimer** `public_html/setup-admin.php` et vider `setup_token` (#19)
- [ ] Activer la double authentification et ranger les codes de secours hors ligne (#19)
- [ ] Créer la boîte `contact@` dans hPanel et configurer le SMTP (`smtp.hostinger.com`, 465, `ssl`) (#20)
- [ ] Tester le formulaire de contact (message dans l'administration + e-mail reçu) (#20)
- [ ] Vérifier les en-têtes sur securityheaders.com et Mozilla Observatory (objectif A ou A+) (#18)
- [ ] Vérifier l'affichage sur téléphone (Android et iPhone si possible)

## 5. LinkedIn (Alexandre)

- [ ] Ajouter le site dans **Coordonnées** (type « Portfolio ») (#21)
- [ ] Ajouter le lien dans la section **Sélection** (#21)
- [ ] Mentionner le portfolio dans le titre ou la description du profil (#21)
- [ ] Rafraîchir l'aperçu avec le LinkedIn Post Inspector après chaque changement d'image ou de texte (#21)
- [ ] Partager un projet (par exemple TARDIS) dans un post, avec sa capture d'écran

## 6. Entretien régulier (Alexandre)

- [ ] Sauvegarder la base (phpMyAdmin > Exporter) et les images une fois par mois
- [ ] Désactiver ou mettre à jour le bandeau « Recherche de stage » après le stage (le tableau de bord prévient quand les dates sont dépassées)
- [ ] Consulter `app/storage/logs/php-errors.log` de temps en temps
- [ ] Mettre à jour le CV et le parcours à chaque nouvelle expérience

---

## 7. Idées d'évolutions

### Sécurité (voir [`SECURITE.md` § 19](SECURITE.md#19-limites-connues-et-améliorations-possibles))

- [ ] Fermer toutes les sessions quand le mot de passe est changé depuis l'administration
- [ ] Rendre la double authentification obligatoire
- [ ] Refuser les mots de passe connus pour avoir fuité (API « Have I Been Pwned », k-anonymat)
- [ ] Publier un fichier `/.well-known/security.txt` (contact pour signaler une faille)
- [ ] Ajouter `Cross-Origin-Resource-Policy` et préparer l'inscription HSTS `preload`
- [ ] Restreindre les droits de l'utilisateur MySQL après l'installation
- [ ] Journal des connexions à l'administration visible dans le tableau de bord
- [ ] Rotation automatique du fichier de journal

### Qualité et outillage

- [ ] Intégration continue GitHub Actions : `php -l` sur tous les fichiers et lancement des tests
- [ ] Tests de bout en bout automatisés (Playwright) : pages, formulaire, administration
- [ ] Ajouter un fichier `LICENSE` (choisir une licence, par exemple MIT)
- [ ] Script de déploiement (archive prête à envoyer, sans `docs/` ni `tests/`)

### Fonctionnalités

- [ ] Version anglaise du site (recruteurs internationaux, stages Erasmus)
- [ ] Page « Veille » ou « Blog » : articles courts sur la cybersécurité et les réseaux
- [ ] Write-ups de CTF avec blocs de code
- [ ] Statistiques de visites **sans traceur** (comptage côté serveur, sans cookie)
- [ ] Thème clair en option (en respectant la préférence du système)
- [ ] Badges de certifications (Cisco NetAcad, ANSSI SecNumacadémie, Pix…)
