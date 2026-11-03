/**
 * Tests de .github/scripts/sync-project.js (synchronisation du projet GitHub).
 *
 * Aucune dépendance : node:test et node:assert, fournis par Node.js 18 ou plus.
 * Lancement : node tests/project-sync.test.js
 *
 * Le script reçoit un faux objet « github » : GraphQL simulé (projet, champs aux
 * noms variés, éléments, mutations enregistrées) et API REST simulée (issues, PR).
 * Aucun appel réseau n'est fait.
 */

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

delete process.env.SYNC_FORCE;
delete process.env.PROJECT_ITEMS_PATH;
delete process.env.PROJECT_ORG;
delete process.env.PROJECT_NUMBER;
delete process.env.GITHUB_STEP_SUMMARY;

const ROOT = path.join(__dirname, '..');
const sync = require(path.join(ROOT, '.github', 'scripts', 'sync-project.js'));
const { internals } = sync;

const OWNER = 'Aurora-Coms';
const REPO = 'Portfolio-Generale';
const PROJECT_ID = 'PVT_portfolio';

/* ------------------------------------------------------------------------- */
/* Jeux de champs                                                            */
/* ------------------------------------------------------------------------- */

function select(id, name, options) {
    return {
        __typename: 'ProjectV2SingleSelectField',
        id,
        name,
        dataType: 'SINGLE_SELECT',
        options: options.map((o) => ({ id: `${id}:${o}`, name: o })),
    };
}
function plain(id, name, dataType) {
    return { __typename: 'ProjectV2Field', id, name, dataType };
}
function iteration(id, name) {
    return {
        __typename: 'ProjectV2IterationField',
        id,
        name,
        dataType: 'ITERATION',
        configuration: {
            iterations: [
                { id: 'it-1', title: 'Itération 1', startDate: '2026-11-02', duration: 14 },
                { id: 'it-2', title: 'Itération 2', startDate: '2026-11-16', duration: 14 },
            ],
            completedIterations: [{ id: 'it-0', title: 'Itération 0', startDate: '2026-10-19', duration: 14 }],
        },
    };
}
function builtIns() {
    return [
        plain('f-title', 'Title', 'TITLE'),
        plain('f-assignees', 'Assignees', 'ASSIGNEES'),
        plain('f-labels', 'Labels', 'LABELS'),
        plain('f-linked', 'Linked pull requests', 'LINKED_PULL_REQUESTS'),
        plain('f-repo', 'Repository', 'REPOSITORY'),
    ];
}

/** Champs du modèle « Team planning ». */
function teamPlanningFields() {
    return [
        ...builtIns(),
        select('f-status', 'Status', ['Backlog', 'Ready', 'In progress', 'In review', 'Done']),
        select('f-priority', 'Priority', ['P0', 'P1', 'P2']),
        select('f-size', 'Size', ['XS', 'S', 'M', 'L', 'XL']),
        plain('f-estimate', 'Estimate', 'NUMBER'),
        iteration('f-iteration', 'Iteration'),
        plain('f-start', 'Start date', 'DATE'),
        plain('f-end', 'End date', 'DATE'),
    ];
}

/** Champs renommés en français, avec emojis, et un piège de type (« Estimate » en texte). */
function variedFields() {
    return [
        ...builtIns(),
        select('f-statut', 'Statut', ['📋 À faire', '🏗 En cours', '👀 En revue', '✅ Terminé']),
        select('f-prio', 'Priorité', ['P0 🔥', 'P1', 'P2']),
        select('f-taille', 'Taille', ['XS', 'S', 'M', 'L', 'XL']),
        plain('f-estimate-text', 'Estimate', 'TEXT'),
        plain('f-estimation', 'Estimation', 'NUMBER'),
        plain('f-debut', 'Date de début', 'DATE'),
        plain('f-due', 'Due date', 'DATE'),
        iteration('f-sprint', 'Sprint'),
    ];
}

/** Champs incomplets : pas de Size, pas d'option P2, Estimate en texte. */
function incompleteFields() {
    return [
        ...builtIns(),
        select('f-status', 'Status', ['Backlog', 'Ready', 'In progress', 'In review', 'Done']),
        select('f-priority', 'Priority', ['P0', 'P1']),
        plain('f-estimate', 'Estimate', 'TEXT'),
        plain('f-start', 'Start date', 'DATE'),
        plain('f-end', 'Target date', 'DATE'),
    ];
}

/* ------------------------------------------------------------------------- */
/* Faux GitHub                                                               */
/* ------------------------------------------------------------------------- */

function httpError(status, message, extra = {}) {
    return Object.assign(new Error(message), { status, ...extra });
}
function graphqlError(type, message, pathParts) {
    return Object.assign(new Error(`Request failed due to following response errors:\n - ${message}`), {
        errors: [{ type, message, path: pathParts }],
    });
}

function issue(number, { state = 'open', labels = [], title = `Ticket ${number}`, pr = false } = {}) {
    const data = { number, node_id: `I_${number}`, title, state, labels: labels.map((name) => ({ name })) };
    if (pr) {
        data.node_id = `PR_${number}`;
        data.pull_request = { url: `https://api.github.com/repos/${OWNER}/${REPO}/pulls/${number}` };
    }
    return data;
}

function pull(number, { state = 'open', draft = false, merged = false, body = '', base = 'main', closing = [] } = {}) {
    return {
        number,
        node_id: `PR_${number}`,
        title: `PR ${number}`,
        state,
        draft,
        merged,
        merged_at: merged ? '2026-11-06T10:00:00Z' : null,
        body,
        base: { ref: base },
        closing,
    };
}

/**
 * @param {object} o
 *   fields : champs du projet ; issues / pulls : données REST ;
 *   existing : éléments déjà présents { content, values: { nomDuChamp: valeur }, archived } ;
 *   fail : { graphql(query, vars, state), repos, issues(number) } pour simuler des erreurs.
 */
