<?php
require_once __DIR__ . '/../includes/auth.php';

$basePath = '../';
$activePage = 'about';

// "How the shop grew" timeline. Edit the years here; the first one opens on page load.
$timeline = [
    [
        'year' => '1998',
        'title' => 'The shop opens',
        'label' => 'MacArthur Highway',
        'image' => '1998.jpg',
        'story' => "Mr. Oliver P. Dagohoy learned auto repair hands-on at his uncle's shop. In 1998 he and Mrs. Norily B. Dagohoy opened a small repair shop along MacArthur Highway in Poblacion East, Rosario, with about ₱15,000 in capital and basic automotive repair.",
    ],
    [
        'year' => '2002',
        'title' => 'Word spreads',
        'label' => 'Steady growth',
        'image' => '2002.jpg',
        'story' => 'Three years in, customer demand grew fast. Good customer service and affordable prices earned the shop a name around Rosario, while the family settled into a community far from their home provinces.',
    ],
    [
        'year' => '2000s',
        'title' => 'Rewinding work',
        'label' => 'New services',
        'image' => '2000s.jpg',
        'story' => 'The shop went beyond basic repair and began rewinding alternators, starters, submersible motors and water pumps. Rewinding work still carries a 60 to 90 day warranty today.',
    ],
    [
        'year' => '2015',
        'title' => 'Parts and supplies',
        'label' => 'Parts counter',
        'image' => '2015.jpg',
        'story' => 'The shop started selling auto parts and supplies, so customers can get the parts they need in the same place their vehicle is fixed.',
    ],
    [
        'year' => 'Today',
        'title' => 'Vila Rosario',
        'label' => 'Family property',
        'image' => 'today.jpg',
        'story' => "After MacArthur Highway and Rivera Street, the shop moved to the family's own property in Vila Rosario. It is farther from the town center, but regular and walk-in customers still come for reliable work.",
    ],
];

$team = [
    ['count' => '02', 'title' => 'Owners', 'text' => 'Mr. Oliver P. Dagohoy and Mrs. Norily B. Dagohoy run the shop and handle customer concerns personally.'],
    ['count' => '01', 'title' => 'Head mechanic', 'text' => "Checks each vehicle, gives the estimate, assigns the work and makes sure it meets the shop's standards."],
    ['count' => '06', 'title' => 'Mechanics', 'text' => 'A senior mechanic, a general mechanic and four automotive mechanics.'],
    ['count' => '02', 'title' => 'Cashier & inventory', 'text' => 'Handle payments, receipts and the parts on the shelves.'],
];

$offers = [
    ['letter' => 'A', 'title' => 'Automotive repair', 'text' => 'Vehicle repair for regular and walk-in customers.'],
    ['letter' => 'B', 'title' => 'Rewinding', 'text' => 'Alternators, starters, motors, water pumps and related rewinding work.'],
    ['letter' => 'C', 'title' => 'Electrical & wiring', 'text' => 'Automotive wiring, troubleshooting, repair and electrical support.'],
    ['letter' => 'D', 'title' => 'Parts & supplies', 'text' => 'Capacitors, starter armatures, bearings, water seals, switches, magnetic wires and auto wires.'],
];

$reasons = [
    ['figure' => '25+', 'title' => 'Years in Vila Rosario', 'text' => 'Hands-on repair experience serving the local community since 1998.'],
    ['figure' => '₱', 'title' => 'Fair pricing', 'text' => 'Known for reasonable service fees, technical skill and good word from local engineers.'],
    ['figure' => '60–90', 'title' => 'Day warranty', 'text' => 'Warranty on rewinding work, backed by dependable workmanship.'],
];

