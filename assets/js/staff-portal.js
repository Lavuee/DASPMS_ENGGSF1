// Staff header dropdowns (Counter, Workshop, Records, Stock, Owner) and the alerts bell.
// The user menu, MENU sheet, confirm dialog and pagination come from customer-portal.js.
(function () {
    const menus = Array.from(document.querySelectorAll('[data-sp-menu], [data-sp-alerts]'));

    function setOpen(menu, open) {
        const btn = menu.querySelector('button[aria-controls]');
        const panel = menu.querySelector('.sp-panel');
        if (!btn || !panel) return;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        panel.hidden = !open;
    }

    function closeAll(except) {
        menus.forEach(function (menu) {
            if (menu !== except) setOpen(menu, false);
        });
    }

    menus.forEach(function (menu) {
        const btn = menu.querySelector('button[aria-controls]');
        const panel = menu.querySelector('.sp-panel');
        if (!btn || !panel) return;

        btn.addEventListener('click', function (event) {
            event.stopPropagation();
            const open = panel.hidden;
            closeAll(menu);
            setOpen(menu, open);
            if (open) {
                const first = panel.querySelector('a');
                if (first && event.detail === 0) first.focus(); // opened from the keyboard
            }
        });

        // Arrow keys move through the links inside an open panel.
        panel.addEventListener('keydown', function (event) {
            const links = Array.from(panel.querySelectorAll('a'));
            const index = links.indexOf(document.activeElement);
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                const step = event.key === 'ArrowDown' ? 1 : -1;
                links[(index + step + links.length) % links.length].focus();
            }
        });
    });

    document.addEventListener('click', function (event) {
        if (!menus.some(function (menu) { return menu.contains(event.target); })) closeAll();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        const open = menus.find(function (menu) {
            const panel = menu.querySelector('.sp-panel');
            return panel && !panel.hidden;
        });
        if (open) {
            setOpen(open, false);
            open.querySelector('button[aria-controls]').focus();
        }
    });

    const previous = window.cpCloseMenus;
    window.spCloseMenus = function () {
        closeAll();
        if (typeof previous === 'function') previous();
    };
})();

// Quick search (header search icon, or "/" key). Pages are matched in the browser;
// records come from controllers/StaffSearchController.php (read-only, filtered by role).
(function () {
    const dialog = document.getElementById('spSearch');
    if (!dialog) return;

    const input = document.getElementById('spSearchInput');
    const results = document.getElementById('spSearchResults');
    const openers = document.querySelectorAll('[data-sp-search-open]');
    let pages = [];
    try { pages = JSON.parse(document.getElementById('spSearchPages').textContent) || []; } catch (e) { pages = []; }

    let lastOpener = null;
    let timer = null;
    let request = 0;

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function open(event) {
        lastOpener = event && event.currentTarget ? event.currentTarget : document.activeElement;
        if (window.spCloseMenus) window.spCloseMenus();
        dialog.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        input.value = '';
        render([]);
        setTimeout(function () { input.focus(); }, 0);
    }

    function close() {
        dialog.hidden = true;
        document.documentElement.style.overflow = '';
        if (lastOpener && lastOpener.focus) lastOpener.focus();
    }

    function matchPages(term) {
        const t = term.toLowerCase();
        return pages.filter(function (p) {
            return (p.label + ' ' + p.group + ' ' + (p.note || '')).toLowerCase().indexOf(t) !== -1;
        }).slice(0, 5).map(function (p) {
            return { title: p.label, meta: p.group + (p.note ? ' · ' + p.note : ''), href: p.href };
        });
    }

    function render(groups, term) {
        if (!term) { results.innerHTML = ''; return; }
        const html = groups.filter(function (g) { return g.items.length; }).map(function (g) {
            return '<p class="sp-search-group">' + escapeHtml(g.label) + '</p>' + g.items.map(function (item) {
                return '<a class="sp-search-item" role="option" href="' + escapeHtml(item.href) + '">'
                    + '<span><strong>' + escapeHtml(item.title) + '</strong><small>' + escapeHtml(item.meta) + '</small></span>'
                    + '<span class="sp-search-arrow" aria-hidden="true">↗</span></a>';
            }).join('');
        }).join('');
        results.innerHTML = html || '<p class="sp-search-empty">No matches for “' + escapeHtml(term) + '”.</p>';
        const first = results.querySelector('.sp-search-item');
        if (first) first.classList.add('is-current');
    }

    function search() {
        const term = input.value.trim();
        if (term.length < 2) { render([], ''); return; }
        const pageGroup = { label: 'Pages', items: matchPages(term) };
        render([pageGroup], term);
        const id = ++request;
        fetch('../controllers/StaffSearchController.php?q=' + encodeURIComponent(term), { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : { groups: [] }; })
            .then(function (data) {
                if (id !== request) return; // a newer search is running
                render([pageGroup].concat(data.groups || []), term);
            })
            .catch(function () {});
    }

    function move(step) {
        const items = Array.from(results.querySelectorAll('.sp-search-item'));
        if (!items.length) return;
        let index = items.findIndex(function (el) { return el.classList.contains('is-current'); });
        items.forEach(function (el) { el.classList.remove('is-current'); });
        index = (index + step + items.length) % items.length;
        items[index].classList.add('is-current');
        items[index].scrollIntoView({ block: 'nearest' });
    }

    openers.forEach(function (btn) { btn.addEventListener('click', open); });
    dialog.querySelectorAll('[data-sp-search-close]').forEach(function (el) { el.addEventListener('click', close); });

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(search, 180);
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowDown') { event.preventDefault(); move(1); }
        else if (event.key === 'ArrowUp') { event.preventDefault(); move(-1); }
        else if (event.key === 'Enter') {
            const current = results.querySelector('.sp-search-item.is-current');
            if (current) { event.preventDefault(); window.location.href = current.href; }
        }
    });

    document.addEventListener('keydown', function (event) {
        if (!dialog.hidden && event.key === 'Escape') { event.preventDefault(); close(); return; }
        const tag = (event.target.tagName || '').toLowerCase();
        const typing = tag === 'input' || tag === 'textarea' || tag === 'select' || event.target.isContentEditable;
        if (dialog.hidden && event.key === '/' && !typing && !event.ctrlKey && !event.metaKey) {
            event.preventDefault();
            open();
        }
    });
})();