function fakeGitHub({ fields = teamPlanningFields(), issues = [], pulls = [], existing = [], fail = {}, projectMissing = false } = {}) {
    const fieldById = new Map(fields.map((f) => [f.id, f]));
    const fieldByName = new Map(fields.map((f) => [f.name, f]));
    const issueMap = new Map(issues.map((i) => [i.number, i]));
    const pullMap = new Map(pulls.map((p) => [p.number, p]));
    const items = new Map(); // contentId => { id, isArchived, values: Map(fieldId => valeur) }
    const calls = { adds: [], updates: [], graphql: 0, rest: 0, updateAttempts: 0 };
    let nextItem = 1;

    function storedValue(field, raw) {
        switch (field.dataType) {
            case 'SINGLE_SELECT': {
                const option = field.options.find((o) => o.name === raw);
                assert.ok(option, `option de test « ${raw} » absente de « ${field.name} »`);
                return { optionId: option.id };
            }
            case 'DATE':
                return { date: raw };
            case 'NUMBER':
                return { number: raw };
            case 'ITERATION':
                return { iterationId: raw };
            default:
                throw new Error(`type de test non géré : ${field.dataType}`);
        }
    }
    for (const e of existing) {
        const values = new Map();
        for (const [name, raw] of Object.entries(e.values || {})) {
            const field = fieldByName.get(name);
            assert.ok(field, `champ de test « ${name} » absent`);
            values.set(field.id, storedValue(field, raw));
        }
        items.set(e.content, { id: `PVTI_${nextItem++}`, isArchived: Boolean(e.archived), values });
    }

    function valueNode(fieldId, value) {
        const field = fieldById.get(fieldId);
        if (value.optionId) {
            const option = field.options.find((o) => o.id === value.optionId);
            return { __typename: 'ProjectV2ItemFieldSingleSelectValue', optionId: value.optionId, name: option.name, field: { id: fieldId } };
        }
        if (value.date) {
            return { __typename: 'ProjectV2ItemFieldDateValue', date: value.date, field: { id: fieldId } };
        }
        if (value.number !== undefined) {
            return { __typename: 'ProjectV2ItemFieldNumberValue', number: value.number, field: { id: fieldId } };
        }
        const it = [...field.configuration.iterations, ...field.configuration.completedIterations].find((i) => i.id === value.iterationId);
        return { __typename: 'ProjectV2ItemFieldIterationValue', iterationId: value.iterationId, title: it.title, field: { id: fieldId } };
    }

    async function graphql(query, vars) {
        calls.graphql += 1;
        if (fail.graphql) {
            fail.graphql(query, vars, calls);
        }
        if (query.includes('projectV2(number')) {
            const ownerType = query.includes('organization(login') ? 'organization' : 'user';
            if (projectMissing) {
                throw graphqlError('NOT_FOUND', `Could not resolve to a ProjectV2 with the number ${vars.number}.`, [ownerType, 'projectV2']);
            }
            return { [ownerType]: { projectV2: { id: PROJECT_ID, title: 'Portfolio', url: `https://github.com/orgs/${OWNER}/projects/6`, closed: false, fields: { nodes: fields } } } };
        }
        if (query.includes('addProjectV2ItemById')) {
            assert.equal(vars.projectId, PROJECT_ID);
            calls.adds.push(vars.contentId);
            if (!items.has(vars.contentId)) {
                items.set(vars.contentId, { id: `PVTI_${nextItem++}`, isArchived: false, values: new Map() });
            }
            return { addProjectV2ItemById: { item: { id: items.get(vars.contentId).id } } };
        }
        if (query.includes('updateProjectV2ItemFieldValue')) {
            calls.updateAttempts += 1;
            if (fail.update) {
                fail.update(vars, calls);
            }
            assert.equal(vars.projectId, PROJECT_ID);
            const entry = [...items.entries()].find(([, item]) => item.id === vars.itemId);
            assert.ok(entry, `élément inconnu ${vars.itemId}`);
            const field = fieldById.get(vars.fieldId);
            assert.ok(field, `champ inconnu ${vars.fieldId}`);
            const keys = Object.keys(vars.value);
            assert.equal(keys.length, 1, 'une seule valeur par mutation');
            const expectedKey = { SINGLE_SELECT: 'singleSelectOptionId', DATE: 'date', NUMBER: 'number', ITERATION: 'iterationId' }[field.dataType];
            assert.equal(keys[0], expectedKey, `valeur ${keys[0]} pour un champ ${field.dataType}`);
            let label;
            let stored;
            if (field.dataType === 'SINGLE_SELECT') {
                const option = field.options.find((o) => o.id === vars.value.singleSelectOptionId);
                assert.ok(option, 'option inconnue');
                label = option.name;
                stored = { optionId: option.id };
            } else if (field.dataType === 'ITERATION') {
                label = vars.value.iterationId;
                stored = { iterationId: vars.value.iterationId };
            } else {
                label = vars.value[expectedKey];
                stored = { [expectedKey]: label };
            }
            entry[1].values.set(field.id, stored);
            calls.updates.push({ contentId: entry[0], field: field.name, value: label });
            return { updateProjectV2ItemFieldValue: { projectV2Item: { id: vars.itemId } } };
        }
        if (query.includes('closingIssuesReferences')) {
            const pr = [...pullMap.values()].find((p) => p.node_id === vars.id);
            return {
                node: {
                    closingIssuesReferences: {
                        nodes: (pr?.closing || []).map((ref) => (typeof ref === 'number'
                            ? { number: ref, repository: { nameWithOwner: `${OWNER}/${REPO}` } }
                            : ref)),
                    },
                },
            };
        }
        if (query.includes('projectItems')) {
            const item = items.get(vars.id);
            const nodes = [
                // Élément d'un autre projet : doit être ignoré.
                { id: 'PVTI_other', isArchived: false, project: { id: 'PVT_other' }, fieldValues: { nodes: [] } },
            ];
            if (item) {
                nodes.push({
                    id: item.id,
                    isArchived: item.isArchived,
                    project: { id: PROJECT_ID },
                    fieldValues: { nodes: [...item.values.entries()].map(([fid, v]) => valueNode(fid, v)) },
                });
            }
            return { node: { __typename: vars.id.startsWith('PR_') ? 'PullRequest' : 'Issue', projectItems: { nodes } } };
        }
        throw new Error(`requête GraphQL inattendue : ${query.slice(0, 80)}`);
    }

    const github = {
        graphql,
        rest: {
            repos: {
                get: async () => {
                    calls.rest += 1;
                    if (fail.repos) {
                        fail.repos();
                    }
                    return { data: { full_name: `${OWNER}/${REPO}`, default_branch: 'main' } };
                },
            },
            issues: {
                get: async ({ owner, repo, issue_number: number }) => {
                    calls.rest += 1;
                    assert.equal(owner, OWNER);
                    assert.equal(repo, REPO);
                    if (fail.issues) {
                        fail.issues(number);
                    }
                    const data = issueMap.get(number) || (pullMap.has(number) ? issue(number, { pr: true }) : null);
                    if (!data) {
                        throw httpError(404, 'Not Found');
                    }
                    return { data };
                },
                listForRepo: async ({ state, page, per_page: perPage }) => {
                    calls.rest += 1;
                    assert.equal(state, 'open');
                    const open = [...issueMap.values()].filter((i) => i.state === 'open');
                    return { data: open.slice((page - 1) * perPage, page * perPage) };
                },
            },
            pulls: {
                get: async ({ pull_number: number }) => {
                    calls.rest += 1;
                    const data = pullMap.get(number);
                    if (!data) {
                        throw httpError(404, 'Not Found');
                    }
                    return { data };
                },
            },
        },
    };

    return {
        github,
        calls,
        items,
        /** Valeurs écrites pour un contenu : { nomDuChamp: valeur }. */
        written(contentId) {
            return Object.fromEntries(calls.updates.filter((u) => u.contentId === contentId).map((u) => [u.field, u.value]));
        },
    };
}

