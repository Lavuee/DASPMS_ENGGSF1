// Customer portal behaviour (styles in assets/css/customer-portal.css).
// Header user menu, the fullscreen MENU sheet, and a styled confirm dialog for
// forms marked with data-cp-confirm. Pages keep working if this file fails to load.
(function () {
    const root = document.documentElement;

    /* ---------- User dropdown ---------- */
    const user = document.querySelector('[data-cp-user]');
    const userBtn = user ? user.querySelector('.cp-user-btn') : null;
    const userMenu = user ? user.querySelector('.cp-user-menu') : null;

    function setUserMenu(open) {
        if (!userBtn) return;
        userBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        userMenu.hidden = !open;
    }

    if (userBtn) {
        userBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            setUserMenu(userMenu.hidden);
        });

        document.addEventListener('click', function (event) {
            if (!user.contains(event.target)) setUserMenu(false);
        });
    }

    /* ---------- Fullscreen menu sheet (below the desktop header width) ---------- */
    // Customer (#cpSheet) and staff (#spSheet) headers share this menu sheet behaviour.
    const sheet = document.querySelector('.cp-sheet');
    const menuBtn = document.querySelector('.cp-menu-btn');

    function setSheet(open) {
        if (!sheet) return;
        sheet.hidden = !open;
        root.classList.toggle('cp-locked', open);
        if (menuBtn) menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open) {
            const first = sheet.querySelector('.cp-sheet-close');
            if (first) first.focus();
        } else if (menuBtn && sheet.contains(document.activeElement)) {
            menuBtn.focus();
        }
    }

    if (menuBtn && sheet) {
        menuBtn.addEventListener('click', function () { setSheet(true); });
        sheet.querySelector('.cp-sheet-close').addEventListener('click', function () { setSheet(false); });

        // Widening the window past the breakpoint closes the sheet.
        window.matchMedia('(min-width: ' + (parseInt(sheet.dataset.desktopFrom, 10) || 1181) + 'px)').addEventListener('change', function (mq) {
            if (mq.matches) setSheet(false);
        });
    }

    window.cpCloseMenus = function () {
        setUserMenu(false);
        setSheet(false);
    };

    /* ---------- Confirm dialog for forms with data-cp-confirm ---------- */
    let pendingForm = null;
    let confirmModal = null;

    function buildConfirm() {
        const wrap = document.createElement('div');
        wrap.className = 'cp-confirm';
        wrap.id = 'cpConfirm';
        wrap.hidden = true;
        wrap.innerHTML =
            '<div class="cp-confirm-box" role="alertdialog" aria-modal="true" aria-labelledby="cpConfirmTitle" aria-describedby="cpConfirmText">' +
                '<p class="cp-label">Please confirm</p>' +
                '<h2 class="cp-modal-title" id="cpConfirmTitle"></h2>' +
                '<p class="cp-muted" id="cpConfirmText"></p>' +
                '<div class="cp-confirm-actions">' +
                    '<button type="button" class="cp-btn cp-btn-line" data-cp-confirm-no></button>' +
                    '<button type="button" class="cp-btn cp-btn-danger" data-cp-confirm-yes></button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(wrap);

        wrap.addEventListener('click', function (event) {
            if (event.target === wrap) closeConfirm();
        });
        wrap.querySelector('[data-cp-confirm-no]').addEventListener('click', closeConfirm);
        wrap.querySelector('[data-cp-confirm-yes]').addEventListener('click', function () {
            const form = pendingForm;
            closeConfirm();
            if (form) {
                form.dataset.cpConfirmed = '1';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }
        });

        return wrap;
    }

    function openConfirm(form) {
        confirmModal = confirmModal || buildConfirm();
        pendingForm = form;
        confirmModal.querySelector('#cpConfirmTitle').textContent = form.dataset.cpConfirmTitle || 'Are you sure?';
        confirmModal.querySelector('#cpConfirmText').textContent = form.dataset.cpConfirm;
        confirmModal.querySelector('[data-cp-confirm-no]').textContent = form.dataset.cpConfirmNo || 'Keep it';
        confirmModal.querySelector('[data-cp-confirm-yes]').textContent = form.dataset.cpConfirmYes || 'Yes, continue';
        // data-cp-confirm-tone="dark" for a normal (not destructive) confirmation
        confirmModal.querySelector('[data-cp-confirm-yes]').className = 'cp-btn ' + (form.dataset.cpConfirmTone === 'dark' ? 'cp-btn-dark' : 'cp-btn-danger');
        confirmModal.hidden = false;
        root.classList.add('cp-locked');
        confirmModal.querySelector('[data-cp-confirm-no]').focus();
    }

    function closeConfirm() {
        if (!confirmModal || confirmModal.hidden) return;
        confirmModal.hidden = true;
        root.classList.remove('cp-locked');
        pendingForm = null;
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form.matches || !form.matches('form[data-cp-confirm]')) return;

        if (form.dataset.cpConfirmed === '1') {
            delete form.dataset.cpConfirmed;
            return;
        }

        event.preventDefault();
        openConfirm(form);
    }, true);

    /* ---------- Catalog filter (Browse Parts, Services Offered) ----------
       [data-cp-catalog] wraps: .cp-cat buttons (data-cat), [data-cp-search] input,
       [data-cp-clear] button, [data-cp-count] line (data-noun), [data-cp-noresults],
       and the cards, which carry data-search and data-cat. */
    document.querySelectorAll('[data-cp-catalog]').forEach(function (catalog) {
        const searchInput = catalog.querySelector('[data-cp-search]');
        const clearBtn = catalog.querySelector('[data-cp-clear]');
        const countLine = catalog.querySelector('[data-cp-count]');
        const noResults = catalog.querySelector('[data-cp-noresults]');
        const cards = catalog.querySelectorAll('[data-search]');
        const catButtons = catalog.querySelectorAll('.cp-cat');
        const noun = countLine ? countLine.dataset.noun : 'item';
        let activeCat = '';

        if (!searchInput) return;

        // Hand-drawn ellipse that follows the active category
        const ring = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        ring.setAttribute('class', 'cp-cat-ring');
        ring.setAttribute('viewBox', '0 0 100 40');
        ring.setAttribute('preserveAspectRatio', 'none');
        ring.setAttribute('aria-hidden', 'true');
        ring.innerHTML = '<path d="M14 9 C 32 2, 78 2, 93 12 C 101 19, 95 31, 74 35 C 52 39, 18 38, 7 29 C 0 22, 5 12, 21 7" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" vector-effect="non-scaling-stroke"/>';

        function placeRing(button) {
            if (!button) return;
            const path = ring.querySelector('path');
            button.appendChild(ring);
            path.style.animation = 'none';
            void path.getBoundingClientRect();
            path.style.animation = '';
        }

        function apply() {
            const value = searchInput.value.trim().toLowerCase();
            let visible = 0;

            cards.forEach(function (card) {
                const show = (card.dataset.search || '').includes(value) &&
                    (activeCat === '' || card.dataset.cat === activeCat);
                card.hidden = !show;
                if (show) visible++;
            });

            if (noResults) noResults.hidden = !(visible === 0 && cards.length > 0);
            if (countLine) {
                countLine.textContent = 'Showing ' + visible + ' ' + noun + (visible === 1 ? '' : 's') +
                    (value ? ' for "' + searchInput.value.trim() + '"' : '');
            }
        }

        catButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                catButtons.forEach(function (b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-pressed', 'false');
                });
                button.classList.add('is-active');
                button.setAttribute('aria-pressed', 'true');
                activeCat = button.dataset.cat;
                placeRing(button);
                apply();
            });
        });

        placeRing(catalog.querySelector('.cp-cat.is-active'));
        searchInput.addEventListener('input', apply);

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                apply();
                searchInput.focus();
            });
        }

        // The header search icon links to Browse Parts with #search
        if (location.hash === '#search') searchInput.focus();
    });

    /* ---------- Detail page tabs: keep the chosen tab visible when the row scrolls ---------- */
    document.querySelectorAll('.cpd-tabs').forEach(function (tablist) {
        tablist.addEventListener('tabchange', function () {
            const tab = tablist.querySelector('[aria-selected="true"]');
            if (tab && tablist.scrollWidth > tablist.clientWidth) {
                tablist.scrollTo({ left: tab.offsetLeft - (tablist.clientWidth - tab.offsetWidth) / 2, behavior: 'smooth' });
            }
        });
    });

    /* ---------- Pagination for long lists ----------
       Put data-cp-paginate="5" on the element whose direct children are the items
       (a .cp-rows list or a table's tbody). The pager appears under the list (or under
       its .cp-table-wrap) only when there are more items than fit on one page.
       Items marked data-cp-filtered="1" (e.g. by a search box) are left out; call
       window.cpRepaginate(list) after changing that. */
    const pagers = new Map();

    function pageWindow(current, total) {
        // 1 … 4 5 6 … 12
        const pages = [];
        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || Math.abs(i - current) <= 1) {
                pages.push(i);
            } else if (pages[pages.length - 1] !== '…') {
                pages.push('…');
            }
        }
        return pages;
    }

    function setUpPager(list) {
        const perPage = parseInt(list.dataset.cpPaginate, 10) || 5;
        const noun = list.dataset.cpNoun || 'item';
        const anchor = list.closest('.cp-table-wrap') || list.closest('table') || list; // always below the table, never inside it
        const nav = document.createElement('nav');
        nav.className = 'cp-pager';
        nav.setAttribute('aria-label', (list.dataset.cpPagerLabel || noun + 's') + ' pages');
        anchor.after(nav);

        const state = { page: 1 };

        function render(scroll) {
            const items = Array.from(list.children).filter(function (el) { return el.dataset.cpFiltered !== '1'; });
            const total = items.length;
            const pages = Math.max(1, Math.ceil(total / perPage));
            state.page = Math.min(Math.max(1, state.page), pages);
            const start = (state.page - 1) * perPage;

            Array.from(list.children).forEach(function (el) {
                const idx = items.indexOf(el);
                el.hidden = idx === -1 || idx < start || idx >= start + perPage;
            });

            nav.hidden = total <= perPage;
            if (nav.hidden) {
                nav.innerHTML = '';
                return;
            }

            const end = Math.min(start + perPage, total);
            let html = '<p class="cp-pager-info">Showing <span class="cp-mono">' + (start + 1) + '–' + end + '</span> of <span class="cp-mono">' + total + '</span> ' + noun + (total === 1 ? '' : 's') + '</p><div class="cp-pager-ctrls">';
            html += '<button type="button" class="cp-pager-btn cp-pager-step" data-page="' + (state.page - 1) + '"' + (state.page === 1 ? ' disabled' : '') + ' aria-label="Previous page">← Prev</button>';
            pageWindow(state.page, pages).forEach(function (n) {
                if (n === '…') {
                    html += '<span class="cp-pager-gap" aria-hidden="true">…</span>';
                } else {
                    html += '<button type="button" class="cp-pager-btn cp-pager-num' + (n === state.page ? ' is-active' : '') + '" data-page="' + n + '"' + (n === state.page ? ' aria-current="page"' : '') + ' aria-label="Page ' + n + '">' + n + '</button>';
                }
            });
            html += '<button type="button" class="cp-pager-btn cp-pager-step" data-page="' + (state.page + 1) + '"' + (state.page === pages ? ' disabled' : '') + ' aria-label="Next page">Next →</button></div>';
            nav.innerHTML = html;

            if (scroll) {
                // Bring the top of the list back into view when it has scrolled off.
                const top = (list.closest('.cp-sec') || anchor).getBoundingClientRect().top;
                const header = document.querySelector('.cp-header');
                const offset = (header ? header.offsetHeight : 0) + 16;
                if (top < offset) {
                    window.scrollTo({ top: window.scrollY + top - offset, behavior: 'smooth' });
                }
            }
        }

        nav.addEventListener('click', function (event) {
            const btn = event.target.closest('[data-page]');
            if (!btn || btn.disabled) return;
            state.page = parseInt(btn.dataset.page, 10);
            render(true);
            // Keep keyboard focus on the pager without letting it pull the page back down.
            const same = nav.querySelector('[data-page="' + state.page + '"].cp-pager-num');
            if (same) same.focus({ preventScroll: true });
        });

        pagers.set(list, function (resetPage) {
            if (resetPage) state.page = 1;
            render(false);
        });
        render(false);
    }

    document.querySelectorAll('[data-cp-paginate]').forEach(setUpPager);

    window.cpRepaginate = function (list, resetPage) {
        const fn = pagers.get(list);
        if (fn) fn(resetPage !== false);
    };

    /* ---------- Esc closes whatever is open ---------- */
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;

        if (confirmModal && !confirmModal.hidden) {
            closeConfirm();
            return;
        }

        const logout = document.getElementById('customLogoutModal');
        if (logout && logout.classList.contains('show') && typeof window.hideLogoutModal === 'function') {
            window.hideLogoutModal();
            return;
        }

        if (sheet && !sheet.hidden) {
            setSheet(false);
            return;
        }

        setUserMenu(false);
    });
})();

