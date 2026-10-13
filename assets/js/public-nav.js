// Public site header behavior (partials/public-navbar.php).
// Small screens: the MENU button opens the links as a panel under the header. It closes on a
// link tap, on Escape, on a tap outside, or on MENU again, and the page behind does not scroll while it is open.
// Without JavaScript the links simply stay visible, so nothing is ever unreachable.
(function () {
    const header = document.querySelector('[data-pub-header]');

    if (!header) {
        return;
    }

    const toggle = header.querySelector('.pub-menu-toggle');

    function setMenu(open) {
        header.classList.toggle('menu-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.documentElement.classList.toggle('pub-menu-locked', open);
    }

    header.classList.add('is-collapsible');

    toggle.addEventListener('click', function () {
        setMenu(!header.classList.contains('menu-open'));
    });

    header.querySelectorAll('.pub-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
            setMenu(false);
        });
    });

    // Tapping anywhere outside the header closes the menu (phones have no Escape key).
    document.addEventListener('click', function (event) {
        if (header.classList.contains('menu-open') && !header.contains(event.target)) {
            setMenu(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && header.classList.contains('menu-open')) {
            setMenu(false);
            toggle.focus();
        }
    });

    // Growing the window to desktop width closes a menu left open on a small screen.
    window.matchMedia('(min-width: 980px)').addEventListener('change', function (event) {
        if (event.matches) {
            setMenu(false);
        }
    });
})();