function fakeCore() {
    const logs = { info: [], warning: [], error: [], failed: [] };
    return {
        logs,
        info: (m) => logs.info.push(m),
        warning: (m) => logs.warning.push(m),
        error: (m) => logs.error.push(m),
        setFailed: (m) => logs.failed.push(m),
        startGroup: () => {},
        endGroup: () => {},
    };
}

/* ------------------------------------------------------------------------- */
/* Fichier de tickets de test                                                */
/* ------------------------------------------------------------------------- */

const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'project-sync-test-'));
process.on('exit', () => fs.rmSync(tmp, { recursive: true, force: true }));

const FIXTURE = path.join(tmp, 'project-items.json');
fs.writeFileSync(FIXTURE, JSON.stringify({
    project: { org: OWNER, number: 6 },
    items: [
        { key: 'noyau', title: 'Noyau', status: 'Done', priority: 'P0', size: 'L', start: '2026-11-03', end: '2026-11-04', issue: 1 },
        { key: 'contenu', title: 'Contenu', status: 'Backlog', priority: 'P1', size: 'M', start: '2026-11-20', end: '2026-11-27', issue: 2 },
        { key: 'revue', title: 'Revue', status: 'In progress', priority: 'P0', size: 'S', estimate: 4, start: '2026-11-04', end: '2026-11-05', issue: 3 },
        { key: 'pret', title: 'Prêt', status: 'Ready', priority: 'P2', size: 'XS', iteration: 'Itération 2', issue: 4 },
        { key: 'sans-numero', title: 'Pas encore créé', status: 'Backlog' },
    ],
}));

/** Issues du dépôt : #1 à #4 dans le JSON (#2 fermée), #5 bug et #6 hors JSON, #7 PR, #8 fermée. */
function repoIssues(overrides = {}) {
    const list = [
        issue(1),
        issue(2, { state: 'closed' }),
        issue(3),
        issue(4),
        issue(5, { labels: ['bug'] }),
        issue(6, { labels: ['documentation'] }),
        issue(7, { pr: true }),
        issue(8, { state: 'closed' }),
    ];
    return list.map((i) => (overrides[i.number] ? { ...i, ...overrides[i.number] } : i));
}

async function run(fake, event, payload = {}, { core = fakeCore(), options = {} } = {}) {
    const sleeps = [];
    const context = { eventName: event, payload, repo: { owner: OWNER, repo: REPO } };
    const result = await sync({
        github: fake ? fake.github : undefined,
        context,
        core,
        options: { itemsPath: FIXTURE, writeDelayMs: 0, retryDelayMs: 0, sleep: async (ms) => sleeps.push(ms), ...options },
    });
    return { result, logs: core.logs, sleeps };
}

const ALL_TEAM_FIELDS = (status, priority, size, estimate, start, end, iterationId) => {
    const expected = { Status: status, Priority: priority, Size: size, Estimate: estimate, 'Start date': start, 'End date': end, Iteration: iterationId };
    return Object.fromEntries(Object.entries(expected).filter(([, v]) => v !== undefined));
};

/* ------------------------------------------------------------------------- */
/* Correspondance des noms                                                   */
/* ------------------------------------------------------------------------- */

test('noms : casse, accents, emojis et alias', () => {
    const { normalize, matchByAliases, canonicalValue } = internals;
    assert.equal(normalize('  🏗 In-Progress '), 'in progress');
    assert.equal(normalize('Échéance'), 'echeance');
    assert.equal(canonicalValue('status', 'en cours'), 'In progress');
    assert.equal(canonicalValue('status', 'TODO'), 'Backlog');
    assert.equal(canonicalValue('status', 'Terminé'), 'Done');
    assert.equal(canonicalValue('size', 'xl'), 'XL');
    assert.equal(canonicalValue('priority', 'P0 🔥'), 'P0');
    assert.equal(canonicalValue('status', 'Bloqué'), null);
    const options = [{ name: 'P10' }, { name: 'P1 urgent' }];
    assert.equal(matchByAliases(options, ['P1']).name, 'P1 urgent', '« P10 » ne doit pas passer pour « P1 »');
    assert.equal(matchByAliases([{ name: 'To do' }], ['Todo']).name, 'To do');
});