// A search result opens a list page with ?q=...; put it into that page's own search box.
window.addEventListener('load', function () {
    const q = new URLSearchParams(window.location.search).get('q');
    if (!q) return;
    const main = document.querySelector('.main-content') || document;
    const box = main.querySelector('[data-sp-search]')
        || main.querySelector('input[type="search"], input[id*="earch" i], input[placeholder^="Search" i]');
    if (!box) return;
    box.value = q;
    ['input', 'keyup', 'change'].forEach(function (type) {
        box.dispatchEvent(new Event(type, { bubbles: true }));
    });
});

// Popups for staff pages. Markup: <div class="sp-dialog" id="x" hidden><div class="sp-dialog-box">...</div></div>
// Open:  <button data-sp-open="#x">  (or window.spDialog.open('#x'))
// Close: any [data-sp-close] inside, the backdrop, or Esc.
// Each open fires a "sp:open" event on the dialog with event.detail.trigger = the button that opened it.
(function () {
    let current = null;
    let lastTrigger = null;

    function focusables(root) {
        return Array.from(root.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), iframe, [tabindex]:not([tabindex="-1"])'))
            .filter(function (el) { return el.offsetParent !== null; });
    }

    function open(target, trigger) {
        const dialog = typeof target === 'string' ? document.querySelector(target) : target;
        if (!dialog) return;
        if (current && current !== dialog) close(true);
        if (window.spCloseMenus) window.spCloseMenus();

        lastTrigger = trigger || document.activeElement;
        current = dialog;
        dialog.hidden = false;
        document.body.classList.add('sp-dialog-open');
        dialog.dispatchEvent(new CustomEvent('sp:open', { detail: { trigger: trigger || null } }));

        setTimeout(function () {
            const preferred = dialog.querySelector('[autofocus], .sp-dialog-body input:not([type="hidden"]), .sp-dialog-body select, .sp-dialog-body textarea');
            (preferred || focusables(dialog)[0] || dialog).focus();
        }, 20);
    }

    function close(silent) {
        if (!current) return;
        const dialog = current;
        dialog.hidden = true;
        current = null;
        document.body.classList.remove('sp-dialog-open');
        dialog.dispatchEvent(new CustomEvent('sp:close'));
        if (!silent && lastTrigger && document.contains(lastTrigger)) lastTrigger.focus();
    }

    document.addEventListener('click', function (event) {
        const opener = event.target.closest('[data-sp-open]');
        if (opener) {
            event.preventDefault();
            open(opener.getAttribute('data-sp-open'), opener);
            return;
        }
        if (!current) return;
        if (event.target.closest('[data-sp-close]') || event.target === current) {
            event.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (!current) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }
        if (event.key === 'Tab') {
            const items = focusables(current);
            if (!items.length) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });

    window.spDialog = { open: open, close: close };
})();

// Invoice / receipt viewer. A link like
//   <a href="print_invoice.php?invoice_id=5" data-sp-doc data-sp-doc-kind="Invoice" data-sp-doc-ref="INV-0091" data-sp-doc-status="Not Paid">
// opens the document in a popup with Close, Download PDF and Print. Without JavaScript the link opens the page itself.
(function () {
    let dialog = null;

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function build() {
        dialog = document.createElement('div');
        dialog.className = 'sp-dialog';
        dialog.id = 'spDocDialog';
        dialog.hidden = true;
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'spDocTitle');
        dialog.innerHTML =
            '<div class="sp-dialog-box is-wide is-doc">'
            + '<div class="cp-modal-head"><div><p class="cp-label" id="spDocRef"></p><h2 class="cp-modal-title" id="spDocTitle"></h2></div>'
            + '<div class="sp-doc-head-tools">'
            + '<a class="sp-arrow-link sp-doc-full" data-sp-doc-tab target="_blank" rel="noopener" href="#" title="Open the whole document on its own page">Full page ↗</a>'
            + '<button type="button" class="cp-modal-x" data-sp-close aria-label="Close">&times;</button>'
            + '</div></div>'
            + '<div class="sp-dialog-body sp-doc-frame-wrap"><p class="sp-doc-loading">Loading…</p><iframe class="sp-doc-frame" title="Document" scrolling="no" hidden></iframe></div>'
            + '<div class="cp-modal-foot">'
            + '<button type="button" class="cp-btn cp-btn-line" data-sp-close>Close</button>'
            + '<button type="button" class="cp-btn cp-btn-line" data-sp-doc-pdf>Download PDF</button>'
            + '<button type="button" class="cp-btn cp-btn-dark" data-sp-doc-print>Print</button>'
            + '</div></div>';
        document.body.appendChild(dialog);

        const frame = dialog.querySelector('iframe');
        const loading = dialog.querySelector('.sp-doc-loading');

        frame.addEventListener('load', function () {
            if (!frame.src || frame.src === 'about:blank') return;
            loading.hidden = true;
            frame.hidden = false;
            try {
                // Grow the frame to its content (again after the fonts load and whenever it changes size).
                const doc = frame.contentDocument;
                const fit = function () { frame.style.height = Math.max(320, doc.body.scrollHeight + 2) + 'px'; };
                fit();
                if (doc.fonts && doc.fonts.ready) doc.fonts.ready.then(fit);
                if (window.ResizeObserver) new ResizeObserver(fit).observe(doc.body);
            } catch (e) { /* different origin: keep the minimum height */ }
        });

        dialog.querySelector('[data-sp-doc-print]').addEventListener('click', function () {
            try { frame.contentWindow.focus(); frame.contentWindow.print(); } catch (e) { window.open(frame.src.replace(/[?&]embed=1/, ''), '_blank'); }
        });

        dialog.querySelector('[data-sp-doc-pdf]').addEventListener('click', function () {
            const btn = this;
            try {
                const w = frame.contentWindow;
                const fn = w.downloadInvoicePDF || w.downloadReceiptPDF;
                if (typeof fn !== 'function') throw new Error('no pdf');
                btn.disabled = true;
                btn.textContent = 'Preparing…';
                Promise.resolve(fn()).finally(function () {
                    btn.disabled = false;
                    btn.textContent = 'Download PDF';
                });
            } catch (e) {
                window.open(frame.src.replace(/[?&]embed=1/, ''), '_blank');
            }
        });

        dialog.addEventListener('sp:close', function () {
            frame.hidden = true;
            frame.removeAttribute('src');
            loading.hidden = false;
        });
    }

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[data-sp-doc]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) return;
        event.preventDefault();
        if (!dialog) build();

        const kind = link.dataset.spDocKind || 'Document';
        const status = link.dataset.spDocStatus || '';
        const badge = link.dataset.spDocBadge || '';
        dialog.querySelector('#spDocTitle').textContent = kind;
        dialog.querySelector('#spDocRef').innerHTML = '<span class="sp-mono">' + escapeHtml(link.dataset.spDocRef || '') + '</span>'
            + (status ? ' <span class="st ' + escapeHtml(badge || 'done') + '">' + escapeHtml(status) + '</span>' : '');
        dialog.querySelector('[data-sp-doc-print]').textContent = 'Print ' + kind.toLowerCase().replace('sales ', '');

        const href = link.getAttribute('href');
        dialog.querySelector('iframe').src = href + (href.indexOf('?') === -1 ? '?' : '&') + 'embed=1';
        dialog.querySelector('[data-sp-doc-tab]').href = href; // full page, with its own Download PDF and Print buttons
        window.spDialog.open(dialog, link);
    });
})();

