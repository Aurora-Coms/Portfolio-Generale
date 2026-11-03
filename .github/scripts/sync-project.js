/**
 * Synchronisation du projet GitHub « Portfolio » (organisation Aurora-Coms, projet n°6).
 *
 * Exécuté sur les serveurs de GitHub par .github/workflows/project-sync.yml,
 * grâce à actions/github-script :
 *
 *   const synchroniser = require('./.github/scripts/sync-project.js');
 *   await synchroniser({ github, context, core });
 *
 * Selon l'événement reçu :
 *   - workflow_dispatch / schedule : range chaque ticket de .github/project-items.json
 *     dans le projet et remplit Status, Priority, Size, Estimate, dates et Iteration,
 *     puis ajoute les autres issues ouvertes du dépôt (Backlog, P1 si « bug ») ;
 *   - issues : opened ⇒ ajout (Backlog, P1 si bug) ; closed ⇒ Done ;
 *     reopened ⇒ In progress ; labeled « bug » ⇒ ajout s'il est absent ;
 *   - pull_request(_target) : PR ouverte ⇒ In review (brouillon : In progress), de même
 *     que les tickets cités par « Closes #n / Fixes #n / Résout #n » ;
 *     PR fusionnée ⇒ Done pour la PR et pour ces tickets.
 *
 * Le script ne modifie que le projet : jamais les issues, les PR ni le code. Le texte
 * des issues et des PR est une donnée (on en extrait des numéros par expression
 * régulière), il n'est jamais exécuté ni interprété.
 *
 * Documentation : docs/PROJET-GITHUB.md · Tests : node tests/project-sync.test.js
 */

'use strict';

const fs = require('node:fs');
const path = require('node:path');

/** Fichier des tickets, à côté du dossier scripts/. */
const ITEMS_FILE = path.join(__dirname, '..', 'project-items.json');

/** Champs du projet : nom de référence, types acceptés, alias (casse, accents et emojis ignorés). */
const FIELD_SPECS = {
    status: { label: 'Status', types: ['SINGLE_SELECT'], aliases: ['Status', 'Statut', 'État'] },
    priority: { label: 'Priority', types: ['SINGLE_SELECT'], aliases: ['Priority', 'Priorité'] },
    size: { label: 'Size', types: ['SINGLE_SELECT'], aliases: ['Size', 'Taille'] },
    estimate: { label: 'Estimate', types: ['NUMBER'], aliases: ['Estimate', 'Estimation', 'Story points', 'Points'] },
    start: { label: 'Start date', types: ['DATE'], aliases: ['Start date', 'Start', 'Date de début', 'Début', 'Begin date'] },
    end: {
        label: 'Target date',
        types: ['DATE'],
        aliases: ['Target date', 'End date', 'Due date', 'Date de fin', 'Target', 'Échéance', 'Deadline', 'Fin'],
    },
    iteration: { label: 'Iteration', types: ['ITERATION'], aliases: ['Iteration', 'Itération', 'Sprint', 'Cycle'] },
};

/** Ordre dans lequel les champs sont écrits (et affichés dans le journal). */
const FIELD_ORDER = ['status', 'priority', 'size', 'estimate', 'start', 'end', 'iteration'];

/** Options des listes de choix : valeur de référence ⇒ alias, du plus au moins proche. */
const OPTION_SPECS = {
    status: {
        Backlog: ['Backlog', 'Todo', 'To do', 'À faire', 'New', 'Nouveau'],
        Ready: ['Ready', 'Todo', 'To do', 'Prêt', 'À faire'],
        'In progress': ['In progress', 'En cours', 'Doing', 'WIP'],
        'In review': ['In review', 'Review', 'En revue', 'En relecture', 'Revue'],
        Done: ['Done', 'Terminé', 'Terminée', 'Fini', 'Fait', 'Closed', 'Fermé'],
    },
    priority: {
        P0: ['P0', 'Urgent', 'Critique', 'Critical'],
        P1: ['P1', 'High', 'Haute', 'Élevée', 'Important'],
        P2: ['P2', 'Medium', 'Moyenne', 'Normale', 'Low', 'Basse'],
    },
    size: {
        XS: ['XS', 'Extra small', 'Très petite', 'Tiny'],
        S: ['S', 'Small', 'Petite'],
        M: ['M', 'Medium', 'Moyenne'],
        L: ['L', 'Large', 'Grande'],
        XL: ['XL', 'Extra large', 'X-Large', 'Très grande', 'Huge'],
    },
};

/** Estimation déduite de la taille quand le JSON n'en donne pas (suite de Fibonacci). */
const ESTIMATE_BY_SIZE = { XS: 1, S: 2, M: 3, L: 5, XL: 8 };

/** Nombre maximal de tickets liés pris en compte par PR (protection contre les abus). */
const MAX_LINKED_ISSUES = 20;
/** Délai minimal entre deux écritures : reste sous la limite de 80 créations par minute. */
const WRITE_DELAY_MS = 800;
/** Nombre de tentatives quand GitHub signale une limite de débit. */
const RATE_LIMIT_ATTEMPTS = 3;

const DOC = 'docs/PROJET-GITHUB.md';

const TYPE_LABELS = {
    SINGLE_SELECT: 'liste de choix (Single select)',
    NUMBER: 'nombre (Number)',
    DATE: 'date (Date)',
    ITERATION: 'itération (Iteration)',
    TEXT: 'texte (Text)',
};

/* ------------------------------------------------------------------------- */
/* Requêtes GraphQL                                                          */
/* ------------------------------------------------------------------------- */

/** Projet et champs. Le type de propriétaire est une constante du script, jamais une donnée externe. */
function projectQuery(ownerType) {
    return `query($login: String!, $number: Int!) {
  ${ownerType}(login: $login) {
    projectV2(number: $number) {
      id
      title
      url
      closed
      fields(first: 50) {
        nodes {
          __typename
          ... on ProjectV2Field { id name dataType }
          ... on ProjectV2SingleSelectField { id name dataType options { id name } }
          ... on ProjectV2IterationField {
            id
            name
            dataType
            configuration {
              iterations { id title startDate duration }
              completedIterations { id title startDate duration }
            }
          }
        }
      }
    }
  }
}`;
}

/** Éléments de projet d'une issue ou d'une PR, avec leurs valeurs actuelles. */
const ITEM_QUERY = `query($id: ID!) {
  node(id: $id) {
    __typename
    ... on Issue { projectItems(first: 50, includeArchived: true) { nodes { ...ProjectItemValues } } }
    ... on PullRequest { projectItems(first: 50, includeArchived: true) { nodes { ...ProjectItemValues } } }
  }
}
fragment ProjectItemValues on ProjectV2Item {
  id
  isArchived
  project { id }
  fieldValues(first: 50) {
    nodes {
      __typename
      ... on ProjectV2ItemFieldSingleSelectValue { optionId name field { ... on ProjectV2FieldCommon { id } } }
      ... on ProjectV2ItemFieldDateValue { date field { ... on ProjectV2FieldCommon { id } } }
      ... on ProjectV2ItemFieldNumberValue { number field { ... on ProjectV2FieldCommon { id } } }
      ... on ProjectV2ItemFieldIterationValue { iterationId title field { ... on ProjectV2FieldCommon { id } } }
    }
  }
}`;