function e($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | Norily's Vehicle Repair Shop</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script>document.documentElement.classList.add('js');window.__motionFallback=setTimeout(function(){document.documentElement.classList.add('reveal-all');},3000);</script>
    <script src="../assets/js/motion.js?v=<?= filemtime(__DIR__ . '/../assets/js/motion.js') ?>" defer></script>

    <style>
        .about-page {
            background: var(--paper);
            padding-bottom: 6rem;
        }

        .about-crumbs {
            display: flex;
            gap: 0.6rem;
            list-style: none;
            padding: 0;
            margin: 1.5rem 0 3rem;
            color: var(--ink-2);
            font-size: var(--fs-crumb);
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .about-crumbs a {
            color: var(--ink-2);
            text-decoration: none;
        }

        .about-crumbs a:hover {
            color: var(--ink);
        }

        .about-crumbs li + li::before {
            content: "/";
            margin-right: 0.6rem;
        }

        .about-wrap {
            max-width: 1000px;
        }

        /* Wide display type used by every section heading */
        .wide-heading {
            color: var(--ink);
            font-weight: 400;
            font-stretch: 125%;
            text-transform: uppercase;
            line-height: 0.95;
            margin: 0;
        }

        .about-page p.kicker {
            color: var(--ink-2);
            font-size: 0.7rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        /* ---------- Hero ---------- */
        .about-hero {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 2rem;
            align-items: end;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--ink);
        }

        .about-hero h1 {
            font-size: var(--fs-page-title);
        }


        .about-hero p {
            color: var(--ink-2);
            font-size: var(--fs-lead);
            line-height: 1.6;
            max-width: 380px;
            margin: 0 0 0.75rem;
        }

        /* ---------- Story ---------- */
        .about-story {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            padding-top: 2.25rem;
        }

        .about-story-text p:not(.kicker) {
            color: var(--ink);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .about-story-text .drop-cap::first-letter {
            float: left;
            font-size: 4.6rem;
            font-stretch: 125%;
            line-height: 0.8;
            margin: 0.35rem 0.6rem 0 0;
        }

        .about-aim {
            border-top: 1px solid var(--ink);
            margin-top: 1.5rem;
            padding-top: 1rem;
        }

        .about-aim p:not(.kicker) {
            font-size: 1.3rem;
            line-height: 1.35;
            margin: 0;
        }

        .about-story-media {
            margin: 0;
        }

        .about-story-media img {
            display: block;
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: cover;
            filter: grayscale(1);
        }

        .about-story-media figcaption {
            color: var(--ink-2);
            font-size: 0.68rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-top: 0.75rem;
        }

        /* ---------- Section heading row ---------- */
        .about-section {
            margin-top: 6rem;
        }

        .section-head {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr);
            gap: 2rem;
            align-items: end;
            padding-bottom: 1.25rem;
            margin-bottom: 1.1rem;
            border-bottom: 1px solid var(--ink);
        }

        .section-head h2 {
            font-size: var(--fs-section-title);
        }

        .section-head p {
            color: var(--ink-2);
            font-size: 0.95rem;
            line-height: 1.55;
            margin: 0 0 0.3rem;
        }

        /* ---------- Timeline (expanding columns) ---------- */
        .grew-timeline {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            min-height: 560px;
            border-top: 1px solid var(--ink);
            border-left: 1px solid var(--line);
        }

        .grew-item {
            position: relative;
            flex: 1 1 0;
            min-width: 0;
            display: flex;
            flex-direction: column;
            padding: 1.25rem 1.1rem 1rem;
            border-right: 1px solid var(--line);
            cursor: pointer;
            transition: flex-grow 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .grew-item.is-open {
            flex-grow: 3;
            cursor: default;
            padding: 1.5rem 1.25rem 1rem;
        }

        .grew-head {
            display: block;
            width: 100%;
            text-align: left;
            background: transparent;
            border: 0;
            padding: 0;
            color: inherit;
            cursor: inherit;
        }

        .grew-head:focus-visible {
            outline: 2px solid var(--ink);
            outline-offset: 4px;
        }

        .grew-year {
            display: block;
            color: var(--ink-2);
            font-size: clamp(1.75rem, 2.8vw, 2.5rem);
            font-weight: 700;
            line-height: 1;
            margin-bottom: 1.25rem;
            transition: font-size 0.5s cubic-bezier(0.4, 0, 0.2, 1), color 0.2s ease;
        }

        .grew-item:not(.is-open):hover .grew-year {
            color: var(--ink);
        }

        .grew-item.is-open .grew-year {
            color: var(--ink);
            font-size: clamp(3.5rem, 5.8vw, 5.25rem);
        }

        .grew-title {
            display: block;
            color: var(--ink);
            font-size: 0.78rem;
            font-weight: 700;
            font-stretch: 112%;
            letter-spacing: 0.08em;
            line-height: 1.35;
            text-transform: uppercase;
            margin-bottom: 0.4rem;
        }

        .grew-label {
            display: block;
            color: var(--ink-2);
            font-size: 0.78rem;
        }

        .grew-media {
            width: min(150px, 100%);
            aspect-ratio: 16 / 10;
            margin-top: 1.5rem;
            overflow: hidden;
            background: var(--tile); /* shows when an image file is missing */
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .grew-item.is-open .grew-media {
            width: min(520px, 100%);
        }

        .grew-media img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: grayscale(1);
            transition: filter 0.5s ease;
        }

        .grew-item.is-open .grew-media img {
            filter: none;
        }

        .grew-story {
            color: var(--ink-2);
            font-size: 0.85rem;
            line-height: 1.65;
            max-width: 520px;
            margin: 1.5rem 0 0;
            opacity: 0;
            visibility: hidden;
            max-height: 0;
            overflow: hidden;
        }

        /* The story fades in just after the column has opened */
        .grew-item.is-open .grew-story {
            opacity: 1;
            visibility: visible;
            max-height: 24em;
            transition: opacity 0.35s ease 0.35s, visibility 0s linear 0.35s;
        }

        .grew-count {
            margin-top: auto;
            padding-top: 1.5rem;
            color: var(--ink-2);
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            font-size: 0.68rem;
            letter-spacing: 0.08em;
        }

        /* ---------- Cards (team, offers) ---------- */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border-top: 1px solid var(--line);
            border-left: 1px solid var(--line);
        }

        .info-card {
            padding: 1.25rem 1.1rem 1.5rem;
            border-right: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .info-card-index {
            display: block;
            color: var(--ink-2);
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            font-size: 0.68rem;
            margin-bottom: 0.9rem;
        }

        .info-card h3 {
            color: var(--ink);
            font-size: 0.95rem;
            font-weight: 700;
            font-stretch: 112%;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .info-card p {
            color: var(--ink-2);
            font-size: 0.82rem;
            line-height: 1.6;
            margin: 0;
        }

        .about-values {
            margin-top: 2rem;
        }

        .about-values p:not(.kicker) {
            color: var(--ink);
            font-size: clamp(1.05rem, 1.8vw, 1.3rem);
            font-stretch: 112%;
            text-transform: uppercase;
            margin: 0;
        }

        /* ---------- Why customers come back ---------- */
        .reasons-head h2 {
            font-size: var(--fs-section-title);
            padding-bottom: 1.25rem;
        }

        .reasons-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border-top: 3px double var(--ink);
            padding-top: 1rem;
        }

        .reason {
            padding: 0 1.25rem 0 0;
        }

        .reason + .reason {
            border-left: 1px solid var(--line);
            padding-left: 1.25rem;
        }

        .reason-figure {
            display: block;
            color: var(--ink);
            font-size: clamp(2.6rem, 4.5vw, 3.4rem);
            font-weight: 400;
            font-stretch: 112%;
            line-height: 1;
            margin-bottom: 0.9rem;
        }

        .reason h3 {
            color: var(--ink);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 0.6rem;
        }

        .reason p {
            color: var(--ink-2);
            font-size: 0.85rem;
            line-height: 1.6;
            margin: 0;
        }

        /* ---------- Tablet ---------- */
        @media (max-width: 991.98px) {
            .card-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* ---------- Phone: timeline becomes an accordion ---------- */
        @media (max-width: 860px) {
            .about-hero,
            .about-story,
            .section-head {
                grid-template-columns: 1fr;
            }

            .about-hero {
                gap: 1rem;
            }

            .grew-timeline {
                flex-direction: column;
                min-height: 0;
                border-left: 0;
            }

            .grew-item,
            .grew-item.is-open {
                padding: 1rem 0;
                border-right: 0;
                border-bottom: 1px solid var(--line);
            }

            .grew-head {
                display: grid;
                grid-template-columns: 6.5rem 1fr;
                align-items: center;
                column-gap: 1rem;
            }

            .grew-year,
            .grew-item.is-open .grew-year {
                grid-row: span 2;
                font-size: 2.1rem;
                margin: 0;
            }

            .grew-title {
                margin-bottom: 0.15rem;
            }

            .grew-media,
            .grew-item.is-open .grew-media {
                width: 100%;
                margin-top: 1rem;
            }

            .grew-item:not(.is-open) .grew-media {
                display: none;
            }

            .grew-story {
                margin-top: 1rem;
            }

            .grew-count {
                display: none;
            }

            .reasons-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .reason + .reason {
                border-left: 0;
                padding-left: 0;
                border-top: 1px solid var(--line);
                padding-top: 1.25rem;
            }
        }

        @media (max-width: 575.98px) {
            .card-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .grew-item,
            .grew-year,
            .grew-media,
            .grew-media img,
            .grew-item.is-open .grew-story {
                transition: none;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="about-page">
    <div class="container about-wrap">
        <nav aria-label="Breadcrumb">
            <ol class="about-crumbs">
                <li><a href="../" class="link-grow">Home</a></li>
                <li aria-current="page">About</li>
            </ol>
        </nav>

        <section class="about-hero">
            <h1 class="wide-heading">Since<br><span class="title-band">1998</span></h1>
            <p>A trusted local repair shop in Vila Rosario, La Union, run by the Dagohoy family.</p>
        </section>

        <section class="about-story">
            <div class="about-story-text" data-reveal>
                <p class="kicker">Our story</p>

                <p class="drop-cap">
                    Mr. Oliver P. Dagohoy never finished formal schooling. He learned auto repair with his
                    own hands, working for years at his uncle's repair shop. In 1998 he and Mrs. Norily B.
                    Dagohoy opened their own small shop with about ₱15,000, raised by selling five pigs,
                    to build a steadier future for their children.
                </p>

                <p>
                    Accurate repairs and fair prices built a loyal customer base. Today the shop runs on the
                    family's own property in Vila Rosario and serves cars, tricycles and the farm equipment
                    the community depends on.
                </p>

                <div class="about-aim">
                    <p class="kicker">Our aim</p>
                    <p>To give the community accurate repairs, fair pricing and dependable workmanship it can trust.</p>
                </div>
            </div>

            <figure class="about-story-media" data-reveal>
                <img src="../assets/images/landing/about-bench.jpg" alt="Mechanic working on a starter armature at the bench" loading="lazy">
                <figcaption>Motor rewinding at the bench</figcaption>
            </figure>
        </section>

        <section class="about-section" aria-labelledby="grewHeading">
            <div class="section-head" data-reveal>
                <h2 class="wide-heading" id="grewHeading">How the<br>shop grew</h2>
                <p>From one small repair shop to repairs, rewinding and parts.</p>
            </div>

            <ol class="grew-timeline" data-reveal>
                <?php foreach ($timeline as $index => $item): ?>
                    <?php $isOpen = $index === 0; ?>
                    <li class="grew-item<?= $isOpen ? ' is-open' : '' ?>">
                        <button
                            type="button"
                            class="grew-head"
                            id="grew-head-<?= $index ?>"
                            aria-expanded="<?= $isOpen ? 'true' : 'false' ?>"
                            aria-controls="grew-body-<?= $index ?>"
                        >
                            <span class="grew-year"><?= e($item['year']) ?></span>
                            <span class="grew-title"><?= e($item['title']) ?></span>
                            <span class="grew-label"><?= e($item['label']) ?></span>
                        </button>

                        <div class="grew-body" id="grew-body-<?= $index ?>" role="region" aria-labelledby="grew-head-<?= $index ?>">
                            <div class="grew-media">
                                <img
                                    src="../assets/images/about/<?= e($item['image']) ?>?v=<?= @filemtime(__DIR__ . '/../assets/images/about/' . $item['image']) ?: 0 ?>"
                                    alt="<?= e($item['year'] . ' – ' . $item['title']) ?>"
                                    loading="lazy"
                                    onerror="this.remove()"
                                >
                            </div>

                            <p class="grew-story"><?= e($item['story']) ?></p>
                        </div>

                        <span class="grew-count" aria-hidden="true">
                            <?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?> / <?= str_pad(count($timeline), 2, '0', STR_PAD_LEFT) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>

        <section class="about-section" aria-labelledby="teamHeading">
            <div class="section-head" data-reveal>
                <h2 class="wide-heading" id="teamHeading">The team</h2>
                <p>Every job is checked by the head mechanic before it is released.</p>
            </div>

            <div class="card-grid" data-reveal-group>
                <?php foreach ($team as $member): ?>
                    <div class="info-card">
                        <span class="info-card-index"><?= e($member['count']) ?></span>
                        <h3><?= e($member['title']) ?></h3>
                        <p><?= e($member['text']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="about-values" data-reveal>
                <p class="kicker">What we value</p>
                <p>Diligence &middot; Hard work &middot; Perseverance &middot; Good relationships</p>
            </div>
        </section>

        <section class="about-section" aria-labelledby="offersHeading">
            <div class="section-head" data-reveal>
                <h2 class="wide-heading" id="offersHeading">What the<br>shop offers</h2>
                <p>Four kinds of work, one counter.</p>
            </div>

            <div class="card-grid" data-reveal-group>
                <?php foreach ($offers as $offer): ?>
                    <div class="info-card">
                        <span class="info-card-index"><?= e($offer['letter']) ?></span>
                        <h3><?= e($offer['title']) ?></h3>
                        <p><?= e($offer['text']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="about-section" aria-labelledby="reasonsHeading">
            <div class="reasons-head" data-reveal>
                <h2 class="wide-heading" id="reasonsHeading">Why<br>customers<br>come back</h2>
            </div>

            <div class="reasons-grid" data-reveal-group data-count-group>
                <?php foreach ($reasons as $reason): ?>
                    <div class="reason">
                        <span class="reason-figure"<?= preg_match('/\d/', $reason['figure']) ? ' data-count' : '' ?>><?= e($reason['figure']) ?></span>
                        <h3><?= e($reason['title']) ?></h3>
                        <p><?= e($reason['text']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// "How the shop grew": one year open at a time. Click anywhere on a closed column, or use
// Enter/Space on its button; the arrow keys move to and open the next or previous year.
(function () {
    const items = Array.from(document.querySelectorAll('.grew-item'));
    const heads = items.map(function (item) { return item.querySelector('.grew-head'); });

    function openItem(index) {
        items.forEach(function (item, i) {
            const isOpen = i === index;
            item.classList.toggle('is-open', isOpen);
            heads[i].setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    items.forEach(function (item, index) {
        item.addEventListener('click', function () {
            if (!item.classList.contains('is-open')) {
                openItem(index);
            }
        });

        heads[index].addEventListener('keydown', function (event) {
            const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[event.key];

            if (!step) {
                return;
            }

            event.preventDefault();
            const next = (index + step + items.length) % items.length;
            openItem(next);
            heads[next].focus();
        });
    });
})();
</script>

</body>
</html>
