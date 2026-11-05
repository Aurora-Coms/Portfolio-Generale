#!/usr/bin/env bash
# =============================================================================
#  Export statique du site public (pour GitHub Pages)
# =============================================================================
#  Usage : bash scripts/build-static.sh [dossier_sortie] [chemin_de_base]
#          ex. bash scripts/build-static.sh _site /Portfolio-Generale
#
#  Prérequis : app/config.php configuré avec 'static_site' => true, une base
#  remplie (database.sql importé) et 'base_url' = adresse publique finale
#  (ex. https://aurora-coms.github.io/Portfolio-Generale).
#
#  Le site PHP est lancé en local, chaque page publique est enregistrée en
#  HTML, les fichiers statiques sont copiés, puis les liens sont préfixés par
#  le chemin de base. L'administration, le formulaire et le CV n'existent pas
#  dans cette version (GitHub Pages n'exécute pas PHP).
# =============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${1:-_site}"
BASE="${2:-}"
PORT="${STATIC_PORT:-8765}"
URL="http://127.0.0.1:${PORT}"

case "$OUT" in /*) ;; *) OUT="$ROOT/$OUT" ;; esac
rm -rf "$OUT"
mkdir -p "$OUT"
cd "$ROOT"

# 1. Serveur PHP local (mêmes règles et en-têtes que la production).
php -S "127.0.0.1:${PORT}" -t public_html app/tools/dev-router.php > "${TMPDIR:-/tmp}/serveur-statique.log" 2>&1 &
SERVER=$!
trap 'kill "$SERVER" 2>/dev/null || true' EXIT
for _ in $(seq 1 40); do
    curl -fs -o /dev/null "$URL/robots.txt" && break
    sleep 0.25
done

# 2. Liste des pages : pages fixes + chaque projet publié.
# shellcheck disable=SC2016  # code PHP entre apostrophes : pas d'expansion voulue
mapfile -t PROJETS < <(php -r '
    define("PUBLIC_PATH", getcwd() . "/public_html");
    require "app/bootstrap.php";
    foreach (["game", "tech"] as $section) {
        foreach (App\Models\Project::published($section) as $p) {
            echo "/projet/", $p["slug"], "\n";
        }
    }
')
PAGES=(/ /projets/jeux /projets/techniques /parcours /competences /a-propos /contact /mentions-legales "${PROJETS[@]}")

save() { # $1 = chemin d'URL, $2 = fichier de sortie, $3 = code HTTP attendu
    mkdir -p "$(dirname "$2")"
    code="$(curl -s -o "$2" -w '%{http_code}' "$URL$1")"
    if [ "$code" != "$3" ]; then
        echo "ERREUR : $1 a répondu $code (attendu $3)" >&2
        exit 1
    fi
    echo "  $1 -> ${2#"$OUT"/}"
}

echo "Pages :"
for page in "${PAGES[@]}"; do
    if [ "$page" = "/" ]; then
        save / "$OUT/index.html" 200
    else
        save "$page" "$OUT${page}/index.html" 200
    fi
done
save /page-introuvable "$OUT/404.html" 404
save /sitemap.xml "$OUT/sitemap.xml" 200
save /robots.txt "$OUT/robots.txt" 200

# 3. Fichiers statiques.
cp -r public_html/assets "$OUT/assets"
mkdir -p "$OUT/uploads/projects"
find public_html/uploads/projects -maxdepth 1 -type f -regextype posix-extended \
    -regex '.*/[a-f0-9]{32}(-thumb)?\.(webp|jpg)$' -exec cp {} "$OUT/uploads/projects/" \;
touch "$OUT/.nojekyll"   # pas de traitement Jekyll par GitHub Pages

# 4. Liens préfixés par le chemin de base du site.
php scripts/static-postprocess.php "$OUT" "$BASE"

# 5. Contrôles : aucune trace de PHP, de l'administration ou de secrets.
if grep -rl --include='*.html' -e 'name="_csrf"' -e '/admin/' "$OUT" >/dev/null; then
    echo "ERREUR : une page exportée contient un formulaire ou un lien d'administration." >&2
    grep -rl --include='*.html' -e 'name="_csrf"' -e '/admin/' "$OUT" >&2
    exit 1
fi
echo "Export terminé : $(find "$OUT" -type f | wc -l) fichiers dans ${OUT#"$ROOT"/}."
