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

        function setOpen(open, returnFocus) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            panel.classList.toggle('is-open', open);
            if (!open && returnFocus) {
                toggle.focus();
            }
        }

        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') !== 'true';
            setOpen(open, false);
            if (open) {
                var first = panel.querySelector('a, button');
                if (first) {
                    first.focus();
                }
            }
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