test('champs : alias et contrôle du type', () => {
    const { resolved, wrongType } = internals.resolveFields(variedFields());
    assert.equal(resolved.status.name, 'Statut');
    assert.equal(resolved.priority.name, 'Priorité');
    assert.equal(resolved.size.name, 'Taille');
    assert.equal(resolved.estimate.name, 'Estimation', 'le champ texte « Estimate » doit être écarté');
    assert.equal(resolved.start.name, 'Date de début');
    assert.equal(resolved.end.name, 'Due date');
    assert.equal(resolved.iteration.name, 'Sprint');
    assert.deepEqual(wrongType, {});

    const other = internals.resolveFields([plain('a', 'Start', 'DATE'), plain('b', 'Target date', 'DATE'), plain('c', 'Fin', 'TEXT')]);
    assert.equal(other.resolved.start.name, 'Start');
    assert.equal(other.resolved.end.name, 'Target date');

    const incomplete = internals.resolveFields(incompleteFields());
    assert.equal(incomplete.resolved.size, undefined);
    assert.equal(incomplete.resolved.estimate, undefined);
    assert.equal(incomplete.wrongType.estimate.name, 'Estimate');
});

test('itérations : par titre ou par date de début', () => {
    const field = iteration('f', 'Iteration');
    assert.equal(internals.findIteration(field, { date: '2026-11-02' }).id, 'it-1');
    assert.equal(internals.findIteration(field, { date: '2026-11-15' }).id, 'it-1');
    assert.equal(internals.findIteration(field, { date: '2026-11-16' }).id, 'it-2');
    assert.equal(internals.findIteration(field, { date: '2026-10-20' }).id, 'it-0');
    assert.equal(internals.findIteration(field, { date: '2027-01-01' }), null);
    assert.equal(internals.findIteration(field, { title: 'itération 2' }).id, 'it-2');
});

test('références « Closes #n » dans la description d\'une PR', () => {
    const body = [
        'Closes #3, #4 et #5',
        'Résout #6 ; fixes: #7',
        'RÉSOUT #15, corrige #16, ferme Aurora-Coms/Portfolio-Generale#17',
        'resolves https://github.com/aurora-coms/portfolio-generale/issues/13',
        'Fixes autre/depot#9 et https://github.com/autre/depot/issues/10',
        '<!-- Closes #11 (modèle de PR) -->',
        'Exemple : `Closes #12` et ```\nFixes #14\n```',
        'Voir #18 · préfixecloses #19',
    ].join('\n');
    assert.deepEqual(internals.parseClosingReferences(body, OWNER, REPO).sort((a, b) => a - b), [3, 4, 5, 6, 7, 13, 15, 16, 17]);
    assert.deepEqual(internals.parseClosingReferences(null, OWNER, REPO), []);
});

test('étiquette bug', () => {
    assert.ok(internals.isBugLabel('bug'));
    assert.ok(internals.isBugLabel('🐛 Bug'));
    assert.ok(internals.isBugLabel('type: bug'));
    assert.ok(internals.isBugLabel('Bogue'));
    assert.ok(!internals.isBugLabel('debug'));
    assert.ok(!internals.isBugLabel('documentation'));
});

/* ------------------------------------------------------------------------- */
/* Synchronisation complète                                                  */
/* ------------------------------------------------------------------------- */

test('dispatch : ajoute les tickets du JSON et remplit tous les champs', async () => {
    const fake = fakeGitHub({ issues: repoIssues() });
    const { result, logs } = await run(fake, 'workflow_dispatch', { inputs: { force: false } });

    assert.deepEqual(logs.failed, []);
    assert.equal(result.ok, true);
    assert.deepEqual(fake.calls.adds, ['I_1', 'I_2', 'I_3', 'I_4', 'I_5', 'I_6'], 'ni la PR #7 ni le ticket fermé #8 hors JSON');
    assert.deepEqual(fake.written('I_1'), ALL_TEAM_FIELDS('Done', 'P0', 'L', 5, '2026-11-03', '2026-11-04', 'it-1'));
    assert.deepEqual(fake.written('I_2'), ALL_TEAM_FIELDS('Done', 'P1', 'M', 3, '2026-11-20', '2026-11-27', 'it-2'), 'ticket fermé : Done');
    assert.deepEqual(fake.written('I_3'), ALL_TEAM_FIELDS('In progress', 'P0', 'S', 4, '2026-11-04', '2026-11-05', 'it-1'), 'estimate explicite');
    assert.deepEqual(fake.written('I_4'), ALL_TEAM_FIELDS('Ready', 'P2', 'XS', 1, undefined, undefined, 'it-2'), 'itération par titre');
    assert.deepEqual(fake.written('I_5'), { Status: 'Backlog', Priority: 'P1' }, 'issue hors JSON avec « bug »');
    assert.deepEqual(fake.written('I_6'), { Status: 'Backlog' });
    assert.ok(logs.warning.some((m) => m.includes('sans-numero')), 'élément sans numéro signalé');
    assert.equal(logs.warning.length, 1, `avertissements inattendus : ${logs.warning.join(' | ')}`);
    assert.ok(logs.info.some((m) => m.includes('ticket fermé : Done au lieu de Backlog')));

    // Deuxième passage : tout est déjà à jour, aucune écriture.
    const before = fake.calls.updates.length;
    const again = await run(fake, 'workflow_dispatch', {});
    assert.equal(again.result.ok, true);
    assert.equal(fake.calls.updates.length, before, 'idempotent');
    assert.equal(fake.calls.adds.length, 6, 'aucun nouvel ajout');
});

test('dispatch : champs et options aux noms variés (français, emojis)', async () => {
    const fake = fakeGitHub({ fields: variedFields(), issues: repoIssues() });
    const { logs } = await run(fake, 'workflow_dispatch', {});
    assert.deepEqual(logs.failed, []);
    assert.deepEqual(logs.warning.filter((m) => !m.includes('sans-numero')), []);
    assert.deepEqual(fake.written('I_1'), {
        Statut: '✅ Terminé', 'Priorité': 'P0 🔥', Taille: 'L', Estimation: 5, 'Date de début': '2026-11-03', 'Due date': '2026-11-04', Sprint: 'it-1',
    });
    assert.equal(fake.written('I_3').Statut, '🏗 En cours');
    assert.equal(fake.written('I_4').Statut, '📋 À faire', 'Ready ⇒ À faire');
    assert.equal(fake.written('I_6').Statut, '📋 À faire', 'Backlog ⇒ À faire');
    assert.equal(fake.calls.updates.some((u) => u.field === 'Estimate'), false, 'jamais dans le champ texte');
});

