/* =============================================================================
   Portfolio · scripts de l'administration (vanilla, chargé avec « defer »)
   Amélioration progressive : tout fonctionne sans JavaScript.
   Documentation des attributs data-* : docs/STYLEGUIDE.md
   ============================================================================= */
(function () {
    'use strict';

    function each(selector, fn, root) {
        Array.prototype.forEach.call((root || document).querySelectorAll(selector), fn);
    }

    /* -------------------------------------------------------------------------
       1. Menu mobile : sans JS, le lien [data-admin-menu-link] mène à #admin-nav
       (affiché par :target). Avec JS, le bouton [data-admin-menu-toggle] le
       remplace et ouvre/ferme la barre latérale (classe .is-open).
       ---------------------------------------------------------------------- */
    function initMenu() {
        var toggle = document.querySelector('[data-admin-menu-toggle]');
        var link = document.querySelector('[data-admin-menu-link]');
        if (!toggle) {
            return;
        }
        var panel = document.getElementById(toggle.getAttribute('aria-controls') || '');
        if (!panel) {
            return;
        }
        if (link) {
            link.hidden = true;
        }
        toggle.hidden = false;

        // Position de défilement avant l'ouverture, rétablie à la fermeture
        // (on ne perd pas sa place dans une longue liste).
        var savedY = 0;

        function setOpen(open, returnFocus) {
            var wasOpen = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            panel.classList.toggle('is-open', open);
            if (open && !wasOpen) {
                // Le panneau s'affiche dans le flux, juste sous la barre du haut :
                // depuis une page défilée, il serait hors de l'écran. On remonte
                // donc en haut de page, puis le focus va sur le premier lien du menu
                // (le logo du panneau, premier lien du DOM, est masqué sur téléphone).
                savedY = window.pageYOffset || document.documentElement.scrollTop || 0;
                window.scrollTo(0, 0);
                var first = panel.querySelector('.admin-nav__link');
                if (first) {
                    first.focus({ preventScroll: true });
                }
            } else if (!open && wasOpen) {
                window.scrollTo(0, savedY);
                if (returnFocus) {
                    toggle.focus({ preventScroll: true });
                }
            }
        }

        toggle.addEventListener('click', function () {
            setOpen(toggle.getAttribute('aria-expanded') !== 'true', false);
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setOpen(false, true);
            }
        });
    }

    /* -------------------------------------------------------------------------
       2. Confirmation : data-confirm="Message" sur un <form> ou sur un bouton
       submit. Si l'utilisateur refuse, l'envoi est annulé.
       ---------------------------------------------------------------------- */
    function initConfirm() {
        document.addEventListener('submit', function (ev) {
            var form = ev.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            var submitter = ev.submitter || null;
            var message = (submitter && submitter.getAttribute('data-confirm'))
                || form.getAttribute('data-confirm');
            if (message && !window.confirm(message)) {
                ev.preventDefault();
                ev.stopImmediatePropagation();
            }
        }, true);

        // Navigateurs sans ev.submitter : on retient le dernier bouton cliqué.
        document.addEventListener('click', function (ev) {
            var btn = ev.target.closest ? ev.target.closest('button[data-confirm], input[type="submit"][data-confirm]') : null;
            var native = typeof SubmitEvent !== 'undefined' && 'submitter' in SubmitEvent.prototype;
            if (!btn || !btn.form || native) {
                return;
            }
            if (!window.confirm(btn.getAttribute('data-confirm'))) {
                ev.preventDefault();
            }
        }, true);
    }

    /* -------------------------------------------------------------------------
       3. Slug automatique : data-slug-source="#f-title" sur le champ slug.
       Rempli depuis le titre tant que l'utilisateur ne l'a pas modifié
       lui-même (un slug déjà présent au chargement n'est jamais écrasé).
       ---------------------------------------------------------------------- */
    function slugify(text) {
        return text
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/œ/g, 'oe').replace(/æ/g, 'ae')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 90)
            .replace(/-+$/g, '');
    }

    function initSlug() {
        each('[data-slug-source]', function (slug) {
            var source = document.querySelector(slug.getAttribute('data-slug-source'));
            if (!source) {
                return;
            }
            var manual = slug.value.trim() !== '';
            slug.addEventListener('input', function () {
                manual = slug.value.trim() !== '';
            });
            source.addEventListener('input', function () {
                if (!manual) {
                    slug.value = slugify(source.value);
                }
            });
        });
    }

    /* -------------------------------------------------------------------------
       4. Cases filtrées selon la section : fieldset[data-section-filter="#f-section"]
       contenant des éléments [data-section="game|tech"]. Les cases des autres
       sections sont masquées ET décochées (le serveur les ignore de toute façon).
       ---------------------------------------------------------------------- */
    function initSectionFilter() {
        each('[data-section-filter]', function (group) {
            var select = document.querySelector(group.getAttribute('data-section-filter'));
            if (!select) {
                return;
            }
            function apply() {
                var value = select.value;
                each('[data-section]', function (item) {
                    var match = item.getAttribute('data-section') === value;
                    item.hidden = !match;
                    if (!match) {
                        each('input[type="checkbox"]', function (box) { box.checked = false; }, item);
                    }
                }, group);
            }
            select.addEventListener('change', apply);
            apply();
        });
    }

    /* -------------------------------------------------------------------------
       5. Compteur de caractères : champ [data-maxlength="160"]
       ---------------------------------------------------------------------- */
    function initCounters() {
        var n = 0;
        each('[data-maxlength]', function (field) {
            var max = parseInt(field.getAttribute('data-maxlength') || '0', 10);
            if (!max) {
                return;
            }
            n++;
            var id = (field.id || 'champ-' + n) + '-compteur';
            var counter = document.createElement('p');
            counter.className = 'counter';
            counter.id = id;
            var live = document.createElement('span');
            live.className = 'sr-only';
            live.setAttribute('aria-live', 'polite');
            field.insertAdjacentElement('afterend', counter);
            counter.insertAdjacentElement('afterend', live);

            var describedBy = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            describedBy.push(id);
            field.setAttribute('aria-describedby', describedBy.join(' '));

            var state = '';
            function update() {
                var len = Array.from ? Array.from(field.value).length : field.value.length;
                counter.textContent = len + ' / ' + max + ' caractères';
                var next = len > max ? 'over' : (len >= max * 0.9 ? 'near' : '');
                counter.classList.toggle('is-near', next === 'near');
                counter.classList.toggle('is-over', next === 'over');
                if (next !== state) {
                    live.textContent = next === 'near'
                        ? 'Il reste ' + (max - len) + ' caractères.'
                        : (next === 'over' ? 'Limite de ' + max + ' caractères dépassée.' : '');
                    state = next;
                }
            }
            field.addEventListener('input', update);
            update();
        });
    }

    /* -------------------------------------------------------------------------
       6. Aperçu des fichiers choisis : input[type=file][data-file-preview]
       Liste nom + taille (+ miniature pour une image) sous le champ.
       Option : data-max-size="8388608" (octets) signale les fichiers trop lourds.
       ---------------------------------------------------------------------- */
    function formatSize(bytes) {
        if (bytes < 1024) {
            return bytes + ' o';
        }
        if (bytes < 1024 * 1024) {
            return (bytes / 1024).toFixed(0) + ' Ko';
        }
        return (bytes / (1024 * 1024)).toFixed(1).replace('.', ',') + ' Mo';
    }

    function initFilePreview() {
        each('input[type="file"][data-file-preview]', function (input) {
            var list = document.createElement('ul');
            list.className = 'file-preview';
            list.setAttribute('aria-live', 'polite');
            input.insertAdjacentElement('afterend', list);
            var maxSize = parseInt(input.getAttribute('data-max-size') || '0', 10);

            input.addEventListener('change', function () {
                list.textContent = '';
                Array.prototype.forEach.call(input.files || [], function (file) {
                    var li = document.createElement('li');
                    li.className = 'file-preview__item';
                    var tooBig = maxSize > 0 && file.size > maxSize;
                    // Miniature en data: (la CSP autorise data: mais pas blob: pour les images).
                    if (/^image\/(png|jpeg|webp|gif)$/.test(file.type) && !tooBig && file.size < 15 * 1024 * 1024 && window.FileReader) {
                        var img = document.createElement('img');
                        img.alt = '';
                        li.appendChild(img);
                        var reader = new FileReader();
                        reader.addEventListener('load', function () {
                            if (typeof reader.result === 'string' && reader.result.indexOf('data:image/') === 0) {
                                img.src = reader.result;
                            }
                        });
                        reader.readAsDataURL(file);
                    }
                    var text = document.createElement('span');
                    text.textContent = file.name + ' · ' + formatSize(file.size) + (tooBig ? ' · trop volumineux' : '');
                    if (tooBig) {
                        li.classList.add('is-error');
                    }
                    li.appendChild(text);
                    list.appendChild(li);
                });
            });
        });
    }

    /* -------------------------------------------------------------------------
       7. Focus sur le premier champ invalide : form[data-focus-error]
       ---------------------------------------------------------------------- */
    function initErrorFocus() {
        var form = document.querySelector('form[data-focus-error]');
        if (!form) {
            return;
        }
        var invalid = form.querySelector('[aria-invalid="true"]');
        if (invalid) {
            invalid.focus();
        }
    }

    function init() {
        initMenu();
        initConfirm();
        initSlug();
        initSectionFilter();
        initCounters();
        initFilePreview();
        initErrorFocus();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());