/** Tickets que GitHub relie lui-même à la PR (mots-clés anglais, liens manuels). */
const CLOSING_QUERY = `query($id: ID!) {
  node(id: $id) {
    ... on PullRequest {
      closingIssuesReferences(first: 25) { nodes { number repository { nameWithOwner } } }
    }
  }
}`;

/** Ajout idempotent : si le contenu est déjà dans le projet, GitHub renvoie l'élément existant. */
const ADD_ITEM_MUTATION = `mutation($projectId: ID!, $contentId: ID!) {
  addProjectV2ItemById(input: { projectId: $projectId, contentId: $contentId }) { item { id } }
}`;

const UPDATE_FIELD_MUTATION = `mutation($projectId: ID!, $itemId: ID!, $fieldId: ID!, $value: ProjectV2FieldValue!) {
  updateProjectV2ItemFieldValue(
    input: { projectId: $projectId, itemId: $itemId, fieldId: $fieldId, value: $value }
  ) { projectV2Item { id } }
}`;

/* ------------------------------------------------------------------------- */
/* Messages d'erreur bloquants                                               */
/* ------------------------------------------------------------------------- */

/** Erreur qui arrête la synchronisation (jeton, droits, configuration). */
class FatalError extends Error {}

const MESSAGES = {
    noToken: () =>
        'Aucun jeton GitHub utilisable : le secret PROJECT_TOKEN est absent ou vide. '
        + `Créez un jeton (${DOC}, section 3) puis enregistrez-le dans Settings › Secrets and variables › Actions `
        + '› New repository secret, sous le nom PROJECT_TOKEN.',
    auth: (err) =>
        'Le jeton PROJECT_TOKEN est refusé par GitHub (invalide, expiré ou révoqué). '
        + `Générez-en un nouveau (${DOC}, section 3) et remplacez la valeur du secret PROJECT_TOKEN. `
        + `Détail : ${describeError(err)}`,
    projectPermission: (ctx, err) =>
        `Le jeton PROJECT_TOKEN n'a pas le droit « Projects » sur ${ctx.config.org} : il ne peut pas lire le projet `
        + `n°${ctx.config.number}. Jeton fine-grained : propriétaire (Resource owner) ${ctx.config.org} et permission `
        + 'd\'organisation « Projects : Read and write » ; si l\'organisation exige une approbation, le jeton reste '
        + 'inactif tant qu\'un propriétaire ne l\'a pas validé. Jeton classique : portées « project » et « repo ». '
        + (/by integration/i.test(describeError(err))
            ? 'Le jeton reçu ressemble au GITHUB_TOKEN par défaut, qui n\'a jamais accès aux projets d\'une organisation : '
              + 'vérifiez que le workflow utilise bien secrets.PROJECT_TOKEN. '
            : '')
        + `Voir ${DOC}, section Dépannage. Détail : ${describeError(err)}`,
    writePermission: (ctx, err) =>
        `Le jeton PROJECT_TOKEN peut lire le projet n°${ctx.config.number} mais pas le modifier. Jeton fine-grained : `
        + 'la permission d\'organisation « Projects » doit être « Read and write » (et non « Read-only »). '
        + 'Jeton classique : portée « project » (et non « read:project »). '
        + `Détail : ${describeError(err)}`,
    notFound: (ctx) =>
        `Projet n°${ctx.config.number} introuvable pour ${ctx.config.org}. Soit le numéro est faux (il figure dans `
        + `l'adresse https://github.com/orgs/${ctx.config.org}/projects/${ctx.config.number} et dans `
        + '.github/project-items.json, clé project.number), soit le jeton n\'a pas le droit « Projects » sur '
        + `l'organisation : GitHub présente alors le projet comme inexistant. Voir ${DOC}, section Dépannage.`,
    repoAccess: (ctx, err) =>
        `Le jeton PROJECT_TOKEN n'a pas accès au dépôt ${ctx.owner}/${ctx.repo}. Jeton fine-grained : dans `
        + '« Repository access », choisissez « Only select repositories » puis ce dépôt, avec les permissions '
        + '« Issues : Read-only », « Pull requests : Read-only » et « Metadata : Read-only ». '
        + `Jeton classique : portée « repo ». Détail : ${describeError(err)}`,
};

/* ------------------------------------------------------------------------- */
/* Outils                                                                    */
/* ------------------------------------------------------------------------- */

