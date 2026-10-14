// Public page motion (styles under "Animations" in assets/css/style.css).
// Reveal on scroll, count-up numbers, gallery cross-fade and the landing tab switch.
// Everything is skipped when the user prefers reduced motion, and the content is
// already in its final state in the HTML, so nothing depends on this file running.
(function () {
    const root = document.documentElement;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // motion.js loaded: cancel the "show everything" safety net from the inline <head> script.
    clearTimeout(window.__motionFallback);

    /* ---------- 2. Reveal on scroll ---------- */
    const STAGGER_MS = 70;
    const STAGGER_CAP = 6;

    function staggerChildren(group) {
        Array.from(group.children).forEach(function (child, index) {
            child.style.setProperty('--reveal-delay', (Math.min(index, STAGGER_CAP - 1) * STAGGER_MS) + 'ms');
        });
    }

    function revealAll() {
        document.querySelectorAll('[data-reveal], [data-reveal-group]').forEach(function (el) {
            el.classList.add('is-in');
        });
    }

    function setUpReveal() {
        const targets = document.querySelectorAll('[data-reveal], [data-reveal-group]');

        if (reduceMotion || !('IntersectionObserver' in window)) {
            revealAll();
            return;
        }

        document.querySelectorAll('[data-reveal-group]').forEach(staggerChildren);

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-in');
                    observer.unobserve(entry.target);

                    if (entry.target.hasAttribute('data-count-group')) {
                        countUpWithin(entry.target);
                    }
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -10% 0px' });

        const viewportHeight = window.innerHeight;

        targets.forEach(function (el) {
            const rect = el.getBoundingClientRect();

            // Already on screen at load: show right away instead of waiting for a scroll.
            if (rect.top < viewportHeight && rect.bottom > 0 && rect.height > 0) {
                el.classList.add('is-in');

                if (el.hasAttribute('data-count-group')) {
                    countUpWithin(el);
                }
            } else {
                observer.observe(el);
            }
        });
    }

    /* ---------- 6. Count-up numbers ---------- */
    // Every number in a [data-count] element counts up from 0; other characters ("+", "–") stay put.
    // The real final text is already in the HTML and is restored at the end.
    const COUNT_MS = 900;

    function countUp(el) {
        if (el.dataset.counted) {
            return;
        }

        el.dataset.counted = '1';
        const finalText = el.textContent;
        const parts = finalText.split(/(\d+)/);

        if (parts.length < 2) {
            return;
        }

        const start = performance.now();

        function frame(now) {
            const t = Math.min((now - start) / COUNT_MS, 1);
            const eased = 1 - Math.pow(1 - t, 3);

            el.textContent = parts.map(function (part) {
                return /^\d+$/.test(part) ? String(Math.round(Number(part) * eased)) : part;
            }).join('');

            if (t < 1) {
                requestAnimationFrame(frame);
            } else {
                el.textContent = finalText;
            }
        }

        requestAnimationFrame(frame);
    }

    function countUpWithin(container) {
        if (reduceMotion) {
            return;
        }

        container.querySelectorAll('[data-count]').forEach(countUp);
    }

    /* ---------- 5. Gallery cross-fade (part and service details) ---------- */
    function setUpGallery(gallery) {
        const current = gallery.querySelector('[data-gallery-main]');
        const thumbs = gallery.querySelectorAll('[data-full]');

        if (!current || thumbs.length === 0) {
            return;
        }

        const altText = current.alt;

        // A second image in the same box: the new angle loads there, then the two swap opacity.
        const layer = current.cloneNode(false);
        layer.removeAttribute('id');
        layer.removeAttribute('data-gallery-main');
        layer.alt = '';
        layer.setAttribute('aria-hidden', 'true');
        layer.classList.add('gallery-layer');
        current.after(layer);

        let front = current;
        let back = layer;
        let request = 0;

        function show(src) {
            const ticket = ++request;

            if (reduceMotion) {
                front.src = src;
                return;
            }

            back.src = src;

            const ready = back.decode ? back.decode() : Promise.resolve();

            ready.catch(function () {}).then(function () {
                // Only the last click wins.
                if (ticket !== request) {
                    return;
                }

                back.style.opacity = '1';
                front.style.opacity = '0';
                back.alt = altText;
                back.removeAttribute('aria-hidden');
                front.alt = '';
                front.setAttribute('aria-hidden', 'true');

                const previous = front;
                front = back;
                back = previous;
            });
        }

        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                thumbs.forEach(function (other) {
                    other.classList.toggle('active', other === thumb);
                    other.setAttribute('aria-pressed', other === thumb ? 'true' : 'false');
                });

                show(thumb.dataset.full);
            });
        });
    }

    /* ---------- 4. Landing tabs (Parts / Services) ---------- */
    function setUpTabs(tablist) {
        const tabs = Array.from(tablist.querySelectorAll('[role="tab"]'));
        const indicator = tablist.querySelector('.offerings-tab-indicator');

        function moveIndicator(tab) {
            if (!indicator || !tab) {
                return;
            }

            const x = tab.offsetLeft + (tab.offsetWidth - indicator.offsetWidth) / 2;
            indicator.style.transform = 'translateX(' + x + 'px)';
        }

        function activate(tab, focus) {
            const incoming = document.getElementById(tab.getAttribute('aria-controls'));
            const outgoingTab = tabs.find(function (t) { return t.getAttribute('aria-selected') === 'true'; });
            const outgoing = outgoingTab && outgoingTab !== tab ? document.getElementById(outgoingTab.getAttribute('aria-controls')) : null;

            tabs.forEach(function (t) {
                const selected = t === tab;
                t.setAttribute('aria-selected', selected ? 'true' : 'false');
                t.classList.toggle('active', selected);
                t.tabIndex = selected ? 0 : -1;
            });

            if (focus) {
                tab.focus();
            }

            moveIndicator(tab);

            // Page-specific work (CTA link text, carousel arrows) lives in the landing page script.
            tablist.dispatchEvent(new CustomEvent('tabchange', { detail: { tab: tab.dataset.offeringTab } }));

            if (!outgoing || reduceMotion) {
                if (outgoing) {
                    outgoing.classList.remove('active');
                }
                incoming.classList.add('active');
                tablist.dispatchEvent(new CustomEvent('tabshown'));
                return;
            }

            outgoing.classList.add('is-leaving');

            setTimeout(function () {
                outgoing.classList.remove('active', 'is-leaving');
                incoming.classList.add('active', 'is-entering');
                tablist.dispatchEvent(new CustomEvent('tabshown'));

                setTimeout(function () {
                    incoming.classList.remove('is-entering');
                }, 600);
            }, 150);
        }

        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () {
                if (tab.getAttribute('aria-selected') !== 'true') {
                    activate(tab, false);
                }
            });

            // Arrow keys move between tabs (standard tab pattern).
            tab.addEventListener('keydown', function (event) {
                const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key];

                if (step) {
                    event.preventDefault();
                    activate(tabs[(index + step + tabs.length) % tabs.length], true);
                }
            });
        });

        // Opened from a link such as ?tab=services
        tablist.selectTab = function (name) {
            const tab = tabs.find(function (t) { return t.dataset.offeringTab === name; });

            if (tab && tab.getAttribute('aria-selected') !== 'true') {
                activate(tab, false);
            }
        };

        const initial = tabs.find(function (t) { return t.getAttribute('aria-selected') === 'true'; }) || tabs[0];
        moveIndicator(initial);
        window.addEventListener('resize', function () {
            moveIndicator(tabs.find(function (t) { return t.getAttribute('aria-selected') === 'true'; }));
        });
        // Web fonts can change tab widths after load.
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () { moveIndicator(initial); });
        }
    }

    function init() {
        setUpReveal();
        document.querySelectorAll('[data-gallery]').forEach(setUpGallery);
        document.querySelectorAll('[data-tablist]').forEach(setUpTabs);
        root.classList.add('motion-ready');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