test('dispatch sans force : valeurs choisies à la main conservées, sauf Done si fermé', async () => {
    const fake = fakeGitHub({
        issues: repoIssues(),
        existing: [
            {
                content: 'I_1',
                values: { Status: 'Done', Priority: 'P0', Size: 'L', Estimate: 5, 'Start date': '2026-11-03', 'End date': '2026-11-04', Iteration: 'it-1' },
            },
            { content: 'I_2', values: { Status: 'In progress' } },
            { content: 'I_3', values: { Status: 'In review', Priority: 'P2' } },
            { content: 'I_4', values: { Status: 'Done' }, archived: true },
        ],
    });
    const { result, logs } = await run(fake, 'workflow_dispatch', { inputs: { force: 'false' } });

    assert.equal(result.ok, true);
    assert.deepEqual(fake.calls.adds, ['I_5', 'I_6'], 'les éléments présents ne sont pas ré-ajoutés');
    assert.deepEqual(fake.written('I_1'), {}, 'déjà à jour');
    assert.deepEqual(fake.written('I_2'), ALL_TEAM_FIELDS('Done', 'P1', 'M', 3, '2026-11-20', '2026-11-27', 'it-2'), 'fermé : Done remplace In progress');
    assert.deepEqual(fake.written('I_3'), { Size: 'S', Estimate: 4, 'Start date': '2026-11-04', 'End date': '2026-11-05', Iteration: 'it-1' },
        'Status et Priority choisis à la main conservés, champs vides remplis');
    assert.deepEqual(fake.written('I_4'), {}, 'élément archivé ignoré');
    assert.ok(logs.info.some((m) => m.startsWith('#3') && m.includes('Status conservé (« In review » dans le projet, « In progress » demandé)')));
    assert.ok(logs.info.some((m) => m.startsWith('#4') && m.includes('archivé')));
});

test('dispatch avec force : les champs du JSON remplacent ceux du projet', async () => {
    const fake = fakeGitHub({
        issues: repoIssues(),
        existing: [{ content: 'I_3', values: { Status: 'In review', Priority: 'P2', Size: 'XL' } }],
    });
    const { result } = await run(fake, 'workflow_dispatch', { inputs: { force: 'true' } });
    assert.equal(result.ok, true);
    assert.deepEqual(fake.written('I_3'), ALL_TEAM_FIELDS('In progress', 'P0', 'S', 4, '2026-11-04', '2026-11-05', 'it-1'));

    // Même résultat avec la variable d'environnement transmise par le workflow.
    const fake2 = fakeGitHub({ issues: repoIssues(), existing: [{ content: 'I_3', values: { Status: 'In review' } }] });
    process.env.SYNC_FORCE = 'true';
    try {
        await run(fake2, 'workflow_dispatch', {});
    } finally {
        delete process.env.SYNC_FORCE;
    }
    assert.equal(fake2.written('I_3').Status, 'In progress');
});

test('champ ou option manquant : avertissement unique et on continue', async () => {
    const fake = fakeGitHub({ fields: incompleteFields(), issues: repoIssues() });
    const { result, logs } = await run(fake, 'workflow_dispatch', {});

    assert.equal(result.ok, true, 'un champ manquant n\'est pas une erreur');
    assert.deepEqual(logs.failed, []);
    const sizeWarnings = logs.warning.filter((m) => m.includes('« Size » introuvable'));
    assert.equal(sizeWarnings.length, 1, 'un seul avertissement pour Size, pas un par ticket');
    assert.ok(logs.warning.some((m) => m.includes('Option « P2 » introuvable')), 'option manquante signalée');
    assert.ok(!logs.warning.some((m) => m.includes('Estimate')), 'Estimate déduite de Size : simple information');
    assert.ok(logs.info.some((m) => m.includes('« Estimate » absent')));
    assert.equal(logs.warning.filter((m) => m.includes('Iteration')).length, 1,
        'itération déduite de la date : pas d\'avertissement ; demandée par titre (#4) : un seul');
    assert.deepEqual(fake.written('I_1'), { Status: 'Done', Priority: 'P0', 'Start date': '2026-11-03', 'Target date': '2026-11-04' });
    assert.deepEqual(fake.written('I_4'), { Status: 'Ready' }, 'P2 absente : seule la valeur concernée est ignorée');
    assert.ok(logs.warning.some((m) => m.includes('« Iteration » introuvable')), 'itération demandée par titre : avertissement');
});

/* ------------------------------------------------------------------------- */
/* Événements issues                                                         */
/* ------------------------------------------------------------------------- */

test('issues opened : ajout en Backlog, P1 si bug', async () => {
    const fake = fakeGitHub({ issues: [issue(30, { labels: ['bug'] }), issue(31)] });
    const { result } = await run(fake, 'issues', { action: 'opened', issue: issue(30, { labels: ['bug'] }) });
    assert.equal(result.ok, true);
    assert.deepEqual(fake.calls.adds, ['I_30']);
    assert.deepEqual(fake.written('I_30'), { Status: 'Backlog', Priority: 'P1' });

    await run(fake, 'issues', { action: 'opened', issue: issue(31) });
    assert.deepEqual(fake.written('I_31'), { Status: 'Backlog' }, 'sans bug : pas de priorité');
});

test('issues opened d\'un ticket décrit dans le JSON : ses champs sont utilisés', async () => {
    const fake = fakeGitHub({ issues: repoIssues() });
    await run(fake, 'issues', { action: 'opened', issue: issue(3) });
    assert.deepEqual(fake.written('I_3'), ALL_TEAM_FIELDS('In progress', 'P0', 'S', 4, '2026-11-04', '2026-11-05', 'it-1'));
});

test('issues closed : Done (même si absent du projet)', async () => {
    const fake = fakeGitHub({
        issues: [issue(3, { state: 'closed' }), issue(40, { state: 'closed' })],
        existing: [{ content: 'I_3', values: { Status: 'In progress', Priority: 'P0' } }],
    });
    await run(fake, 'issues', { action: 'closed', issue: issue(3, { state: 'closed' }) });
    assert.deepEqual(fake.calls.adds, []);
    assert.equal(fake.written('I_3').Status, 'Done');
    assert.equal(fake.written('I_3').Priority, undefined, 'Priority choisie conservée');

    await run(fake, 'issues', { action: 'closed', issue: issue(40, { state: 'closed' }) });
    assert.deepEqual(fake.calls.adds, ['I_40']);
    assert.equal(fake.written('I_40').Status, 'Done');
});