/** Texte comparable : sans accents ni emojis, en minuscules, mots séparés par une espace. */
function normalize(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

/**
 * Premier candidat dont le nom correspond à un alias, alias par alias :
 * égalité (espaces ignorées : « To do » = « Todo ») puis début de nom
 * (« P0 urgent » commence par « P0 », mais « P10 » ne commence pas par « P1 »).
 */
function matchByAliases(candidates, aliases, nameOf = (candidate) => candidate.name) {
    const prepared = (candidates || []).map((candidate) => ({ candidate, name: normalize(nameOf(candidate)) }));
    for (const alias of aliases) {
        const wanted = normalize(alias);
        if (!wanted) {
            continue;
        }
        const compact = wanted.replace(/ /g, '');
        const exact = prepared.find((p) => p.name !== '' && p.name.replace(/ /g, '') === compact);
        if (exact) {
            return exact.candidate;
        }
        const prefix = prepared.find((p) => p.name.startsWith(`${wanted} `));
        if (prefix) {
            return prefix.candidate;
        }
    }
    return null;
}

/** Valeur de référence (« In progress », « P1 », « M »…) pour une valeur écrite librement. */
function canonicalValue(key, raw) {
    if (raw === undefined || raw === null || String(raw).trim() === '') {
        return null;
    }
    for (const [canonical, aliases] of Object.entries(OPTION_SPECS[key])) {
        if (matchByAliases([{ name: String(raw) }], aliases)) {
            return canonical;
        }
    }
    return null;
}

/** Une ligne de journal propre : ni retour à la ligne (commandes de workflow), ni texte sans fin. */
function clean(value, max = 120) {
    const text = String(value ?? '').replace(/[\u0000-\u001f\u007f-\u009f\u2028\u2029]+/g, ' ').trim();
    return text.length > max ? `${text.slice(0, max - 1)}…` : text;
}

function describeError(err) {
    const details = Array.isArray(err?.errors) ? err.errors.map((e) => e?.message).filter(Boolean) : [];
    return clean(details.length ? details.join(' ; ') : err?.message || String(err), 300);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

/** Nature d'une erreur de l'API GitHub (REST ou GraphQL). */
function classifyError(err) {
    const status = err?.status ?? err?.response?.status;
    const errors = Array.isArray(err?.errors) ? err.errors : [];
    const types = errors.map((e) => String(e?.type || '').toUpperCase());
    const text = [err?.message, ...errors.map((e) => e?.message)].filter(Boolean).join(' ');
    if (status === 429 || types.includes('RATE_LIMITED') || /rate limit|abuse detection/i.test(text)) {
        return 'ratelimit';
    }
    if (status === 401 || /bad credentials|requires authentication/i.test(text)) {
        return 'auth';
    }
    if (
        status === 403
        || types.includes('FORBIDDEN')
        || types.includes('INSUFFICIENT_SCOPES')
        || /resource not accessible|not been granted the required scopes/i.test(text)
    ) {
        return 'permission';
    }
    if (status === 404 || status === 410 || types.includes('NOT_FOUND') || /could not resolve to/i.test(text)) {
        return 'notfound';
    }
    return 'other';
}

function isValidDate(value) {
    if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return false;
    }
    const [y, m, d] = value.split('-').map(Number);
    const date = new Date(Date.UTC(y, m - 1, d));
    return date.getUTCFullYear() === y && date.getUTCMonth() === m - 1 && date.getUTCDate() === d;
}

/** Numéro de jour (UTC) d'une date AAAA-MM-JJ, ou null. */
function dayNumber(value) {
    if (!isValidDate(value)) {
        return null;
    }
    const [y, m, d] = value.split('-').map(Number);
    return Date.UTC(y, m - 1, d) / 86400000;
}

/** Étiquette « bug » (ou « 🐛 bug », « type: bug », « bogue »), mais pas « debug ». */
function isBugLabel(name) {
    return normalize(name).split(' ').some((word) => ['bug', 'bugs', 'bogue', 'bogues'].includes(word));
}

function hasBugLabel(issue) {
    return (issue?.labels || []).some((label) => isBugLabel(typeof label === 'string' ? label : label?.name));
}

function fieldType(field) {
    if (field?.dataType) {
        return field.dataType;
    }
    if (Array.isArray(field?.options)) {
        return 'SINGLE_SELECT';
    }
    return field?.configuration ? 'ITERATION' : undefined;
}

/** Associe chaque champ attendu à un champ du projet (nom par alias ET type compatible). */
function resolveFields(nodes) {
    const fields = (nodes || []).filter((f) => f && f.id && f.name);
    const resolved = {};
    const wrongType = {};
    const used = new Set();
    for (const key of FIELD_ORDER) {
        const spec = FIELD_SPECS[key];
        const typed = fields.filter((f) => spec.types.includes(fieldType(f)) && !used.has(f.id));
        const found = matchByAliases(typed, spec.aliases);
        if (found) {
            resolved[key] = { ...found, dataType: fieldType(found) };
            used.add(found.id);
        } else {
            const other = matchByAliases(fields, spec.aliases);
            if (other) {
                wrongType[key] = other;
            }
        }
    }
    return { resolved, wrongType };
}

function iterationsOf(field) {
    const configuration = field?.configuration || {};
    return [...(configuration.iterations || []), ...(configuration.completedIterations || [])];
}

/** Itération par titre, ou celle qui contient la date donnée. */
function findIteration(field, { title = null, date = null } = {}) {
    const list = iterationsOf(field);
    if (title) {
        return matchByAliases(list, [title], (iteration) => iteration.title);
    }
    const day = dayNumber(date);
    if (day === null) {
        return null;
    }
    return list.find((iteration) => {
        const first = dayNumber(iteration.startDate);
        return first !== null && day >= first && day < first + Number(iteration.duration || 0);
    }) || null;
}

/** Numéros des tickets fermés par une PR : « Closes #3 », « Fixes #4 et #5 », « Résout #6 », lien complet. */
const CLOSING_KEYWORDS = String.raw`close[sd]?|fix(?:e[sd])?|resolve[sd]?|r[ée]sou[dst]|r[ée]solue?s?|corrige[sz]?|ferme[sz]?`;
const ISSUE_REF = String.raw`https?:\/\/github\.com\/[\w.-]+\/[\w.-]+\/issues\/\d+|(?:[\w.-]+\/[\w.-]+)?#\d+`;
const CLOSING_RE_SOURCE = String.raw`(?<![\p{L}\p{N}_])(?:${CLOSING_KEYWORDS})(?:\s*:\s*|\s+)`
    + String.raw`((?:${ISSUE_REF})(?:\s*(?:,|&|\bet\b|\band\b)\s*(?:${ISSUE_REF}))*)`;

function parseClosingReferences(body, owner, repo) {
    const text = String(body || '')
        .slice(0, 65536)
        .normalize('NFC')
        .replace(/<!--[\s\S]*?-->/g, ' ') // commentaires des modèles de PR
        .replace(/```[\s\S]*?```/g, ' ') // blocs de code
        .replace(/`[^`\n]*`/g, ' '); // code en ligne
    const same = `${owner}/${repo}`.toLowerCase();
    const numbers = new Set();
    for (const match of text.matchAll(new RegExp(CLOSING_RE_SOURCE, 'giu'))) {
        for (const [ref] of match[1].matchAll(new RegExp(ISSUE_REF, 'giu'))) {
            const url = ref.match(/github\.com\/([\w.-]+)\/([\w.-]+)\/issues\/(\d+)/i);
            const qualified = ref.match(/^([\w.-]+)\/([\w.-]+)#(\d+)$/);
            let target = same;
            let number;
            if (url) {
                target = `${url[1]}/${url[2]}`.toLowerCase();
                number = Number(url[3]);
            } else if (qualified) {
                target = `${qualified[1]}/${qualified[2]}`.toLowerCase();
                number = Number(qualified[3]);
            } else {
                number = Number(ref.slice(ref.indexOf('#') + 1));
            }
            if (target === same && Number.isSafeInteger(number) && number > 0) {
                numbers.add(number);
            }
        }
    }
    return [...numbers];
}

function createLogger(core) {
    const target = core || {};
    const pick = (name, fallback) => (typeof target[name] === 'function' ? target[name].bind(target) : fallback);
    return {
        info: pick('info', (m) => console.log(m)),
        warning: pick('warning', (m) => console.warn(`Avertissement : ${m}`)),
        error: pick('error', (m) => console.error(`Erreur : ${m}`)),
        startGroup: pick('startGroup', (m) => console.log(m)),
        endGroup: pick('endGroup', () => {}),
        setFailed: pick('setFailed', (m) => {
            console.error(m);
            process.exitCode = 1;
        }),
    };
}

function warnOnce(ctx, key, message) {
    if (!ctx.warned.has(key)) {
        ctx.warned.add(key);
        ctx.log.warning(message);
    }
}

function isTrue(value) {
    return value === true || String(value ?? '').trim().toLowerCase() === 'true';
}

/* ------------------------------------------------------------------------- */
/* Fichier .github/project-items.json                                        */
/* ------------------------------------------------------------------------- */

function loadConfig(filePath, log) {
    const shown = path.relative(process.cwd(), filePath) || filePath;
    let raw;
    try {
        raw = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    } catch (err) {
        throw new FatalError(`Impossible de lire ${shown} : ${clean(err.message, 200)}. Vérifiez que le fichier existe et que son JSON est valide.`);
    }
    const org = String(process.env.PROJECT_ORG || raw?.project?.org || '').trim();
    const number = Number(process.env.PROJECT_NUMBER || raw?.project?.number);
    if (!org || !Number.isSafeInteger(number) || number <= 0) {
        throw new FatalError(`${shown} doit indiquer le projet : "project": { "org": "Aurora-Coms", "number": 6 }.`);
    }

    const entries = [];
    const byIssue = new Map();
    const items = Array.isArray(raw.items) ? raw.items : [];
    items.forEach((item, index) => {
        const name = clean(item?.key || item?.title || `élément ${index + 1}`, 60);
        const where = `${shown}, « ${name} »`;
        const entry = {
            key: name,
            issue: Number.isSafeInteger(item?.issue) && item.issue > 0 ? item.issue : null,
            desired: {},
            estimateDerived: false,
            iterationTitle: null,
        };
        for (const key of ['status', 'priority', 'size']) {
            const value = item?.[key];
            if (value === undefined || value === null || value === '') {
                continue;
            }
            const canonical = canonicalValue(key, value);
            if (canonical) {
                entry.desired[key] = canonical;
            } else {
                log.warning(`${where} : ${FIELD_SPECS[key].label} « ${clean(value, 40)} » inconnu `
                    + `(valeurs possibles : ${Object.keys(OPTION_SPECS[key]).join(', ')}) : ignoré.`);
            }
        }
        if (item?.estimate !== undefined && item.estimate !== null) {
            if (typeof item.estimate === 'number' && Number.isFinite(item.estimate) && item.estimate >= 0) {
                entry.desired.estimate = item.estimate;
            } else {
                log.warning(`${where} : estimate doit être un nombre positif : ignoré.`);
            }
        } else if (entry.desired.size) {
            entry.desired.estimate = ESTIMATE_BY_SIZE[entry.desired.size];
            entry.estimateDerived = true;
        }
        for (const key of ['start', 'end']) {
            const value = item?.[key];
            if (value === undefined || value === null || value === '') {
                continue;
            }
            if (isValidDate(value)) {
                entry.desired[key] = value;
            } else {
                log.warning(`${where} : ${key} « ${clean(value, 40)} » n'est pas une date AAAA-MM-JJ valide : ignoré.`);
            }
        }
        if (entry.desired.start && entry.desired.end && entry.desired.start > entry.desired.end) {
            log.warning(`${where} : la date de début (${entry.desired.start}) est après la date de fin (${entry.desired.end}).`);
        }
        if (typeof item?.iteration === 'string' && item.iteration.trim() !== '') {
            entry.iterationTitle = item.iteration.trim();
        }
        if (entry.issue !== null) {
            if (byIssue.has(entry.issue)) {
                log.warning(`${where} : l'issue #${entry.issue} est déjà décrite par « ${byIssue.get(entry.issue).key} » : doublon ignoré.`);
                return;
            }
            byIssue.set(entry.issue, entry);
        }
        entries.push(entry);
    });
    return { org, number, entries, byIssue };
}