// List filters for staff pages.
//   <div data-sp-filter="#listId">
//     <input data-sp-search>            text search over each row's text (or its data-search)
//     <select data-sp-type>              matches a row's data-type ("all" shows everything)
//     <button data-sp-chip="all|key">    matches when key is one of the row's data-status words
//     <p data-sp-showing></p>            "Showing X of Y"
//   </div>
// The list is the element whose direct children are the rows (usually a tbody). Rows that don't
// match get data-cp-filtered="1" so the pager (data-cp-paginate) skips them.
(function () {
    document.querySelectorAll('[data-sp-filter]').forEach(function (bar) {
        const list = document.querySelector(bar.getAttribute('data-sp-filter'));
        if (!list) return;

        const search = bar.querySelector('[data-sp-search]');
        const type = bar.querySelector('[data-sp-type]');
        const chips = Array.from(bar.querySelectorAll('[data-sp-chip]'));
        const showing = bar.querySelector('[data-sp-showing]');
        const emptyRow = list.querySelector('[data-sp-empty]');
        const rows = Array.from(list.children).filter(function (row) { return !row.hasAttribute('data-sp-empty'); });
        let chip = 'all';

        rows.forEach(function (row) {
            row.dataset.spText = (row.dataset.search || row.textContent).toLowerCase().replace(/\s+/g, ' ');
        });

        function apply() {
            const term = search ? search.value.trim().toLowerCase() : '';
            const kind = type ? type.value : 'all';
            let shown = 0;

            rows.forEach(function (row) {
                const statuses = (row.dataset.status || '').split(' ');
                const ok = (!term || row.dataset.spText.indexOf(term) !== -1)
                    && (kind === 'all' || row.dataset.type === kind)
                    && (chip === 'all' || statuses.indexOf(chip) !== -1);
                if (ok) {
                    shown++;
                    delete row.dataset.cpFiltered;
                    if (!list.hasAttribute('data-cp-paginate')) row.hidden = false;
                } else {
                    row.dataset.cpFiltered = '1';
                    row.hidden = true;
                }
            });

            if (showing) showing.textContent = 'Showing ' + shown + ' of ' + rows.length;
            if (window.cpRepaginate && list.hasAttribute('data-cp-paginate')) window.cpRepaginate(list, true);
            // The "no results" row never counts as a page item; show it only when nothing matches.
            if (emptyRow) {
                emptyRow.dataset.cpFiltered = '1';
                emptyRow.hidden = shown > 0 || rows.length === 0;
            }
        }

        chips.forEach(function (btn) {
            btn.addEventListener('click', function () {
                chip = btn.getAttribute('data-sp-chip');
                chips.forEach(function (b) { b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'); });
                apply();
            });
        });
        if (search) search.addEventListener('input', apply);
        if (type) type.addEventListener('change', apply);

        // Start from ?status=... when given (e.g. links from the dashboard)
        const wanted = new URLSearchParams(window.location.search).get('status');
        const preset = wanted && chips.find(function (b) { return b.getAttribute('data-sp-chip') === wanted; });
        if (preset) preset.click(); else apply();
    });
})();