test('issues reopened : In progress', async () => {
    const fake = fakeGitHub({ issues: [issue(41), issue(1)], existing: [{ content: 'I_41', values: { Status: 'Done', Priority: 'P2' } }] });
    await run(fake, 'issues', { action: 'reopened', issue: issue(41) });
    assert.deepEqual(fake.written('I_41'), { Status: 'In progress' });

    // Ticket décrit dans le JSON : Status In progress, champs vides complétés depuis le JSON.
    await run(fake, 'issues', { action: 'reopened', issue: issue(1) });
    assert.deepEqual(fake.written('I_1'), ALL_TEAM_FIELDS('In progress', 'P0', 'L', 5, '2026-11-03', '2026-11-04', 'it-1'));
});

test('issues : l\'état relu sur GitHub l\'emporte sur un événement périmé', async () => {
    // Événement « reopened » traité alors que le ticket a été refermé entre-temps.
    const fake = fakeGitHub({ issues: [issue(1, { state: 'closed' })], existing: [{ content: 'I_1', values: { Status: 'In progress' } }] });
    await run(fake, 'issues', { action: 'reopened', issue: issue(1) });
    assert.equal(fake.written('I_1').Status, 'Done');
});

test('issues labeled : « bug » ajoute si absent, autre étiquette ignorée', async () => {
    const fake = fakeGitHub({
        issues: [issue(50, { labels: ['bug'] }), issue(51, { labels: ['bug'] })],
        existing: [{ content: 'I_51', values: { Status: 'In progress' } }],
    });
    await run(fake, 'issues', { action: 'labeled', label: { name: 'bug' }, issue: issue(50, { labels: ['bug'] }) });
    assert.deepEqual(fake.calls.adds, ['I_50']);
    assert.deepEqual(fake.written('I_50'), { Status: 'Backlog', Priority: 'P1' });

    await run(fake, 'issues', { action: 'labeled', label: { name: '🐛 Bug' }, issue: issue(51, { labels: ['bug'] }) });
    assert.deepEqual(fake.calls.adds, ['I_50'], 'déjà présent : pas de nouvel ajout');
    assert.deepEqual(fake.written('I_51'), { Priority: 'P1' }, 'statut conservé, priorité vide remplie');

    const calls = fake.calls.graphql + fake.calls.rest;
    const { result, logs } = await run(fake, 'issues', { action: 'labeled', label: { name: 'documentation' }, issue: issue(50) });
    assert.equal(result.skipped, true);
    assert.equal(fake.calls.graphql + fake.calls.rest, calls, 'aucun appel à l\'API');
    assert.ok(logs.info[0].includes('documentation'));
});

/* ------------------------------------------------------------------------- */
/* Événements pull_request                                                   */
/* ------------------------------------------------------------------------- */

test('PR ouverte avec « Closes #3 » : PR et ticket en In review', async () => {
    const pr = pull(20, { body: 'Ajoute la page contact.\n\nCloses #3\n<!-- Fixes #4 -->', closing: [3] });
    const fake = fakeGitHub({ issues: repoIssues(), pulls: [pr], existing: [{ content: 'I_3', values: { Status: 'In progress' } }] });
    const { result, logs } = await run(fake, 'pull_request_target', { action: 'opened', pull_request: pr });

    assert.equal(result.ok, true);
    assert.deepEqual(fake.calls.adds, ['PR_20'], 'le ticket #3 est déjà dans le projet');
    assert.deepEqual(fake.written('PR_20'), { Status: 'In review' });
    assert.deepEqual(fake.written('I_3'), { Status: 'In review' });
    assert.deepEqual(fake.written('I_4'), {}, 'référence dans un commentaire HTML ignorée');
    assert.ok(logs.info.some((m) => m.startsWith('PR #20') && m.includes('tickets liés : #3')));
});

test('PR en brouillon : In progress ; prête pour relecture : In review', async () => {
    const draft = pull(21, { draft: true, body: 'Résout #1' });
    const fake = fakeGitHub({ issues: [issue(1)], pulls: [draft], existing: [{ content: 'I_1', values: { Status: 'Backlog' } }] });
    await run(fake, 'pull_request_target', { action: 'opened', pull_request: draft });
    assert.deepEqual(fake.written('PR_21'), { Status: 'In progress' });
    assert.deepEqual(fake.written('I_1'), { Status: 'In progress' });

    draft.draft = false;
    await run(fake, 'pull_request_target', { action: 'ready_for_review', pull_request: draft });
    assert.equal(fake.written('PR_21').Status, 'In review');
    assert.equal(fake.written('I_1').Status, 'In review');
});

test('PR ouverte : un ticket déjà Done ou fermé n\'est pas déplacé', async () => {
    const pr = pull(22, { body: 'Fixes #1, #2' });
    const fake = fakeGitHub({ issues: [issue(1), issue(2, { state: 'closed' })], pulls: [pr], existing: [{ content: 'I_1', values: { Status: 'Done' } }] });
    await run(fake, 'pull_request', { action: 'reopened', pull_request: pr });
    assert.deepEqual(fake.written('I_1'), {});
    assert.deepEqual(fake.written('I_2'), {});
    assert.deepEqual(fake.calls.adds, ['PR_22', 'I_2'], 'le ticket absent est quand même rangé dans le projet');
});