/* === Ajouts admin lot A === */
/* Connexion, projets, images. Documentation : docs/STYLEGUIDE.md, § 10 bis. */
(function () {
    'use strict';

    /* -------------------------------------------------------------------------
       A1. Nombre de fichiers : input[type=file][data-max-files="10"].
       Au-delà, le navigateur bloque l'envoi (setCustomValidity) et un message
       est affiché et annoncé. Le serveur refuse de toute façon l'envoi.
       ---------------------------------------------------------------------- */
    function initMaxFiles() {
        Array.prototype.forEach.call(document.querySelectorAll('input[type="file"][data-max-files]'), function (input) {
            var max = parseInt(input.getAttribute('data-max-files') || '0', 10);
            if (!max) {
                return;
            }
            var note = document.createElement('p');
            note.className = 'field__error';
            note.id = (input.id || 'fichiers') + '-nombre';
            note.setAttribute('aria-live', 'polite');
            note.hidden = true;
            input.insertAdjacentElement('afterend', note);

            var describedBy = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            describedBy.push(note.id);
            input.setAttribute('aria-describedby', describedBy.join(' '));

            input.addEventListener('change', function () {
                var count = input.files ? input.files.length : 0;
                var message = count > max
                    ? count + ' fichiers choisis : ' + max + ' au maximum par envoi.'
                    : '';
                input.setCustomValidity(message);
                note.textContent = message;
                note.hidden = message === '';
                if (message) {
                    input.setAttribute('aria-invalid', 'true');
                } else {
                    input.removeAttribute('aria-invalid');
                }
            });
        });
    }

    /* -------------------------------------------------------------------------
       A2. Bouton occupé : <button type="submit" data-busy-label="Envoi en cours…">.
       Après un envoi réellement parti (non annulé par data-confirm ni par la
       validation du navigateur), le bouton est désactivé et son texte change :
       pas de double envoi. Rétabli si la page revient du cache (bouton Retour).
       ---------------------------------------------------------------------- */
    function initBusy() {
        var saved = [];
        document.addEventListener('submit', function (ev) {
            var form = ev.target;
            if (!(form instanceof HTMLFormElement) || ev.defaultPrevented) {
                return;
            }
            var button = ev.submitter || form.querySelector('[type="submit"][data-busy-label]');
            if (!button || !button.hasAttribute('data-busy-label') || button.disabled) {
                return;
            }
            // Après l'envoi (le bouton désactivé ne bloquerait pas l'envoi, mais
            // on laisse d'abord les autres gestionnaires décider).
            window.setTimeout(function () {
                // Contenu d'origine (icône + texte) gardé de côté, sans innerHTML.
                saved.push({ button: button, nodes: Array.prototype.slice.call(button.childNodes) });
                button.textContent = button.getAttribute('data-busy-label');
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
            }, 0);
        });

        window.addEventListener('pageshow', function (ev) {
            if (!ev.persisted) {
                return;
            }
            saved.forEach(function (entry) {
                entry.button.textContent = '';
                entry.nodes.forEach(function (node) {
                    entry.button.appendChild(node);
                });
                entry.button.disabled = false;
                entry.button.removeAttribute('aria-disabled');
            });
            saved = [];
        });
    }

    function init() {
        initMaxFiles();
        initBusy();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());

/* === Ajouts admin lot B === */
/* Parcours, codes de secours, formulaires multiples. Documentation : docs/STYLEGUIDE.md, § 10 ter. */
(function () {
    'use strict';

    function each(selector, fn, root) {
        Array.prototype.forEach.call((root || document).querySelectorAll(selector), fn);
    }

    /* -------------------------------------------------------------------------
       B1. Aperçu de la période : [data-period-preview] avec data-start,
       data-end, data-precision (sélecteurs des champs) et un enfant
       [data-period-output]. Même format que period_fr() côté PHP :
       « septembre 2025 → en cours », « 18 mai 2026 → 26 juin 2026 », « 2024 ».
       ---------------------------------------------------------------------- */
    var MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
        'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    function dateFr(value, precision) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        if (!m) {
            return '';
        }
        var month = parseInt(m[2], 10);
        var day = parseInt(m[3], 10);
        if (month < 1 || month > 12 || day < 1 || day > 31) {
            return '';
        }
        if (precision === 'year') {
            return m[1];
        }
        if (precision === 'month') {
            return MONTHS[month - 1] + ' ' + m[1];
        }
        return day + (day === 1 ? 'er' : '') + ' ' + MONTHS[month - 1] + ' ' + m[1];
    }

    function initPeriodPreview() {
        each('[data-period-preview]', function (box) {
            var start = document.querySelector(box.getAttribute('data-start') || '');
            var end = document.querySelector(box.getAttribute('data-end') || '');
            var precision = document.querySelector(box.getAttribute('data-precision') || '');
            var output = box.querySelector('[data-period-output]');
            if (!start || !end || !precision || !output) {
                return;
            }
            function update() {
                var p = precision.value;
                var from = dateFr(start.value, p);
                var to = dateFr(end.value, p);
                var text;
                if (!from) {
                    text = 'indiquez une date de début';
                } else if (end.value && to && end.value < start.value) {
                    text = 'la date de fin est avant la date de début';
                } else if (!to) {
                    text = from + ' → en cours';
                } else {
                    text = from === to ? from : from + ' → ' + to;
                }
                output.textContent = text;
            }
            [start, end, precision].forEach(function (field) {
                field.addEventListener('input', update);
                field.addEventListener('change', update);
            });
            update();
        });
    }

    /* -------------------------------------------------------------------------
       B2. Copier une liste : <button data-copy-target="#id" data-copy-done="Copié" hidden>.
       Le bouton n'apparaît que si le presse-papiers est disponible. Les <li>
       de la cible sont copiés, un par ligne. Annonce dans [data-copy-status].
       ---------------------------------------------------------------------- */
    function initCopy() {
        var status = document.querySelector('[data-copy-status]');
        each('[data-copy-target]', function (button) {
            var target = document.querySelector(button.getAttribute('data-copy-target') || '');
            if (!target || !navigator.clipboard || !window.isSecureContext) {
                return;
            }
            button.hidden = false;
            var label = button.querySelector('span');
            var initial = label ? label.textContent : '';
            button.addEventListener('click', function () {
                var items = target.querySelectorAll('li');
                var text = items.length
                    ? Array.prototype.map.call(items, function (li) { return li.textContent.trim(); }).join('\n')
                    : target.textContent.trim();
                navigator.clipboard.writeText(text).then(function () {
                    var done = button.getAttribute('data-copy-done') || 'Copié';
                    if (label) {
                        label.textContent = done;
                        window.setTimeout(function () { label.textContent = initial; }, 2500);
                    }
                    if (status) {
                        status.textContent = done + '.';
                    }
                }, function () {
                    if (status) {
                        status.textContent = 'Copie impossible : sélectionnez les codes à la main.';
                    }
                });
            });
        });
    }

    /* -------------------------------------------------------------------------
       B3. Imprimer : <button data-print hidden> (affiché seulement avec JS).
       ---------------------------------------------------------------------- */
    function initPrint() {
        if (typeof window.print !== 'function') {
            return;
        }
        each('[data-print]', function (button) {
            button.hidden = false;
            button.addEventListener('click', function () {
                window.print();
            });
        });
    }

    /* -------------------------------------------------------------------------
       B4. Plusieurs formulaires [data-focus-error] sur une page (ex. « Mon
       compte ») : si rien n'a encore reçu le focus, il va au premier champ
       invalide de la page, quel que soit le formulaire.
       ---------------------------------------------------------------------- */
    function initMultiFormFocus() {
        var active = document.activeElement;
        if (active && active !== document.body && active !== document.documentElement) {
            return;
        }
        var invalid = document.querySelector('form[data-focus-error] [aria-invalid="true"]');
        if (invalid) {
            invalid.focus();
        }
    }

    function init() {
        initPeriodPreview();
        initCopy();
        initPrint();
        initMultiFormFocus();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