/* ------------------------------------------------------------------------- */
/* Appels à l'API                                                            */
/* ------------------------------------------------------------------------- */

async function waitForRateLimit(ctx, err, attempt) {
    const header = err?.response?.headers?.['retry-after'] ?? err?.headers?.['retry-after'];
    const seconds = Number(header);
    let delay = Number.isFinite(seconds) && seconds > 0 ? seconds * 1000 : 60000 * attempt;
    if (Number.isFinite(ctx.retryDelayMs)) {
        delay = ctx.retryDelayMs;
    }
    ctx.log.warning(`Limite de débit de l'API GitHub atteinte : nouvelle tentative dans ${Math.ceil(delay / 1000)} s.`);
    await ctx.sleep(delay);
}

/** Espace les écritures pour respecter les limites secondaires de GitHub. */
async function pace(ctx) {
    const wait = ctx.lastWriteAt + ctx.writeDelayMs - Date.now();
    if (ctx.lastWriteAt && wait > 0) {
        await ctx.sleep(wait);
    }
    ctx.lastWriteAt = Date.now();
}

/**
 * Requête GraphQL. Jeton refusé ou droit manquant : FatalError (inutile de continuer).
 * Limite de débit : attente puis nouvelle tentative. Autres erreurs : relancées à l'appelant.
 */
async function graphql(ctx, query, variables, { write = false } = {}) {
    for (let attempt = 1; ; attempt += 1) {
        if (write) {
            await pace(ctx);
        }
        try {
            const data = await ctx.github.graphql(query, variables);
            if (write) {
                ctx.stats.writes += 1;
            }
            return data;
        } catch (err) {
            const kind = classifyError(err);
            if (kind === 'ratelimit' && attempt < RATE_LIMIT_ATTEMPTS) {
                await waitForRateLimit(ctx, err, attempt);
                continue;
            }
            if (kind === 'auth') {
                throw new FatalError(MESSAGES.auth(err));
            }
            if (kind === 'permission') {
                throw new FatalError(write ? MESSAGES.writePermission(ctx, err) : MESSAGES.projectPermission(ctx, err));
            }
            throw err;
        }
    }
}

/** Appel REST (lecture du dépôt), mêmes règles que graphql(). Renvoie response.data. */
async function rest(ctx, call) {
    for (let attempt = 1; ; attempt += 1) {
        try {
            const response = await call();
            return response?.data;
        } catch (err) {
            const kind = classifyError(err);
            if (kind === 'ratelimit' && attempt < RATE_LIMIT_ATTEMPTS) {
                await waitForRateLimit(ctx, err, attempt);
                continue;
            }
            if (kind === 'auth') {
                throw new FatalError(MESSAGES.auth(err));
            }
            if (kind === 'permission') {
                throw new FatalError(MESSAGES.repoAccess(ctx, err));
            }
            throw err;
        }
    }
}

async function checkRepository(ctx) {
    try {
        return await rest(ctx, () => ctx.github.rest.repos.get({ owner: ctx.owner, repo: ctx.repo }));
    } catch (err) {
        if (err instanceof FatalError) {
            throw err;
        }
        if (classifyError(err) === 'notfound') {
            throw new FatalError(MESSAGES.repoAccess(ctx, err));
        }
        throw err;
    }
}