test('PR fusionnée : PR et tickets liés en Done', async () => {
    const pr = pull(20, {
        state: 'closed',
        merged: true,
        body: 'Closes #3',
        closing: [4, { number: 99, repository: { nameWithOwner: 'autre/depot' } }],
    });
    const fake = fakeGitHub({
        issues: repoIssues(),
        pulls: [pr],
        existing: [{ content: 'PR_20', values: { Status: 'In review' } }, { content: 'I_3', values: { Status: 'In review' } }],
    });
    const { result } = await run(fake, 'pull_request_target', { action: 'closed', pull_request: { ...pr, state: 'open', merged: false } });
    assert.equal(result.ok, true);
    assert.deepEqual(fake.written('PR_20'), { Status: 'Done' }, 'état relu : fusionnée');
    assert.deepEqual(fake.written('I_3'), { Status: 'Done' });
    assert.deepEqual(fake.written('I_4'), { Status: 'Done' }, 'ticket lié par GitHub (closingIssuesReferences)');
    assert.equal(fake.calls.adds.includes('I_99'), false, 'ticket d\'un autre dépôt ignoré');
});

test('PR fermée sans fusion : ticket In review ⇒ In progress', async () => {
    const pr = pull(23, { state: 'closed', body: 'Closes #3\nCloses #1' });
    const fake = fakeGitHub({
        issues: repoIssues(),
        pulls: [pr],
        existing: [{ content: 'I_3', values: { Status: 'In review' } }, { content: 'I_1', values: { Status: 'Backlog' } }],
    });
    await run(fake, 'pull_request_target', { action: 'closed', pull_request: pr });
    assert.deepEqual(fake.written('PR_23'), { Status: 'Done' });
    assert.deepEqual(fake.written('I_3'), { Status: 'In progress' });
    assert.deepEqual(fake.written('I_1'), {}, 'un ticket qui n\'était pas en relecture ne bouge pas');
});

test('PR fusionnée dans une autre branche que la branche par défaut : tickets inchangés', async () => {
    const pr = pull(24, { state: 'closed', merged: true, base: 'develop', body: 'Closes #3' });
    const fake = fakeGitHub({ issues: repoIssues(), pulls: [pr], existing: [{ content: 'I_3', values: { Status: 'In review' } }] });
    const { logs } = await run(fake, 'pull_request_target', { action: 'closed', pull_request: pr });
    assert.deepEqual(fake.written('PR_24'), { Status: 'Done' });
    assert.deepEqual(fake.written('I_3'), {});
    assert.ok(logs.info.some((m) => m.includes('develop')));
});

/* ------------------------------------------------------------------------- */
/* Jeton et permissions : échec propre                                       */
/* ------------------------------------------------------------------------- */

test('sans jeton : échec propre avec explication', async () => {
    const { result, logs } = await run(null, 'workflow_dispatch', {});
    assert.equal(result.ok, false);
    assert.equal(logs.failed.length, 1);
    assert.match(logs.failed[0], /PROJECT_TOKEN est absent/);
    assert.match(logs.failed[0], /Secrets and variables › Actions/);
});

test('jeton refusé (401) : échec propre', async () => {
    const fake = fakeGitHub({ fail: { repos: () => { throw httpError(401, 'Bad credentials'); } } });
    const { result, logs } = await run(fake, 'issues', { action: 'opened', issue: issue(1) });
    assert.equal(result.ok, false);
    assert.match(logs.failed[0], /refusé par GitHub \(invalide, expiré ou révoqué\)/);
    assert.equal(fake.calls.graphql, 0);
});

