#!/usr/bin/env bash
# =============================================================================
#  Fabrication du paquet de mise en ligne Hostinger
# =============================================================================
#
#  Usage (depuis n'importe quel dossier) :
#      bash scripts/build-release.sh            # fabrique et vérifie le paquet
#      bash scripts/build-release.sh --detail   # idem, en listant chaque fichier
#
#  Résultat : dist/portfolio-hostinger-AAAAMMJJ.zip, qui contient à sa racine :
#      public_html/   contenu public (à copier DANS domains/<domaine>/public_html/)
#      app/           code et modèle de configuration (domains/<domaine>/app/)
#      database.sql   schéma et données de départ (import phpMyAdmin)
#      LISEZMOI.txt   aide-mémoire de l'installation
#
#  Ne sont JAMAIS livrés : app/config.php (identifiants), journaux, sessions,
#  CV et images envoyés, outils de développement, tests, documentation, .git.
#  Le paquet est vérifié après fabrication ; en cas d'anomalie il est supprimé
#  et le script s'arrête en erreur (code 1).
#
#  Outils nécessaires : bash, find, cp, grep, et « zip » ou à défaut Python 3.
#  Facultatif : php (vérification de la syntaxe des fichiers livrés), unzip.
# =============================================================================

set -euo pipefail

# --- Chemins -----------------------------------------------------------------

# Racine du dépôt = dossier parent de scripts/ (quel que soit le dossier courant).
RACINE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
DIST="$RACINE/dist"
DATE_PAQUET="$(date +%Y%m%d)"
ZIP="$DIST/portfolio-hostinger-$DATE_PAQUET.zip"

DETAIL=0   # 1 = afficher chaque fichier de l'archive (option --detail)
for option in "$@"; do
    case "$option" in
        -h|--help)
            # Affiche le bloc de commentaires d'en-tête de ce script.
            awk 'NR == 1 { next } /^#/ { sub(/^# ?/, ""); print; next } { exit }' "${BASH_SOURCE[0]}"
            exit 0
            ;;
        -d|--detail) DETAIL=1 ;;
        *)
            echo "Option inconnue : $option (voir --help)" >&2
            exit 2
            ;;
    esac
done

# Dossier de travail temporaire, effacé à la sortie quoi qu'il arrive.
TRAVAIL="$(mktemp -d "${TMPDIR:-/tmp}/portfolio-paquet.XXXXXX")"
trap 'rm -rf -- "$TRAVAIL"' EXIT
PAQUET="$TRAVAIL/paquet"     # arborescence du futur zip
VERIF="$TRAVAIL/verif"       # zip décompressé pour la vérification
mkdir -p "$PAQUET" "$VERIF"

# Python : sert de solution de secours pour créer, lister et extraire le zip.
PYTHON=""
for candidat in python3 python; do
    if command -v "$candidat" >/dev/null 2>&1 && "$candidat" -c 'import sys; sys.exit(sys.version_info < (3, 6))' 2>/dev/null; then
        PYTHON="$candidat"
        break
    fi
done

info()  { printf '  %s\n' "$*"; }
titre() { printf '\n== %s\n' "$*"; }

# Arrêt en erreur : le zip éventuellement produit est supprimé pour qu'un
# paquet douteux ne puisse pas être envoyé par mégarde.
echec() {
    printf '\nÉCHEC : %s\n' "$*" >&2
    rm -f -- "$ZIP"
    exit 1
}

# --- Règles de sélection -------------------------------------------------------