/** Projet de l'organisation (ou, à défaut, d'un compte personnel portant ce nom). */
async function resolveProject(ctx) {
    const variables = { login: ctx.config.org, number: ctx.config.number };
    for (const ownerType of ['organization', 'user']) {
        let data;
        try {
            data = await graphql(ctx, projectQuery(ownerType), variables);
        } catch (err) {
            if (err instanceof FatalError || classifyError(err) !== 'notfound') {
                throw err;
            }
            const ownerMissing = (err.errors || []).some((e) => Array.isArray(e?.path) && e.path.length === 1 && e.path[0] === ownerType);
            if (ownerMissing && ownerType === 'organization') {
                continue; // ce n'est pas une organisation : essai en tant qu'utilisateur
            }
            throw new FatalError(MESSAGES.notFound(ctx));
        }
        const project = data?.[ownerType]?.projectV2;
        if (project?.id) {
            return project;
        }
        if (data?.[ownerType]) {
            break;
        }
    }
    throw new FatalError(MESSAGES.notFound(ctx));
}

async function getIssue(ctx, number) {
    try {
        return await rest(ctx, () => ctx.github.rest.issues.get({ owner: ctx.owner, repo: ctx.repo, issue_number: number }));
    } catch (err) {
        if (!(err instanceof FatalError) && classifyError(err) === 'notfound') {
            ctx.log.warning(`Ticket #${number} introuvable dans ${ctx.owner}/${ctx.repo} (supprimé ou transféré ?) : ignoré.`);
            return null;
        }
        throw err;
    }
}

async function getPull(ctx, number, fallback) {
    try {
        return (await rest(ctx, () => ctx.github.rest.pulls.get({ owner: ctx.owner, repo: ctx.repo, pull_number: number }))) || fallback;
    } catch (err) {
        if (err instanceof FatalError) {
            throw err;
        }
        ctx.log.warning(`Lecture de la PR #${number} impossible (${describeError(err)}) : données de l'événement utilisées.`);
        return fallback;
    }
}

async function listOpenIssues(ctx) {
    const issues = [];
    for (let page = 1; page <= 50; page += 1) {
        const data = await rest(ctx, () => ctx.github.rest.issues.listForRepo({
            owner: ctx.owner,
            repo: ctx.repo,
            state: 'open',
            per_page: 100,
            page,
        }));
        const list = Array.isArray(data) ? data : [];
        issues.push(...list);
        if (list.length < 100) {
            break;
        }
    }
    return issues.filter((issue) => !issue.pull_request); // l'API renvoie aussi les PR
}

/** Tickets liés à la PR : ceux que GitHub connaît, plus ceux de la description (mots-clés français). */
async function linkedIssues(ctx, pr) {
    const numbers = new Set(parseClosingReferences(pr.body, ctx.owner, ctx.repo));
    if (pr.node_id) {
        try {
            const data = await graphql(ctx, CLOSING_QUERY, { id: pr.node_id });
            const same = `${ctx.owner}/${ctx.repo}`.toLowerCase();
            for (const node of data?.node?.closingIssuesReferences?.nodes || []) {
                if (String(node?.repository?.nameWithOwner || '').toLowerCase() === same && Number.isSafeInteger(node.number)) {
                    numbers.add(node.number);
                }
            }
        } catch (err) {
            if (err instanceof FatalError) {
                throw err;
            }
            ctx.log.warning(`Tickets liés à la PR #${pr.number} non lus par GitHub (${describeError(err)}) : seule la description est analysée.`);
        }
    }
    numbers.delete(pr.number);
    const sorted = [...numbers].sort((a, b) => a - b);
    if (sorted.length > MAX_LINKED_ISSUES) {
        ctx.log.warning(`La PR #${pr.number} cite ${sorted.length} tickets : seuls les ${MAX_LINKED_ISSUES} premiers sont traités.`);
    }
    return sorted.slice(0, MAX_LINKED_ISSUES);
}

/* ------------------------------------------------------------------------- */
/* Éléments du projet et champs                                              */
/* ------------------------------------------------------------------------- */

function parseItem(node) {
    const values = new Map();
    for (const value of node?.fieldValues?.nodes || []) {
        const fieldId = value?.field?.id;
        if (!fieldId) {
            continue;
        }
        if (value.optionId) {
            values.set(fieldId, { optionId: value.optionId, label: value.name || value.optionId });
        } else if (value.date) {
            values.set(fieldId, { date: value.date, label: value.date });
        } else if (typeof value.number === 'number') {
            values.set(fieldId, { number: value.number, label: String(value.number) });
        } else if (value.iterationId) {
            values.set(fieldId, { iterationId: value.iterationId, label: value.title || value.iterationId });
        }
    }
    return { id: node.id, isArchived: Boolean(node.isArchived), values };
}

async function findProjectItem(ctx, contentId) {
    const data = await graphql(ctx, ITEM_QUERY, { id: contentId });
    const node = (data?.node?.projectItems?.nodes || []).find((n) => n?.project?.id === ctx.project.id);
    return node ? parseItem(node) : null;
}

async function ensureItem(ctx, content) {
    if (!content?.node_id) {
        throw new Error('identifiant GraphQL (node_id) absent de la réponse de GitHub');
    }
    const existing = await findProjectItem(ctx, content.node_id);
    if (existing) {
        return { item: existing, created: false };
    }
    const data = await graphql(ctx, ADD_ITEM_MUTATION, { projectId: ctx.project.id, contentId: content.node_id }, { write: true });
    const id = data?.addProjectV2ItemById?.item?.id;
    if (!id) {
        throw new Error('GitHub n\'a pas renvoyé l\'identifiant de l\'élément ajouté');
    }
    ctx.stats.added += 1;
    return { item: { id, isArchived: false, values: new Map() }, created: true };
}

function reportMissingField(ctx, key, quiet) {
    const spec = FIELD_SPECS[key];
    if (quiet) {
        if (!ctx.warned.has(`field:${key}`)) {
            ctx.warned.add(`field:${key}`);
            ctx.log.info(`Champ « ${spec.label} » absent du projet : non rempli.`);
        }
        return;
    }
    const other = ctx.wrongType[key];
    const hint = other
        ? `Le champ « ${clean(other.name, 40)} » existe mais c'est un champ ${TYPE_LABELS[fieldType(other)] || fieldType(other) || 'd\'un autre type'} `
            + `au lieu de ${TYPE_LABELS[spec.types[0]]}.`
        : `Ajoutez-le dans le projet (⚙ Settings › Custom fields › New field) ou renommez le champ existant.`;
    warnOnce(ctx, `field:${key}`, `Champ « ${spec.label} » introuvable dans le projet (noms reconnus : ${spec.aliases.join(', ')}) : `
        + `valeur ignorée. ${hint}`);
}

function resolveOption(ctx, key, canonical, { silent = false } = {}) {
    const field = ctx.fields[key];
    const cacheKey = `${key}:${canonical}`;
    if (!ctx.optionCache.has(cacheKey)) {
        ctx.optionCache.set(cacheKey, matchByAliases(field?.options || [], OPTION_SPECS[key]?.[canonical] || [canonical]));
    }
    const option = ctx.optionCache.get(cacheKey);
    if (!option && !silent) {
        const available = (field?.options || []).map((o) => clean(o.name, 30)).join(', ') || 'aucune';
        warnOnce(ctx, `option:${cacheKey}`, `Option « ${canonical} » introuvable dans le champ « ${clean(field?.name, 40)} » `
            + `(options : ${available}) : valeur ignorée. Ajoutez-la dans les réglages du champ.`);
    }
    return option;
}