/* ---------- Form messages (instead of the browser's alert() box) ----------
   cpFieldError(input, 'message')  red text under that field, field outlined; clears when the user edits it
   cpNotice(anchor, 'message')     red message bar placed just before `anchor` (e.g. a submit button) */
(function () {
    function clearFieldError(input) {
        input.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');
        const note = input.__cpError;
        if (note && note.parentNode) note.parentNode.removeChild(note);
        input.__cpError = null;
    }

    window.cpFieldError = function (input, message) {
        if (!input) return;
        clearFieldError(input);
        const note = document.createElement('p');
        note.className = 'cp-field-error';
        note.setAttribute('role', 'alert');
        note.textContent = message;
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        input.insertAdjacentElement('afterend', note);
        input.__cpError = note;
        const clear = function () {
            clearFieldError(input);
            input.removeEventListener('input', clear);
            input.removeEventListener('change', clear);
        };
        input.addEventListener('input', clear);
        input.addEventListener('change', clear);
        if (typeof input.focus === 'function') input.focus({ preventScroll: false });
    };

    window.cpNotice = function (anchor, message) {
        if (!anchor) return;
        const holder = anchor.parentNode;
        const old = holder.querySelector(':scope > .cp-notice');
        if (old) old.remove();
        const bar = document.createElement('div');
        bar.className = 'cp-flash cp-flash-bad cp-notice';
        bar.setAttribute('role', 'alert');
        bar.innerHTML = '<span></span><button type="button" class="cp-flash-close" aria-label="Dismiss">&times;</button>';
        bar.querySelector('span').textContent = message;
        bar.querySelector('button').addEventListener('click', function () { bar.remove(); });
        holder.insertBefore(bar, anchor);
        bar.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    };
})();

