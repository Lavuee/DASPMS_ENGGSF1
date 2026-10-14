<?php
$basePath = '../';
$activePage = '';

// Same number as the footer and contact page; change them together.
$shopPhone = '0912-345-6789';
$mapQuery = 'Vila Rosario, La Union';
$mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($mapQuery);

$tips = [
    ['title' => 'For repairs', 'text' => 'Describe the problem clearly: when it happens, any sounds or warning lights, and what was fixed before.'],
    ['title' => 'For parts', 'text' => 'Check the parts page or call first. Bring the old part or its number if you can.'],
    ['title' => 'For follow-ups', 'text' => 'Log in to see your repair status, or call the shop to ask about an update.'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Location | Norily's Vehicle Repair Shop</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script>document.documentElement.classList.add('js');window.__motionFallback=setTimeout(function(){document.documentElement.classList.add('reveal-all');},3000);</script>
    <script src="../assets/js/motion.js?v=<?= filemtime(__DIR__ . '/../assets/js/motion.js') ?>" defer></script>

    <style>
        .loc-page {
            background: var(--paper);
            padding-bottom: 6rem;
        }

        .loc-wrap {
            max-width: 1000px;
        }

        .loc-crumbs {
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

        .loc-crumbs a {
            color: var(--ink-2);
            text-decoration: none;
        }

        .loc-crumbs a:hover {
            color: var(--ink);
        }

        .loc-crumbs li + li::before {
            content: "/";
            margin-right: 0.6rem;
        }

        .wide-heading {
            color: var(--ink);
            font-weight: 400;
            font-stretch: 125%;
            text-transform: uppercase;
            line-height: 0.95;
            margin: 0;
        }

        .mono {
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
        }

        /* ---------- Hero ---------- */
        .loc-hero {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1.75rem 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--ink);
        }

        .loc-hero h1 {
            font-size: var(--fs-page-title);
        }




        .loc-hero p {
            flex: 0 1 340px;
            color: var(--ink-2);
            font-size: var(--fs-lead);
            line-height: 1.6;
            margin: 0 0 0.5rem auto;
        }

        /* ---------- Map + details ---------- */
        .loc-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
            gap: 3rem;
            align-items: start;
            padding-top: 1.75rem;
        }

        .loc-map {
            position: relative;
            aspect-ratio: 4 / 3;
            border: 1px solid var(--ink);
            background: var(--sheet);
            overflow: hidden;
        }

        /* Greyscale map so it matches the page */
        .loc-map iframe {
            display: block;
            width: 100%;
            height: 100%;
            border: 0;
            filter: grayscale(1) contrast(0.95);
        }

        .loc-details {
            list-style: none;
            padding: 0;
            margin: 0;
            border-top: 1px solid var(--ink);
        }

        .loc-detail {
            padding: 1.05rem 0 1.1rem;
            border-bottom: 1px solid var(--line);
        }

        .loc-label {
            display: block;
            color: var(--ink-2);
            font-size: var(--fs-label);
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-bottom: 0.45rem;
        }

        .loc-value {
            color: var(--ink);
            font-size: 1.05rem;
            font-stretch: 112%;
            text-transform: uppercase;
            margin: 0;
        }

        .loc-sub {
            color: var(--ink);
            font-size: 0.85rem;
            margin: 0.35rem 0 0;
        }

        .loc-phone {
            color: var(--ink);
            font-size: 1.15rem;
            text-decoration: none;
        }

        .loc-phone:hover {
            color: var(--ink);
        }

        .loc-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }

        .loc-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 1.3rem;
            border: 1px solid var(--ink);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            text-decoration: none;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .loc-btn-solid {
            background: var(--ink);
            color: var(--sheet);
        }

        .loc-btn-solid:hover {
            background: #3a372f;
            color: var(--sheet);
        }

        .loc-btn-outline {
            background: transparent;
            color: var(--ink);
        }

        .loc-btn-outline:hover {
            background: var(--ink);
            color: var(--sheet);
        }

        /* ---------- We moved ---------- */
        .loc-moved {
            display: grid;
            grid-template-columns: 9rem minmax(0, 1fr);
            gap: 1.5rem;
            margin-top: 3.5rem;
            padding: 1.25rem 1.5rem;
            background: var(--sheet);
            border: 1px solid var(--ink);
        }

        .loc-moved p {
            color: var(--ink);
            font-size: 0.92rem;
            line-height: 1.6;
            margin: 0;
        }

        /* ---------- Before you visit ---------- */
        .loc-section {
            margin-top: 6rem;
        }

        .loc-section-head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1.25rem 2.5rem;
            padding-bottom: 1.25rem;
            margin-bottom: 1.1rem;
            border-bottom: 1px solid var(--ink);
        }

        .loc-section-head h2 {
            font-size: var(--fs-section-title);
        }

        .loc-section-head p {
            flex: 0 1 380px;
            color: var(--ink-2);
            font-size: var(--fs-lead);
            line-height: 1.55;
            margin: 0 0 0.3rem auto;
        }

        .loc-tips {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border-top: 1px solid var(--ink);
            padding-top: 1.25rem;
        }

        .loc-tip {
            padding-right: 1.5rem;
        }

        .loc-tip + .loc-tip {
            padding-left: 1.5rem;
            border-left: 1px solid var(--line);
        }

        .loc-tip h3 {
            color: var(--ink);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 0.6rem;
        }

        .loc-tip p {
            color: var(--ink-2);
            font-size: 0.88rem;
            line-height: 1.6;
            margin: 0;
        }

        @media (max-width: 860px) {
            .loc-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .loc-hero p,
            .loc-section-head p {
                flex-basis: 100%;
                margin-left: 0;
            }

            .loc-moved {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .loc-tips {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }

            .loc-tip,
            .loc-tip + .loc-tip {
                padding: 0;
                border-left: 0;
            }

            .loc-tip + .loc-tip {
                padding-top: 1.25rem;
                border-top: 1px solid var(--line);
            }
        }

        @media (max-width: 575.98px) {

            .loc-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="loc-page">
    <div class="container loc-wrap">
        <nav aria-label="Breadcrumb">
            <ol class="loc-crumbs">
                <li><a href="../" class="link-grow">Home</a></li>
                <li aria-current="page">Store location</li>
            </ol>
        </nav>

        <section class="loc-hero">
            <h1 class="wide-heading">
                Find the<br>
                <span class="title-band">Shop</span>
            </h1>

            <p>Walk-ins are welcome for repairs, rewinding, wiring concerns and parts.</p>
        </section>

        <div class="loc-grid">
            <div class="loc-map" data-reveal>
                <iframe
                    src="https://www.google.com/maps?q=<?= rawurlencode($mapQuery) ?>&output=embed"
                    title="Map of Vila Rosario, La Union"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen
                ></iframe>
            </div>

            <div>
                <ul class="loc-details">
                    <li class="loc-detail">
                        <span class="loc-label">Address</span>
                        <p class="loc-value">Vila Rosario, La Union</p>
                    </li>

                    <li class="loc-detail">
                        <span class="loc-label">Hours</span>
                        <p class="loc-value">Mon &ndash; Sat &middot; 8:00 AM &ndash; 5:00 PM</p>
                        <p class="loc-sub">Sunday closed</p>
                    </li>

                    <li class="loc-detail">
                        <span class="loc-label">Phone</span>
                        <a href="tel:<?= preg_replace('/\D/', '', $shopPhone) ?>" class="loc-phone mono"><?= $shopPhone ?></a>
                    </li>
                </ul>

                <div class="loc-actions">
                    <a href="<?= htmlspecialchars($mapsUrl, ENT_QUOTES, 'UTF-8') ?>" class="loc-btn loc-btn-solid" target="_blank" rel="noopener">
                        Open in Google Maps <span class="arrow" aria-hidden="true">&nbsp;&#8599;</span>
                    </a>
                    <a href="contact.php" class="loc-btn loc-btn-outline">Contact the shop</a>
                </div>
            </div>
        </div>

        <aside class="loc-moved" data-reveal>
            <span class="loc-label">We moved</span>
            <p>
                Looking for the old shop on MacArthur Highway or Rivera Street? Norily's is now in
                Vila Rosario, a little farther from the town center, on the family's own property.
            </p>
        </aside>

        <section class="loc-section" aria-labelledby="visitHeading">
            <div class="loc-section-head" data-reveal>
                <h2 class="wide-heading" id="visitHeading">Before<br>you visit</h2>
                <p>A little preparation helps the shop assess your vehicle faster.</p>
            </div>

            <div class="loc-tips" data-reveal-group>
                <?php foreach ($tips as $tip): ?>
                    <div class="loc-tip">
                        <h3><?= htmlspecialchars($tip['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p><?= htmlspecialchars($tip['text'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