/** Le statut actuel de l'élément correspond-il à cette valeur de référence ? */
function statusIs(ctx, item, canonical) {
    const field = ctx.fields.status;
    const current = field ? item.values.get(field.id) : null;
    if (!current?.optionId) {
        return false;
    }
    const option = resolveOption(ctx, 'status', canonical, { silent: true });
    return Boolean(option && option.id === current.optionId);
}

function sameValue(current, stored) {
    return ['optionId', 'number', 'date', 'iterationId'].some((k) => stored[k] !== undefined && current[k] === stored[k]);
}

/**
 * Écrit un champ de l'élément.
 * mode « fill » : seulement s'il est vide (une valeur choisie dans le projet est conservée) ;
 * mode « overwrite » : remplace la valeur actuelle.
 * quiet : champ absent signalé en simple information (valeur déduite, pas demandée).
 * noteKept : signale dans le journal une valeur conservée (inutile pour une valeur par défaut).
 */
async function setField(ctx, item, rec, key, wanted, { mode = 'fill', quiet = false, noteKept = true } = {}) {
    const field = ctx.fields[key];
    if (!field) {
        reportMissingField(ctx, key, quiet);
        return 'absent';
    }
    let value;
    let stored;
    switch (field.dataType) {
        case 'SINGLE_SELECT': {
            const option = resolveOption(ctx, key, wanted);
            if (!option) {
                return 'absent';
            }
            value = { singleSelectOptionId: option.id };
            stored = { optionId: option.id, label: option.name };
            break;
        }
        case 'NUMBER':
            value = { number: wanted };
            stored = { number: wanted, label: String(wanted) };
            break;
        case 'DATE':
            value = { date: wanted };
            stored = { date: wanted, label: wanted };
            break;
        case 'ITERATION':
            value = { iterationId: wanted.id };
            stored = { iterationId: wanted.id, label: wanted.title };
            break;
        default:
            return 'absent';
    }
    const current = item.values.get(field.id);
    if (current && sameValue(current, stored)) {
        return 'unchanged';
    }
    if (current && mode === 'fill') {
        if (!noteKept) {
            return 'kept';
        }
        rec.kept.push(`${clean(field.name, 40)} conservé (« ${clean(current.label, 40)} » dans le projet, « ${clean(stored.label, 40)} » demandé)`);
        ctx.stats.kept += 1;
        return 'kept';
    }
    await graphql(ctx, UPDATE_FIELD_MUTATION, { projectId: ctx.project.id, itemId: item.id, fieldId: field.id, value }, { write: true });
    item.values.set(field.id, stored);
    rec.changes.push(`${clean(field.name, 40)} : ${clean(stored.label, 40)}`);
    ctx.stats.fields += 1;
    return 'set';
}

/** Itération à donner à un ticket du JSON : celle nommée, sinon celle qui contient sa date de début. */
function iterationFor(ctx, entry) {
    const field = ctx.fields.iteration;
    if (!field) {
        if (entry.iterationTitle) {
            reportMissingField(ctx, 'iteration', false);
        }
        return null;
    }
    const found = findIteration(field, entry.iterationTitle ? { title: entry.iterationTitle } : { date: entry.desired.start });
    if (!found && entry.iterationTitle) {
        warnOnce(ctx, `iteration:${normalize(entry.iterationTitle)}`, `Itération « ${clean(entry.iterationTitle, 40)} » introuvable dans `
            + `le champ « ${clean(field.name, 40)} » : valeur ignorée.`);
    }
    return found ? { id: found.id, title: found.title } : null;
}

/** Applique les champs d'un ticket du JSON. Ticket fermé : Status Done, quel que soit le mode. */
async function applyEntry(ctx, item, rec, entry, { mode, closed = false, skip = [] }) {
    const wanted = { ...entry.desired };
    const iteration = iterationFor(ctx, entry);
    if (iteration) {
        wanted.iteration = iteration;
    }
    if (closed && !skip.includes('status')) {
        if (wanted.status && wanted.status !== 'Done') {
            rec.notes.push(`ticket fermé : Done au lieu de ${wanted.status}`);
        }
        wanted.status = 'Done';
    }
    for (const key of FIELD_ORDER) {
        if (skip.includes(key) || wanted[key] === undefined || wanted[key] === null) {
            continue;
        }
        const quiet = (key === 'estimate' && entry.estimateDerived) || (key === 'iteration' && !entry.iterationTitle);
        await setField(ctx, item, rec, key, wanted[key], { mode: key === 'status' && closed ? 'overwrite' : mode, quiet });
    }
}

/** Ajoute le contenu au projet si besoin puis applique handler ; une erreur ne bloque que cet élément. */
async function processContent(ctx, content, kind, handler) {
    const rec = {
        ref: kind === 'pr' ? `PR #${content.number}` : `#${content.number}`,
        title: clean(content.title, 80),
        created: false,
        changes: [],
        kept: [],
        notes: [],
        error: null,
    };
    let aborted = false;
    try {
        const { item, created } = await ensureItem(ctx, content);
        rec.created = created;
        if (item.isArchived) {
            rec.notes.push('archivé dans le projet : ignoré (désarchivez-le pour le synchroniser de nouveau)');
            return;
        }
        await handler(item, rec, created);
    } catch (err) {
        if (err instanceof FatalError) {
            aborted = true; // erreur bloquante : signalée une seule fois, par le point d'entrée
            throw err;
        }
        rec.error = describeError(err);
        ctx.errors.push(`${rec.ref} : ${rec.error}`);
    } finally {
        if (!aborted) {
            ctx.report.push(rec);
            ctx.stats.processed += 1;
            const parts = [rec.created ? 'ajouté au projet' : null, ...rec.changes, ...rec.kept, ...rec.notes].filter(Boolean);
            if (rec.error) {
                ctx.log.error(`${rec.ref} « ${rec.title} » : échec (${rec.error})`);
            } else {
                ctx.log.info(`${rec.ref} « ${rec.title} » : ${parts.length ? parts.join(' · ') : 'déjà à jour'}`);
            }
        }
    }
}

/** Exécute fn ; une erreur non bloquante est notée et la synchronisation continue. */
async function safely(ctx, ref, fn) {
    try {
        await fn();
    } catch (err) {
        if (err instanceof FatalError) {
            throw err;
        }
        const message = `${ref} : ${describeError(err)}`;
        ctx.errors.push(message);
        ctx.log.error(message);
    }
}

/* ------------------------------------------------------------------------- */
/* Événements                                                                */
/* ------------------------------------------------------------------------- */

