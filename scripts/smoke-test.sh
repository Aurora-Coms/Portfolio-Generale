#!/usr/bin/env bash
# =============================================================================
#  Vérification rapide d'un site lancé (« smoke test »)
# =============================================================================
#
#  Usage :
#      bash scripts/smoke-test.sh                          # http://127.0.0.1:8080
#      bash scripts/smoke-test.sh https://www.votre-domaine.fr
#
#  En local, lancez d'abord le serveur de développement (il applique les règles
#  du .htaccess) :
#      php -S 127.0.0.1:8080 -t public_html app/tools/dev-router.php
#
#  Vérifie, en lecture seule (requêtes GET, aucun formulaire envoyé) :
#   - les pages publiques répondent 200, sans trace d'erreur PHP ;
#   - une adresse inconnue affiche la page 404 du site ;
#   - l'administration redirige vers la connexion ;
#   - les fichiers sensibles sont refusés (403) ;
#   - les en-têtes de sécurité (CSP stricte, anti-cadre, nosniff…) et les
#     options du cookie de session (HttpOnly, SameSite).
#
#  Code de sortie : 0 si tout est conforme, 1 sinon. Nécessite curl.
# =============================================================================

set -euo pipefail

BASE="${1:-http://127.0.0.1:8080}"
BASE="${BASE%/}"
command -v curl >/dev/null 2>&1 || { echo "curl est nécessaire." >&2; exit 1; }

TRAVAIL="$(mktemp -d "${TMPDIR:-/tmp}/portfolio-smoke.XXXXXX")"
trap 'rm -rf -- "$TRAVAIL"' EXIT
CORPS="$TRAVAIL/corps"
ENTETES="$TRAVAIL/entetes"
echecs=0
nb=0

ko() { echecs=$((echecs + 1)); printf '  ÉCHEC  %s\n' "$*"; }
ok() { printf '  ok     %s\n' "$*"; }

# requete CHEMIN : télécharge la page (sans suivre les redirections).
requete() {
    curl -sS -o "$CORPS" -D "$ENTETES" -w '%{http_code}' --max-time 20 "$BASE$1" 2>/dev/null || echo 000
}

# entete NOM : valeur de l'en-tête de la dernière réponse (vide si absent).
entete() {
    tr -d '\r' < "$ENTETES" | awk -v nom="$1" '
        tolower(substr($0, 1, length(nom) + 1)) == tolower(nom) ":" { sub(/^[^:]*:[ \t]*/, ""); print; exit }'
}

# verifier CHEMIN CODE_ATTENDU [TEXTE_ATTENDU] (CODE_ATTENDU peut être « 3?? »).
verifier() {
    local chemin="$1" attendu="$2" texte="${3:-}" code
    nb=$((nb + 1))
    code="$(requete "$chemin")"
    # shellcheck disable=SC2053  # comparaison avec motif volontaire (3??)
    if [[ "$code" != $attendu ]]; then
        ko "$chemin : code $code (attendu $attendu)"
        return 0
    fi
    if [ -n "$texte" ] && ! grep -qF -- "$texte" "$CORPS"; then
        ko "$chemin : texte « $texte » absent de la réponse"
        return 0
    fi
    if grep -qE '(Fatal error|Parse error|Warning|Notice|Deprecated)(</b>)?: |Uncaught |Stack trace' "$CORPS"; then
        ko "$chemin : trace d'erreur PHP dans la page"
        return 0
    fi
    ok "$chemin ($code)"
}

echo "== Pages publiques ($BASE)"
verifier /                    200 '</html>'
verifier /projets             '30?'
verifier /projets/jeux        200 '</html>'
verifier /projets/techniques  200 '</html>'
verifier /parcours            200 '</html>'
verifier /competences         200 '</html>'
verifier /a-propos            200 '</html>'
verifier /contact             200 'name="_csrf"'
verifier /mentions-legales    200 '</html>'
verifier /robots.txt          200
verifier /sitemap.xml         200 '<urlset'

# Premier projet publié listé dans le plan du site (s'il y en a un).
projet="$(grep -oE '<loc>[^<]*/projet/[^<]+</loc>' "$CORPS" | head -n 1 | sed -E 's#<loc>https?://[^/]+##; s#</loc>##' || true)"
if [ -n "$projet" ]; then
    verifier "$projet" 200 '</html>'
fi

echo "== Erreurs et accès refusés"
verifier /page-inexistante-smoke-test 404 'Page introuvable'
verifier /projet/projet-inexistant-smoke-test 404 'Page introuvable'
verifier /admin               '30?'
verifier /admin/projets       '30?'
verifier /admin/connexion     200 'name="_csrf"'
verifier /.htaccess           403
verifier /index.php.bak       403
verifier /uploads/projects/test.php 403

echo "== En-têtes de sécurité (page d'accueil)"
requete / >/dev/null
csp="$(entete Content-Security-Policy)"
nb=$((nb + 1))
case "$csp" in
    *"script-src 'self'"*"style-src 'self'"*"object-src 'none'"*"frame-ancestors 'none'"*)
        if [[ "$csp" == *unsafe-inline* || "$csp" == *unsafe-eval* ]]; then
            ko "Content-Security-Policy autorise unsafe-inline/unsafe-eval"
        else
            ok "Content-Security-Policy stricte"
        fi
        ;;
    *) ko "Content-Security-Policy absente ou incomplète : ${csp:-(aucune)}" ;;
esac
for attendu in "X-Frame-Options: DENY" "X-Content-Type-Options: nosniff" \
    "Referrer-Policy: strict-origin-when-cross-origin" "Cross-Origin-Opener-Policy: same-origin"; do
    nb=$((nb + 1))
    nom="${attendu%%:*}"
    valeur="$(entete "$nom")"
    if [ "$valeur" = "${attendu#*: }" ]; then ok "$attendu"; else ko "$nom : « ${valeur:-(absent)} »"; fi
done
nb=$((nb + 1))
if [ -n "$(entete Permissions-Policy)" ]; then ok "Permissions-Policy"; else ko "Permissions-Policy absent"; fi
nb=$((nb + 1))
if [ -z "$(entete X-Powered-By)" ]; then ok "X-Powered-By masqué"; else ko "X-Powered-By révèle : $(entete X-Powered-By)"; fi
if [[ "$BASE" == https://* ]]; then
    nb=$((nb + 1))
    if [[ "$(entete Strict-Transport-Security)" == *max-age=* ]]; then ok "Strict-Transport-Security"; else ko "Strict-Transport-Security absent"; fi
fi

echo "== Cookie de session (page de connexion)"
requete /admin/connexion >/dev/null
cookie="$(entete Set-Cookie)"
nb=$((nb + 1))
if [ -z "$cookie" ]; then
    ok "aucun cookie posé (rien à vérifier)"
elif [[ "$cookie" == *[Hh]ttp[Oo]nly* && "$cookie" == *[Ss]ame[Ss]ite=* ]]; then
    if [[ "$BASE" == https://* && "$cookie" != *[Ss]ecure* ]]; then
        ko "cookie de session sans l'option Secure en HTTPS"
    else
        ok "cookie de session HttpOnly + SameSite"
    fi
else
    ko "cookie de session sans HttpOnly ou SameSite : $cookie"
fi

echo
if [ "$echecs" -gt 0 ]; then
    echo "$echecs échec(s) sur $nb vérifications."
    exit 1
fi
echo "OK : $nb vérifications réussies."