/* ---------- Pop-up message (Shopee style) ----------
   cpToast(true, 'Added to cart', '1 × Switches')   centred box with a check, closes by itself after 1.5s
   cpToast(false, 'Quantity exceeds available stock.') same box with a cross, stays 2.5s
   Pages can also queue one for page load: <div data-cp-toast data-ok="1" data-title="..." data-detail="..."> */
(function () {
    let box = null;
    let timer = null;

    function build() {
        box = document.createElement('div');
        box.className = 'cp-toast';
        box.setAttribute('role', 'status');
        box.setAttribute('aria-live', 'polite');
        box.hidden = true;
        box.innerHTML = '<span class="cp-toast-icon" aria-hidden="true"></span><p class="cp-toast-title"></p><p class="cp-toast-detail"></p>';
        document.body.appendChild(box);
    }

    window.cpToast = function (ok, title, detail) {
        if (!box) build();
        box.classList.toggle('is-bad', !ok);
        box.querySelector('.cp-toast-icon').textContent = ok ? '\u2713' : '\u2715';
        box.querySelector('.cp-toast-title').textContent = title;
        box.querySelector('.cp-toast-detail').textContent = detail || '';
        box.hidden = false;
        void box.offsetWidth; // restart the fade-in
        box.classList.add('is-shown');
        clearTimeout(timer);
        timer = setTimeout(function () {
            box.classList.remove('is-shown');
            timer = setTimeout(function () { box.hidden = true; }, 250);
        }, ok ? 1500 : 2500);
    };

    function showQueued() {
        const queued = document.querySelector('[data-cp-toast]');
        if (!queued) return;
        window.cpToast(queued.dataset.ok === '1', queued.dataset.title, queued.dataset.detail);
        queued.remove();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showQueued);
    } else {
        showQueued();
    }
})();