/** Synchronisation complète (bouton « Run workflow » ou planification). */
async function syncAll(ctx, force) {
    ctx.log.info(force
        ? 'Mode force : les champs de project-items.json remplacent ceux du projet (sauf Status d\'un ticket fermé : Done).'
        : 'Mode normal : seuls les champs vides sont remplis ; une valeur choisie dans le projet est conservée '
            + '(sauf Status d\'un ticket fermé : Done). Cochez « force » pour tout ré-appliquer.');

    const listed = new Set();
    ctx.log.startGroup(`Tickets de .github/project-items.json (${ctx.config.entries.length})`);
    try {
        for (const entry of ctx.config.entries) {
            if (!entry.issue) {
                ctx.log.warning(`« ${entry.key} » n'a pas de numéro d'issue (clé "issue") : ignoré.`);
                continue;
            }
            listed.add(entry.issue);
            await safely(ctx, `#${entry.issue}`, async () => {
                const issue = await getIssue(ctx, entry.issue);
                if (!issue) {
                    return;
                }
                const closed = issue.state === 'closed';
                await processContent(ctx, issue, issue.pull_request ? 'pr' : 'issue', (item, rec, created) =>
                    applyEntry(ctx, item, rec, entry, { mode: created || force ? 'overwrite' : 'fill', closed }));
            });
        }
    } finally {
        ctx.log.endGroup();
    }

    await safely(ctx, 'Autres issues ouvertes', async () => {
        const others = (await listOpenIssues(ctx)).filter((issue) => !listed.has(issue.number));
        ctx.log.startGroup(`Autres issues ouvertes du dépôt (${others.length})`);
        try {
            if (!others.length) {
                ctx.log.info('Aucune autre issue ouverte à ranger.');
            }
            for (const issue of others) {
                await processContent(ctx, issue, 'issue', async (item, rec) => {
                    await setField(ctx, item, rec, 'status', 'Backlog', { mode: 'fill', noteKept: false });
                    if (hasBugLabel(issue)) {
                        await setField(ctx, item, rec, 'priority', 'P1', { mode: 'fill', noteKept: false });
                    }
                });
            }
        } finally {
            ctx.log.endGroup();
        }
    });
}

/**
 * Événement « issues ». L'état est relu sur GitHub : si plusieurs événements arrivent
 * en rafale, le projet reflète l'état réel du ticket et non un événement périmé.
 */
async function onIssue(ctx) {
    const { action, issue: payloadIssue } = ctx.context.payload;
    const issue = await getIssue(ctx, payloadIssue.number);
    if (!issue) {
        return;
    }
    const entry = ctx.config.byIssue.get(issue.number);
    await processContent(ctx, issue, 'issue', async (item, rec) => {
        if (issue.state === 'closed') {
            await setField(ctx, item, rec, 'status', 'Done', { mode: 'overwrite' });
        } else if (action === 'reopened' || action === 'closed') {
            // « closed » alors que le ticket est ouvert : il a été rouvert depuis.
            await setField(ctx, item, rec, 'status', 'In progress', { mode: 'overwrite' });
        } else {
            await setField(ctx, item, rec, 'status', entry?.desired.status || 'Backlog', { mode: 'fill', noteKept: false });
        }
        // Champs complémentaires : uniquement s'ils sont vides.
        if (entry) {
            await applyEntry(ctx, item, rec, entry, { mode: 'fill', skip: ['status'] });
        }
        if (hasBugLabel(issue) && !entry?.desired.priority) {
            await setField(ctx, item, rec, 'priority', 'P1', { mode: 'fill', noteKept: false });
        }
    });
}