# a_livrer CHEMIN : vrai (0) si le fichier, chemin relatif au dépôt, fait
# partie du paquet. Dans un « case », « * » couvre aussi les « / ».
a_livrer() {
    local chemin="$1"
    local nom="${chemin##*/}"

    # Fichiers cachés : seuls .htaccess et .gitkeep (dossiers vides) servent.
    # Le dossier .well-known (validation des certificats SSL) est conservé.
    case "/$chemin" in
        */.well-known/*) ;;
        */.*/*) return 1 ;;                       # contenu d'un dossier caché
        *)
            case "$nom" in
                .htaccess|.gitkeep) ;;
                .*) return 1 ;;                   # .DS_Store, .env, .user.ini…
            esac
            ;;
    esac

    # Fichiers temporaires d'éditeur, sauvegardes et journaux.
    case "$nom" in
        *~|*.swp|*.swo|*.bak|*.old|*.orig|*.tmp|*.log|Thumbs.db|desktop.ini) return 1 ;;
    esac

    case "$chemin" in
        # ---- Partie publique ----
        public_html/_demo*|public_html/*/_demo*) return 1 ;;   # pages de démonstration
        public_html/uploads/projects/.gitkeep) return 0 ;;
        public_html/uploads/projects/*) return 1 ;;            # images envoyées en local
        public_html/*) return 0 ;;

        # ---- Application ----
        app/config.example.php) return 0 ;;
        app/config.php|app/config.*) return 1 ;;              # identifiants (et leurs copies)
        app/storage/*/.gitkeep) return 0 ;;
        app/storage/logs/*|app/storage/sessions/*|app/storage/cv/*) return 1 ;;
        app/tools/dev-router.php|app/tools/generate-images.php) return 1 ;;   # outils locaux
        app/*) return 0 ;;
    esac
    return 1
}

# --- 1. Préparation de l'arborescence --------------------------------------------

titre "Préparation du paquet depuis $RACINE"
cd -- "$RACINE"

for requis in public_html/index.php public_html/.htaccess app/bootstrap.php app/config.example.php database.sql; do
    [ -f "$requis" ] || echec "fichier du dépôt introuvable : $requis"
done

nb_copies=0
nb_ignores=0
while IFS= read -r -d '' fichier; do
    fichier="${fichier#./}"
    if [ -L "$fichier" ]; then
        info "ignoré (lien symbolique) : $fichier"
        nb_ignores=$((nb_ignores + 1))
        continue
    fi
    if a_livrer "$fichier"; then
        mkdir -p -- "$PAQUET/$(dirname -- "$fichier")"
        cp -p -- "$fichier" "$PAQUET/$fichier"   # -p : garde la date du fichier
        nb_copies=$((nb_copies + 1))
    else
        nb_ignores=$((nb_ignores + 1))
    fi
done < <(find public_html app \( -type f -o -type l \) -print0)

cp -- database.sql "$PAQUET/database.sql"

# Version livrée (commit Git), pour savoir plus tard ce qui est en ligne.
VERSION="inconnue"
if command -v git >/dev/null 2>&1 && git -C "$RACINE" rev-parse --git-dir >/dev/null 2>&1; then
    VERSION="$(git -C "$RACINE" rev-parse --short HEAD 2>/dev/null || echo inconnue)"
    if [ -n "$(git -C "$RACINE" --no-optional-locks status --porcelain --untracked-files=no 2>/dev/null)" ]; then
        VERSION="$VERSION + modifications locales non validées"
    fi
fi

# Aide-mémoire (15 lignes au plus ; le guide complet est dans README.md).
cat > "$PAQUET/LISEZMOI.txt" <<EOF
Portfolio d'Alexandre Derbez · paquet Hostinger du $(date +%d/%m/%Y) · version $VERSION

1. hPanel > Avancé > Configuration PHP : PHP 8.1 minimum (8.3 conseillé).
2. Copiez le CONTENU du dossier public_html/ dans domains/votre-domaine.fr/public_html/
   (fichiers cachés .htaccess compris).
3. Copiez le dossier app/ à côté, dans domains/votre-domaine.fr/app/ (jamais dans public_html).
4. hPanel > Bases de données : créez une base, puis importez database.sql avec phpMyAdmin
   (onglet « Importer »). N'envoyez pas database.sql sur le serveur.
5. Dans app/, copiez config.example.php en config.php et remplissez-le
   (base de données, e-mail, base_url en https, app_key de 64 caractères hexadécimaux).
6. Créez le compte administrateur avec /setup-admin.php, puis supprimez ce fichier.
Mise à jour d'un site en ligne : ne réimportez JAMAIS database.sql (il efface tout),
gardez votre app/config.php et suivez docs/MIGRATIONS.md du dépôt.
Guide complet, pas à pas : README.md du dépôt (section « Mise en ligne sur Hostinger »).
EOF

# Droits classiques d'hébergement : dossiers 755, fichiers 644.
find "$PAQUET" -type d -exec chmod 755 {} +
find "$PAQUET" -type f -exec chmod 644 {} +

info "$nb_copies fichiers retenus, $nb_ignores écartés (+ database.sql et LISEZMOI.txt)"

# --- 2. Contrôle de la syntaxe PHP des fichiers livrés ---------------------------

titre "Syntaxe PHP"
if command -v php >/dev/null 2>&1; then
    nb_php=0
    while IFS= read -r -d '' source_php; do
        nb_php=$((nb_php + 1))
        if ! sortie="$(php -l "$source_php" 2>&1)"; then
            echec "erreur de syntaxe PHP : ${source_php#"$PAQUET"/}
$sortie"
        fi
    done < <(find "$PAQUET" -type f -name '*.php' -print0)
    info "php -l : $nb_php fichiers sans erreur ($(php -r 'echo PHP_VERSION;'))"
else
    info "php absent : vérification de la syntaxe ignorée (elle est faite par la CI)"
fi

# --- 3. Création du zip ----------------------------------------------------------------

titre "Création de l'archive"
mkdir -p "$DIST"
rm -f -- "$ZIP"   # « zip » complèterait une archive existante au lieu de la remplacer
if command -v zip >/dev/null 2>&1; then
    # Liste triée transmise à zip : ordre des entrées identique d'une fois sur l'autre.
    (cd -- "$PAQUET" && find . -mindepth 1 | sed 's#^\./##' | LC_ALL=C sort | zip -X -q -9 -@ "$ZIP")
    info "outil : zip"
elif [ -n "$PYTHON" ]; then
    "$PYTHON" -I - "$PAQUET" "$ZIP" <<'PY'
# Équivalent de « zip -r » : chemins relatifs, séparateur « / », ordre stable.
import os
import sys
import zipfile

source, cible = sys.argv[1], sys.argv[2]
with zipfile.ZipFile(cible, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for dossier, sous_dossiers, fichiers in os.walk(source):
        sous_dossiers.sort()
        for nom in sorted(fichiers):
            complet = os.path.join(dossier, nom)
            archive.write(complet, os.path.relpath(complet, source).replace(os.sep, "/"))
PY
    info "outil : $PYTHON (zipfile)"
else
    echec "ni « zip » ni Python 3 ne sont installés : impossible de créer l'archive"
fi
[ -s "$ZIP" ] || echec "archive vide ou absente : $ZIP"

# --- 4. Vérifications sur l'archive elle-même --------------------------------------------

titre "Vérification du contenu"

# Extraction dans un dossier temporaire (ce qui est vérifié est ce qui sera livré).
if command -v unzip >/dev/null 2>&1; then
    unzip -q "$ZIP" -d "$VERIF"
elif [ -n "$PYTHON" ]; then
    "$PYTHON" -I -c 'import sys, zipfile; zipfile.ZipFile(sys.argv[1]).extractall(sys.argv[2])' "$ZIP" "$VERIF"
else
    echec "ni « unzip » ni Python 3 : impossible de vérifier l'archive"
fi

LISTE="$TRAVAIL/liste.txt"
(cd -- "$VERIF" && find . -type f | sed 's#^\./##' | LC_ALL=C sort) > "$LISTE"
nb_entrees="$(wc -l < "$LISTE" | tr -d ' ')"

# Liste du contenu : chaque fichier (--detail) ou un résumé par dossier.
if [ "$DETAIL" -eq 1 ]; then
    sed 's/^/  /' "$LISTE"
else
    awk -F/ '{ d = "."; if (NF > 1) { d = $1; for (i = 2; i < NF; i++) d = d "/" $i } n[d]++ }
        END { for (d in n) printf "  %-46s %3d fichier(s)\n", (d == "." ? "(racine)" : d "/"), n[d] }' "$LISTE" \
        | LC_ALL=C sort
fi
info "total : $nb_entrees fichiers"

# a) Présence des éléments indispensables.
for requis in LISEZMOI.txt database.sql \
    public_html/index.php public_html/.htaccess public_html/setup-admin.php \
    public_html/uploads/.htaccess public_html/uploads/projects/.gitkeep \
    app/.htaccess app/bootstrap.php app/routes.php app/config.example.php app/tools/create-admin.php \
    app/storage/logs/.gitkeep app/storage/sessions/.gitkeep app/storage/cv/.gitkeep; do
    grep -qxF -- "$requis" "$LISTE" || echec "élément manquant dans l'archive : $requis"
done

# b) Racine : uniquement public_html/, app/, database.sql et LISEZMOI.txt.
if hors_racine="$(grep -vE '^(public_html/|app/|database\.sql$|LISEZMOI\.txt$)' "$LISTE")"; then
    echec "élément inattendu à la racine de l'archive :
$hors_racine"
fi

# c) Éléments interdits (identifiants, dépôt Git, développement, données locales).
INTERDITS='(^|/)config\.php$|^app/config\.|(^|/)\.git(/|$)|(^|/)\.github/|(^|/)\.gitignore$'
INTERDITS="$INTERDITS"'|^(tests|docs|scripts|dist|database)/|(^|/)_demo|(^|/)sess_|\.(log|env|bak|swp|sh|md)$'
INTERDITS="$INTERDITS"'|^app/tools/(dev-router|generate-images)\.php$|^app/storage/[^/]+/[^.]'
INTERDITS="$INTERDITS"'|^public_html/uploads/projects/[^.]|^public_html/.*\.sql$'
if trouves="$(grep -E -- "$INTERDITS" "$LISTE" | grep -vxF 'app/config.example.php')"; then
    echec "élément interdit dans l'archive :
$trouves"
fi
info "aucun app/config.php, tests/, docs/, scripts/, .git ni donnée locale"

# d) Secrets : aucune clé de 64 caractères hexadécimaux (format de app_key)
#    hors du modèle config.example.php (fichiers texte uniquement).
#    Une erreur de grep (code 2) fait échouer le contrôle : jamais de « succès par défaut ».
code=0
secrets="$(grep -rlIE '(^|[^0-9A-Fa-f])[0-9A-Fa-f]{64}([^0-9A-Fa-f]|$)' "$VERIF")" || code=$?
[ "$code" -le 1 ] || echec "recherche de secrets impossible (grep, code $code)"
secrets="$(printf '%s\n' "$secrets" | grep -v -e '/app/config\.example\.php$' -e '^$' || true)"
if [ -n "$secrets" ]; then
    echec "chaîne hexadécimale de 64 caractères (clé secrète ?) dans :
${secrets//"$VERIF"\//}"
fi
info "aucun secret détecté (clé hexadécimale de 64 caractères)"

# --- 5. Résumé ------------------------------------------------------------------------------

titre "Paquet prêt"
octets="$(wc -c < "$ZIP" | tr -d ' ')"
taille="$(awk -v o="$octets" 'BEGIN { if (o >= 1048576) printf "%.1f Mo", o / 1048576; else printf "%.0f Ko", o / 1024 }')"
empreinte="non calculée"
if command -v sha256sum >/dev/null 2>&1; then
    empreinte="$(sha256sum "$ZIP" | cut -d' ' -f1)"
elif command -v shasum >/dev/null 2>&1; then
    empreinte="$(shasum -a 256 "$ZIP" | cut -d' ' -f1)"
fi
info "fichier  : $ZIP"
info "taille   : $taille ($octets octets)"
info "contenu  : $nb_entrees fichiers · version $VERSION"
info "SHA-256  : $empreinte"
