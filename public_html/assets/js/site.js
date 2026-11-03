/* =============================================================================
   Portfolio · scripts du site public (vanilla, chargé avec « defer »)
   Amélioration progressive : chaque fonction suppose que le HTML fonctionne
   déjà sans JavaScript. Documentation des attributs data-* : docs/STYLEGUIDE.md
   CSP : aucun script en ligne, aucun gestionnaire onclick dans le HTML.
   ============================================================================= */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function each(selector, fn, root) {
        Array.prototype.forEach.call((root || document).querySelectorAll(selector), fn);
    }

    /* -------------------------------------------------------------------------
       1. Menu mobile : [data-menu-toggle] ouvre/ferme [data-menu-panel]
       Sans JS, le bouton reste masqué et la rangée d'onglets suffit.
       ---------------------------------------------------------------------- */
    function initMenu() {
        var toggle = document.querySelector('[data-menu-toggle]');
        if (!toggle) {
            return;
        }
        var panel = document.getElementById(toggle.getAttribute('aria-controls') || '');
        if (!panel) {
            return;
        }
        toggle.hidden = false;

        function setOpen(open, returnFocus) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Fermer le menu' : 'Menu');
            panel.hidden = !open;
            if (!open && returnFocus) {
                toggle.focus();
            }
        }

        setOpen(false, false);

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

        document.addEventListener('click', function (ev) {
            if (toggle.getAttribute('aria-expanded') === 'true'
                && !panel.contains(ev.target) && !toggle.contains(ev.target)) {
                setOpen(false, false);
            }
        });

        // Passage en affichage ordinateur : le panneau n'a plus lieu d'être.
        if (window.matchMedia) {
            var mq = window.matchMedia('(min-width: 900px)');
            var onChange = function () {
                if (mq.matches) {
                    setOpen(false, false);
                }
            };
            if (mq.addEventListener) {
                mq.addEventListener('change', onChange);
            } else if (mq.addListener) {
                mq.addListener(onChange);
            }
        }
    }

    /* -------------------------------------------------------------------------
       2. Texte qui s'écrit : [data-typewriter]
       Le texte complet reste dans le DOM (copie .sr-only lue par les lecteurs
       d'écran) ; la partie animée est aria-hidden. Le texte non encore écrit
       est seulement invisible (visibility) : la mise en page ne bouge pas.
       Option : data-typewriter-delay="400" (ms avant le début).
       ---------------------------------------------------------------------- */
    function initTypewriter() {
        if (reduceMotion) {
            return;
        }
        each('[data-typewriter]', function (el) {
            // Uniquement sur un élément qui ne contient que du texte.
            if (el.children.length > 0) {
                return;
            }
            var text = el.textContent.replace(/\s+/g, ' ').trim();
            if (text === '') {
                return;
            }
            var chars = Array.from ? Array.from(text) : text.split('');
            var delay = parseInt(el.getAttribute('data-typewriter-delay') || '250', 10);
            var speed = Math.max(18, Math.min(60, Math.round(1400 / chars.length)));

            var sr = document.createElement('span');
            sr.className = 'sr-only';
            sr.textContent = text;

            var visual = document.createElement('span');
            visual.setAttribute('aria-hidden', 'true');
            var typed = document.createElement('span');
            var caret = document.createElement('span');
            caret.className = 'tw-caret';
            var rest = document.createElement('span');
            rest.className = 'tw-rest';
            rest.textContent = text;
            visual.appendChild(typed);
            visual.appendChild(caret);
            visual.appendChild(rest);

            el.textContent = '';
            el.appendChild(sr);
            el.appendChild(visual);

            var i = 0;
            function step() {
                i++;
                typed.textContent = chars.slice(0, i).join('');
                rest.textContent = chars.slice(i).join('');
                if (i < chars.length) {
                    window.setTimeout(step, speed);
                } else {
                    // Fin : on rend un texte simple (le curseur disparaît après un moment).
                    window.setTimeout(function () {
                        el.textContent = text;
                    }, 2200);
                }
            }
            window.setTimeout(step, delay);
        });
    }

    /* -------------------------------------------------------------------------
       3. Apparition au défilement : [data-reveal]
       Seuls les éléments situés SOUS l'écran au chargement sont masqués puis
       révélés : rien n'est caché si le JS ne charge pas, aucun clignotement.
       ---------------------------------------------------------------------- */
    function initReveal() {
        if (reduceMotion || !('IntersectionObserver' in window)) {
            return;
        }
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px', threshold: 0.01 });

        var limit = window.innerHeight || document.documentElement.clientHeight;
        each('[data-reveal]', function (el) {
            var rect = el.getBoundingClientRect();
            if (rect.height > 0 && rect.top > limit) {
                el.classList.add('reveal-pending');
                observer.observe(el);
            }
        });
    }

    /* -------------------------------------------------------------------------
       4. Filtres de projets : [data-filter-group][data-filter-target="id"]
       Les puces sont des liens ?categorie=slug (fonctionnent sans JS). Ici :
       filtrage instantané des cartes [data-categories], URL mise à jour,
       annonce « N projets affichés » dans [data-filter-status].
       ---------------------------------------------------------------------- */
    function initFilters() {
        each('[data-filter-group]', function (group) {
            var list = document.getElementById(group.getAttribute('data-filter-target') || '');
            if (!list) {
                return;
            }
            var status = group.querySelector('[data-filter-status]');
            var empty = list.querySelector('[data-filter-empty]');

            group.addEventListener('click', function (ev) {
                var link = ev.target.closest ? ev.target.closest('[data-filter]') : null;
                if (!link || !group.contains(link)) {
                    return;
                }
                // Ouverture dans un nouvel onglet : comportement normal du lien.
                if (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) {
                    return;
                }
                ev.preventDefault();
                var slug = link.getAttribute('data-filter') || '';

                each('[data-filter]', function (chip) {
                    if (chip === link) {
                        chip.setAttribute('aria-current', 'page');
                    } else {
                        chip.removeAttribute('aria-current');
                    }
                }, group);

                var shown = 0;
                each('[data-categories]', function (card) {
                    var cats = (card.getAttribute('data-categories') || '').split(/\s+/);
                    var visible = slug === '' || cats.indexOf(slug) !== -1;
                    card.hidden = !visible;
                    if (visible) {
                        shown++;
                        card.classList.remove('reveal-pending');
                    }
                }, list);

                if (empty) {
                    empty.hidden = shown > 0;
                }
                if (status) {
                    status.textContent = shown === 0
                        ? 'Aucun projet affiché'
                        : shown + (shown > 1 ? ' projets affichés' : ' projet affiché');
                }

                if (window.history && window.history.replaceState) {
                    var url = new URL(window.location.href);
                    url.searchParams.delete('page');
                    if (slug === '') {
                        url.searchParams.delete('categorie');
                    } else {
                        url.searchParams.set('categorie', slug);
                    }
                    window.history.replaceState(null, '', url.pathname + url.search + url.hash);
                }
            });
        });
    }

    /* -------------------------------------------------------------------------
       5. Visionneuse d'images : liens [data-lightbox="groupe"] (href = image)
       <dialog> modale : ←/→ pour naviguer, Échap pour fermer, focus rendu
       au lien d'origine. Sans JS, le lien ouvre simplement l'image.
       ---------------------------------------------------------------------- */
    var ICONS = {
        close: '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
        prev: '<path d="m15 18-6-6 6-6"/>',
        next: '<path d="m9 18 6-6-6-6"/>'
    };

    function svgIcon(name) {
        return '<svg class="icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
            + 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + ICONS[name] + '</svg>';
    }

    function initLightbox() {
        var links = document.querySelectorAll('a[data-lightbox]');
        if (links.length === 0 || typeof HTMLDialogElement === 'undefined') {
            return;
        }

        var dialog = document.createElement('dialog');
        dialog.className = 'lightbox';
        dialog.setAttribute('aria-label', 'Visionneuse d\'images');
        dialog.innerHTML =
            '<div class="lightbox__inner">'
            + '<button type="button" class="lightbox__btn lightbox__close" aria-label="Fermer la visionneuse">' + svgIcon('close') + '</button>'
            + '<button type="button" class="lightbox__btn lightbox__prev" aria-label="Image précédente">' + svgIcon('prev') + '</button>'
            + '<figure class="lightbox__figure"><img class="lightbox__img" alt="">'
            + '<figcaption class="lightbox__caption"></figcaption></figure>'
            + '<p class="lightbox__count" aria-live="polite"></p>'
            + '<button type="button" class="lightbox__btn lightbox__next" aria-label="Image suivante">' + svgIcon('next') + '</button>'
            + '</div>';
        document.body.appendChild(dialog);

        var img = dialog.querySelector('.lightbox__img');
        var caption = dialog.querySelector('.lightbox__caption');
        var count = dialog.querySelector('.lightbox__count');
        var btnClose = dialog.querySelector('.lightbox__close');
        var btnPrev = dialog.querySelector('.lightbox__prev');
        var btnNext = dialog.querySelector('.lightbox__next');

        var items = [];
        var index = 0;
        var opener = null;

        function show(i) {
            index = (i + items.length) % items.length;
            var link = items[index];
            var text = link.getAttribute('data-caption') || '';
            if (text === '') {
                var inner = link.querySelector('img');
                text = inner ? inner.getAttribute('alt') || '' : '';
            }
            img.src = link.getAttribute('href');
            img.alt = text;
            caption.textContent = text;
            caption.hidden = text === '';
            caption.setAttribute('aria-hidden', 'true'); // déjà lu via alt
            count.textContent = items.length > 1 ? 'Image ' + (index + 1) + ' sur ' + items.length : '';
            btnPrev.hidden = items.length < 2;
            btnNext.hidden = items.length < 2;
        }

        function open(link) {
            var group = link.getAttribute('data-lightbox');
            items = Array.prototype.filter.call(links, function (l) {
                return l.getAttribute('data-lightbox') === group;
            });
            opener = link;
            show(items.indexOf(link));
            document.body.classList.add('has-dialog');
            dialog.showModal();
            btnClose.focus();
        }

        Array.prototype.forEach.call(links, function (link) {
            link.addEventListener('click', function (ev) {
                if (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) {
                    return;
                }
                ev.preventDefault();
                open(link);
            });
        });

        btnClose.addEventListener('click', function () { dialog.close(); });
        btnPrev.addEventListener('click', function () { show(index - 1); });
        btnNext.addEventListener('click', function () { show(index + 1); });

        dialog.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowLeft' && items.length > 1) {
                ev.preventDefault();
                show(index - 1);
            } else if (ev.key === 'ArrowRight' && items.length > 1) {
                ev.preventDefault();
                show(index + 1);
            }
        });

        // Clic sur le fond (hors image et boutons) : fermeture.
        dialog.addEventListener('click', function (ev) {
            if (ev.target === dialog || ev.target.classList.contains('lightbox__inner')) {
                dialog.close();
            }
        });

        dialog.addEventListener('close', function () {
            document.body.classList.remove('has-dialog');
            img.removeAttribute('src');
            if (opener) {
                opener.focus();
            }
        });
    }

    /* -------------------------------------------------------------------------
       6. Vidéo YouTube en façade : bouton [data-youtube="ID"][data-title]
       Aucune requête vers YouTube avant le clic ; ensuite seulement, l'iframe
       youtube-nocookie.com est insérée (autorisée par frame-src dans la CSP).
       ---------------------------------------------------------------------- */
    function initYoutube() {
        each('[data-youtube]', function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-youtube') || '';
                if (!/^[A-Za-z0-9_-]{11}$/.test(id)) {
                    return;
                }
                var iframe = document.createElement('iframe');
                iframe.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1';
                iframe.title = btn.getAttribute('data-title') || 'Vidéo YouTube';
                iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
                iframe.setAttribute('allowfullscreen', '');
                iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
                iframe.setAttribute('loading', 'eager');
                btn.parentNode.replaceChild(iframe, btn);
                iframe.focus();
            });
        });
    }

    /* -------------------------------------------------------------------------
       7. Compteur de caractères : champ [data-maxlength="2000"]
       Affiche « 120 / 2000 » sous le champ (relié par aria-describedby) et
       n'annonce aux lecteurs d'écran que l'approche et le dépassement de la limite.
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
                    if (next === 'near') {
                        live.textContent = 'Il reste ' + (max - len) + ' caractères.';
                    } else if (next === 'over') {
                        live.textContent = 'Limite de ' + max + ' caractères dépassée.';
                    } else {
                        live.textContent = '';
                    }
                    state = next;
                }
            }
            field.addEventListener('input', update);
            update();
        });
    }

    /* -------------------------------------------------------------------------
       8. Formulaire avec erreurs : focus sur le premier champ invalide
       (seulement si la page contient [data-focus-error] sur le formulaire).
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

    /* -------------------------------------------------------------------------
       9. Rangées qui défilent horizontalement (.tabs, .filters__list) sur
       téléphone : Chrome ne fait pas défiler un élément déjà partiellement
       visible, l'onglet ou la puce focalisé au clavier restait à moitié hors
       champ. On l'amène entièrement à l'écran (marge : scroll-padding-inline).
       Seulement au clavier (:focus-visible) : au clic ou au toucher, la rangée
       ne doit pas bouger entre l'appui et le relâchement (le clic serait perdu).
       ---------------------------------------------------------------------- */
    function initScrollRows() {
        each('.tabs, .filters__list', function (row) {
            row.addEventListener('focusin', function (ev) {
                var el = ev.target;
                if (typeof el.scrollIntoView !== 'function') {
                    return;
                }
                try {
                    if (!el.matches(':focus-visible')) {
                        return;
                    }
                } catch (e) {
                    return; // :focus-visible inconnu : comportement par défaut
                }
                el.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            });
        });
    }

    function init() {
        initMenu();
        initTypewriter();
        initReveal();
        initFilters();
        initLightbox();
        initYoutube();
        initCounters();
        initErrorFocus();
        initScrollRows();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