/** Événement « pull_request » ou « pull_request_target ». */
async function onPullRequest(ctx) {
    const payloadPr = ctx.context.payload.pull_request;
    const pr = await getPull(ctx, payloadPr.number, payloadPr);
    const issues = await linkedIssues(ctx, pr);
    const merged = Boolean(pr.merged || pr.merged_at);

    let prStatus;
    let issueStatus; // (item, issue) => valeur de Status à écrire, ou null pour ne rien changer
    let reason = '';
    if (pr.state === 'closed' && merged) {
        prStatus = 'Done';
        const intoDefault = !ctx.defaultBranch || !pr.base?.ref || pr.base.ref === ctx.defaultBranch;
        issueStatus = intoDefault ? () => 'Done' : () => null;
        if (!intoDefault) {
            reason = `fusionnée dans ${clean(pr.base.ref, 60)} et non dans ${ctx.defaultBranch} : tickets inchangés`;
        }
    } else if (pr.state === 'closed') {
        // Fermée sans fusion : la relecture est abandonnée, le travail reprend.
        prStatus = 'Done';
        issueStatus = (item) => (statusIs(ctx, item, 'In review') ? 'In progress' : null);
    } else {
        prStatus = pr.draft ? 'In progress' : 'In review';
        issueStatus = (item, issue) => (issue.state === 'closed' || statusIs(ctx, item, 'Done') ? null : prStatus);
    }

    await processContent(ctx, pr, 'pr', async (item, rec) => {
        await setField(ctx, item, rec, 'status', prStatus, { mode: 'overwrite' });
        if (issues.length) {
            rec.notes.push(`tickets liés : ${issues.map((n) => `#${n}`).join(', ')}`);
        }
        if (reason) {
            rec.notes.push(reason);
        }
    });

    for (const number of issues) {
        await safely(ctx, `#${number}`, async () => {
            const issue = await getIssue(ctx, number);
            if (!issue) {
                return;
            }
            if (issue.pull_request) {
                ctx.log.info(`#${number} est une PR et non un ticket : ignoré.`);
                return;
            }
            await processContent(ctx, issue, 'issue', async (item, rec) => {
                const status = issueStatus(item, issue);
                if (status) {
                    await setField(ctx, item, rec, 'status', status, { mode: 'overwrite' });
                } else {
                    rec.notes.push('statut inchangé');
                }
            });
        });
    }
}

/** Choisit le traitement de l'événement, sans appeler l'API (évite tout appel inutile). */
function planEvent(context) {
    const event = context?.eventName;
    const payload = context?.payload || {};
    const action = payload.action;
    if (event === 'workflow_dispatch' || event === 'schedule') {
        return { label: 'synchronisation complète', run: (ctx) => syncAll(ctx, isTrue(ctx.options.force) || isTrue(payload.inputs?.force) || isTrue(process.env.SYNC_FORCE)) };
    }
    if (event === 'issues') {
        if (!payload.issue?.number) {
            return { skip: 'Événement « issues » sans ticket : rien à faire.' };
        }
        if (!['opened', 'closed', 'reopened', 'labeled'].includes(action)) {
            return { skip: `Action « ${clean(action, 30)} » sur un ticket : rien à synchroniser.` };
        }
        if (action === 'labeled' && !isBugLabel(payload.label?.name)) {
            return { skip: `Étiquette « ${clean(payload.label?.name, 40)} » ajoutée : seule « bug » déclenche une synchronisation.` };
        }
        return { label: `ticket #${payload.issue.number} (${action})`, run: onIssue };
    }
    if (event === 'pull_request' || event === 'pull_request_target') {
        if (!payload.pull_request?.number) {
            return { skip: 'Événement de PR sans PR : rien à faire.' };
        }
        if (!['opened', 'reopened', 'ready_for_review', 'closed'].includes(action)) {
            return { skip: `Action « ${clean(action, 30)} » sur une PR : rien à synchroniser.` };
        }
        return { label: `PR #${payload.pull_request.number} (${action})`, run: onPullRequest };
    }
    return { skip: `Événement « ${clean(event, 40)} » non géré : rien à faire.` };
}

async function writeSummary(ctx, plan) {
    const summary = ctx.core?.summary;
    if (!summary || typeof summary.addHeading !== 'function' || !process.env.GITHUB_STEP_SUMMARY) {
        return;
    }
    try {
        const rows = ctx.report.map((rec) => [
            escapeHtml(`${rec.ref} ${rec.title}`),
            escapeHtml(rec.error
                ? `échec : ${rec.error}`
                : [rec.created ? 'ajouté au projet' : null, ...rec.changes, ...rec.kept, ...rec.notes].filter(Boolean).join(' · ') || 'déjà à jour'),
        ]);
        summary
            .addHeading('Synchronisation du projet GitHub', 2)
            .addRaw(`<p>${escapeHtml(plan.label)} · <a href="${escapeHtml(ctx.project.url)}">${escapeHtml(ctx.project.title)}</a> · `
                + `${ctx.stats.added} ajout(s), ${ctx.stats.fields} champ(s) modifié(s), ${ctx.stats.kept} valeur(s) conservée(s), `
                + `${ctx.errors.length} erreur(s)</p>`, true);
        if (rows.length) {
            summary.addTable([[{ data: 'Élément', header: true }, { data: 'Résultat', header: true }], ...rows]);
        }
        await summary.write();
    } catch (err) {
        ctx.log.warning(`Résumé de l'exécution non écrit : ${describeError(err)}`);
    }
}

/* ------------------------------------------------------------------------- */
/* Point d'entrée                                                            */
/* ------------------------------------------------------------------------- */

/**
 * @param {object} args { github, context, core } fournis par actions/github-script.
 *   options (facultatif, pour les tests) : itemsPath, force, sleep, writeDelayMs, retryDelayMs.
 * @returns {Promise<object>} bilan (la sortie « result » de github-script).
 */
async function syncProject({ github, context, core, options = {} } = {}) {
    const log = createLogger(core);
    const ctx = {
        github,
        context: context || {},
        core,
        log,
        options: options || {},
        owner: null,
        repo: null,
        config: null,
        project: null,
        fields: {},
        wrongType: {},
        defaultBranch: null,
        stats: { processed: 0, added: 0, fields: 0, kept: 0, writes: 0 },
        report: [],
        errors: [],
        warned: new Set(),
        optionCache: new Map(),
        lastWriteAt: 0,
        writeDelayMs: Number.isFinite(options?.writeDelayMs) ? options.writeDelayMs : Number(process.env.SYNC_WRITE_DELAY_MS ?? WRITE_DELAY_MS),
        retryDelayMs: options?.retryDelayMs,
        sleep: typeof options?.sleep === 'function' ? options.sleep : (ms) => new Promise((resolve) => setTimeout(resolve, ms)),
    };
    if (!Number.isFinite(ctx.writeDelayMs) || ctx.writeDelayMs < 0) {
        ctx.writeDelayMs = WRITE_DELAY_MS;
    }

    const plan = planEvent(ctx.context);
    if (plan.skip) {
        log.info(plan.skip);
        return { ok: true, skipped: true };
    }

    try {
        if (!github || typeof github.graphql !== 'function' || !github.rest) {
            throw new FatalError(MESSAGES.noToken());
        }
        const repository = ctx.context.repo || {};
        ctx.owner = repository.owner;
        ctx.repo = repository.repo;
        if (!ctx.owner || !ctx.repo) {
            throw new FatalError('Dépôt courant inconnu : ce script doit être lancé par GitHub Actions (variable GITHUB_REPOSITORY).');
        }
        ctx.config = loadConfig(ctx.options.itemsPath || process.env.PROJECT_ITEMS_PATH || ITEMS_FILE, log);

        const repoData = await checkRepository(ctx);
        ctx.defaultBranch = repoData?.default_branch || ctx.context.payload?.repository?.default_branch || null;

        ctx.project = await resolveProject(ctx);
        const { resolved, wrongType } = resolveFields(ctx.project.fields?.nodes);
        ctx.fields = resolved;
        ctx.wrongType = wrongType;

        log.info(`Projet « ${clean(ctx.project.title, 80)} » trouvé : ${clean(ctx.project.url, 200)}`);
        if (ctx.project.closed) {
            log.warning('Ce projet est fermé (Closed) : rouvrez-le si les modifications sont refusées.');
        }
        const known = FIELD_ORDER.filter((k) => resolved[k]).map((k) => `${FIELD_SPECS[k].label} ⇒ « ${clean(resolved[k].name, 40)} »`);
        const absent = FIELD_ORDER.filter((k) => !resolved[k]).map((k) => FIELD_SPECS[k].label);
        log.info(`Champs reconnus : ${known.join(', ') || 'aucun'}${absent.length ? ` · absents : ${absent.join(', ')}` : ''}`);
        log.info(`Traitement : ${plan.label}.`);

        await plan.run(ctx);

        log.info(`Bilan : ${ctx.stats.processed} élément(s) traité(s), ${ctx.stats.added} ajout(s), `
            + `${ctx.stats.fields} champ(s) modifié(s), ${ctx.stats.kept} valeur(s) conservée(s), ${ctx.errors.length} erreur(s).`);
        await writeSummary(ctx, plan);
        if (ctx.errors.length) {
            log.setFailed(`${ctx.errors.length} élément(s) n'ont pas pu être synchronisés : ${ctx.errors.slice(0, 5).join(' | ')}`);
        }
        return { ok: ctx.errors.length === 0, ...ctx.stats, errors: ctx.errors.length };
    } catch (err) {
        const message = err instanceof FatalError
            ? err.message
            : `Erreur inattendue pendant la synchronisation : ${describeError(err)}`;
        log.setFailed(message);
        return { ok: false, error: message, ...ctx.stats };
    }
}

module.exports = syncProject;
/** Fonctions internes exposées pour tests/project-sync.test.js. */
module.exports.internals = {
    normalize,
    matchByAliases,
    canonicalValue,
    resolveFields,
    findIteration,
    parseClosingReferences,
    classifyError,
    isBugLabel,
    loadConfig,
    FIELD_SPECS,
    OPTION_SPECS,
    ESTIMATE_BY_SIZE,
};