test('jeton sans droit Projects : échec propre, quelle que soit la réponse de GitHub', async () => {
    const cases = [
        ['INSUFFICIENT_SCOPES', 'Your token has not been granted the required scopes to execute this query. The \'projectV2\' field requires one of the following scopes: [\'read:project\']', /portées « project » et « repo »/],
        ['FORBIDDEN', 'Resource not accessible by personal access token', /Projects : Read and write/],
        ['FORBIDDEN', 'Resource not accessible by integration', /GITHUB_TOKEN par défaut/],
    ];
    for (const [type, message, expected] of cases) {
        const fake = fakeGitHub({
            fail: {
                graphql: (query) => {
                    if (query.includes('projectV2(number')) {
                        throw graphqlError(type, message, ['organization', 'projectV2']);
                    }
                },
            },
        });
        const { result, logs } = await run(fake, 'workflow_dispatch', {});
        assert.equal(result.ok, false, type);
        assert.equal(logs.failed.length, 1);
        assert.match(logs.failed[0], /n'a pas le droit « Projects » sur Aurora-Coms/);
        assert.match(logs.failed[0], expected);
        assert.deepEqual(fake.calls.adds, []);
    }

    // Projet invisible (droit manquant ou mauvais numéro) : NOT_FOUND.
    const hidden = fakeGitHub({ projectMissing: true });
    const { logs } = await run(hidden, 'workflow_dispatch', {});
    assert.match(logs.failed[0], /Projet n°6 introuvable pour Aurora-Coms/);
    assert.match(logs.failed[0], /droit « Projects »/);
});

test('jeton en lecture seule : arrêt dès la première écriture refusée', async () => {
    const fake = fakeGitHub({
        issues: repoIssues(),
        fail: { update: () => { throw graphqlError('FORBIDDEN', 'Resource not accessible by personal access token', ['updateProjectV2ItemFieldValue']); } },
    });
    const { result, logs } = await run(fake, 'workflow_dispatch', {});
    assert.equal(result.ok, false);
    assert.equal(logs.failed.length, 1);
    assert.match(logs.failed[0], /peut lire le projet n°6 mais pas le modifier/);
    assert.match(logs.failed[0], /Read and write/);
    assert.equal(fake.calls.updateAttempts, 1, 'pas d\'acharnement');
});

test('jeton sans accès au dépôt : échec propre', async () => {
    const fake = fakeGitHub({ fail: { repos: () => { throw httpError(404, 'Not Found'); } } });
    const { logs } = await run(fake, 'workflow_dispatch', {});
    assert.match(logs.failed[0], /n'a pas accès au dépôt Aurora-Coms\/Portfolio-Generale/);
    assert.match(logs.failed[0], /Issues : Read-only/);
});

/* ------------------------------------------------------------------------- */
/* Robustesse                                                                */
/* ------------------------------------------------------------------------- */

test('erreur sur un ticket : les autres sont traités, puis échec signalé', async () => {
    const fake = fakeGitHub({
        issues: repoIssues(),
        fail: { issues: (n) => { if (n === 3) throw httpError(502, 'Bad Gateway'); } },
    });
    const { result, logs } = await run(fake, 'workflow_dispatch', {});
    assert.equal(result.ok, false);
    assert.deepEqual(fake.calls.adds, ['I_1', 'I_2', 'I_4', 'I_5', 'I_6']);
    assert.equal(logs.failed.length, 1);
    assert.match(logs.failed[0], /^1 élément\(s\) n'ont pas pu être synchronisés : #3 : Bad Gateway/);
});

test('ticket supprimé (404) : simple avertissement', async () => {
    const fake = fakeGitHub({ issues: repoIssues().filter((i) => i.number !== 2) });
    const { result, logs } = await run(fake, 'workflow_dispatch', {});
    assert.equal(result.ok, true);
    assert.ok(logs.warning.some((m) => m.includes('Ticket #2 introuvable')));
});

test('limite de débit : attente puis nouvelle tentative', async () => {
    let once = true;
    const fake = fakeGitHub({
        issues: [issue(30)],
        fail: {
            update: () => {
                if (once) {
                    once = false;
                    throw httpError(403, 'You have exceeded a secondary rate limit', { response: { status: 403, headers: { 'retry-after': '7' } } });
                }
            },
        },
    });
    const { result, sleeps, logs } = await run(fake, 'issues', { action: 'opened', issue: issue(30) });
    assert.equal(result.ok, true);
    assert.deepEqual(fake.written('I_30'), { Status: 'Backlog' });
    assert.ok(sleeps.includes(0), 'attente demandée (0 ms en test)');
    assert.ok(logs.warning.some((m) => m.includes('Limite de débit')));
});

test('écritures espacées pour respecter les limites de GitHub', async () => {
    const fake = fakeGitHub({ issues: [issue(30, { labels: ['bug'] })] });
    const { sleeps } = await run(fake, 'issues', { action: 'opened', issue: issue(30, { labels: ['bug'] }) }, { options: { writeDelayMs: 60000 } });
    // 3 écritures (ajout, Status, Priority) : au moins 2 attentes.
    assert.ok(sleeps.filter((ms) => ms > 59000).length >= 2, `attentes : ${sleeps.join(', ')}`);
});

test('événement non géré : rien à faire, aucun appel', async () => {
    const fake = fakeGitHub();
    const { result, logs } = await run(fake, 'push', {});
    assert.equal(result.skipped, true);
    assert.equal(fake.calls.graphql + fake.calls.rest, 0);
    assert.deepEqual(logs.failed, []);
});

test('titres hostiles : aucune ligne de journal injectée', async () => {
    const evil = issue(60, { title: 'Titre\n::error::faux message\r\n::add-mask::x' });
    const fake = fakeGitHub({ issues: [evil] });
    const { logs } = await run(fake, 'issues', { action: 'opened', issue: evil });
    for (const line of [...logs.info, ...logs.warning, ...logs.error]) {
        assert.ok(!/[\r\n]/.test(line), `retour à la ligne dans : ${JSON.stringify(line)}`);
    }
});

/* ------------------------------------------------------------------------- */
/* Fichier réel du dépôt                                                     */
/* ------------------------------------------------------------------------- */

test('.github/project-items.json : valide et entièrement synchronisable', async () => {
    const realPath = path.join(ROOT, '.github', 'project-items.json');
    const raw = JSON.parse(fs.readFileSync(realPath, 'utf8'));
    const warnings = [];
    const config = internals.loadConfig(realPath, { warning: (m) => warnings.push(m) });
    assert.deepEqual(warnings, [], 'aucune valeur inconnue dans le JSON');
    assert.equal(config.org, 'Aurora-Coms');
    assert.equal(config.number, 6);
    assert.equal(config.entries.length, raw.items.length);
    for (const entry of config.entries) {
        assert.ok(entry.issue, `« ${entry.key} » sans numéro d'issue`);
        assert.ok(entry.desired.status && entry.desired.priority && entry.desired.size, `« ${entry.key} » incomplet`);
    }

    const issues = raw.items.map((item) => issue(item.issue, { labels: item.labels, title: item.title }));
    const fake = fakeGitHub({ issues });
    const { result, logs } = await run(fake, 'workflow_dispatch', {}, { options: { itemsPath: realPath } });
    assert.deepEqual(logs.failed, []);
    assert.deepEqual(logs.warning, []);
    assert.equal(result.ok, true);
    assert.equal(fake.calls.adds.length, raw.items.length);
    for (const item of raw.items) {
        const written = fake.written(`I_${item.issue}`);
        assert.equal(written.Status, item.status, `#${item.issue} Status`);
        assert.equal(written.Priority, item.priority, `#${item.issue} Priority`);
        assert.equal(written.Size, item.size, `#${item.issue} Size`);
        assert.equal(written.Estimate, internals.ESTIMATE_BY_SIZE[item.size], `#${item.issue} Estimate`);
        assert.equal(written['Start date'], item.start, `#${item.issue} Start date`);
        assert.equal(written['End date'], item.end, `#${item.issue} End date`);
    }
});

test('résumé de l\'exécution : tableau écrit, titres échappés', async () => {
    const written = { headings: [], raw: [], tables: [], writes: 0 };
    const core = fakeCore();
    core.summary = {
        addHeading(text) { written.headings.push(text); return this; },
        addRaw(text) { written.raw.push(text); return this; },
        addTable(rows) { written.tables.push(rows); return this; },
        async write() { written.writes += 1; return this; },
    };
    const hostile = issue(70, { title: '<img src=x onerror=alert(1)>' });
    const fake = fakeGitHub({ issues: [hostile] });
    process.env.GITHUB_STEP_SUMMARY = path.join(tmp, 'summary.md');
    try {
        await run(fake, 'issues', { action: 'opened', issue: hostile }, { core });
    } finally {
        delete process.env.GITHUB_STEP_SUMMARY;
    }
    assert.equal(written.writes, 1);
    assert.match(written.raw[0], /1 ajout\(s\), 1 champ\(s\) modifié\(s\)/);
    const cells = written.tables[0].slice(1).flat().join(' ');
    assert.ok(cells.includes('&lt;img src=x onerror=alert(1)&gt;'), cells);
    assert.ok(!cells.includes('<img'), 'aucune balise injectée');
});
