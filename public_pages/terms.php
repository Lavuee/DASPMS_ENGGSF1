<?php
$basePath = '../';
$activePage = '';
$lastUpdated = 'October 2026';

// Each section: a title and either 'text' (a paragraph) or 'list' (bullet points).
$sections = [
    [
        'title' => 'Use of the website',
        'text' => 'The website helps customers browse repair services, view parts and supplies, create an account and request services. Give accurate information when you register, request a service or send a message.',
    ],
    [
        'title' => 'Your account',
        'text' => "Keep your login details private. Activity on your account is treated as yours. Tell the shop right away if you notice access you didn't make or wrong account details.",
    ],
    [
        'title' => 'Service requests',
        'text' => 'Guests can browse services but need an account to request one. A request is not an approval: the shop may confirm, reschedule or decline it depending on availability, capacity and the repair needed.',
    ],
    [
        'title' => 'Estimates, payment and warranty',
        'list' => [
            'Repair work starts only after you approve the estimate from the head mechanic.',
            'The minimum service fee is ₱450. Any lower price needs the owner\'s approval.',
            'Services marked as requiring a down payment, such as rewinding, wiring overhauls and special-order parts, need 50% of the estimate before work starts. Down payments are non-refundable once the service has started.',
            'Balances are paid before the vehicle or parts are released. If the shop allows you to pay later, balances for services must be settled within 7 days.',
            'Your original official receipt is your warranty proof. Without a receipt there is no return, exchange or free back-job.',
        ],
    ],
    [
        'title' => 'Parts and supplies',
        'text' => 'Parts on the website are shown for reference and reservation. Availability can change with inventory, and the shop may update item details when needed. Reservations are for pickup only and are confirmed once the shop approves them.',
    ],
    [
        'title' => 'Your information',
        'text' => 'Keep your customer, vehicle, contact and request details correct. Wrong or missing details can delay confirmation, repair tracking and communication.',
    ],
    [
        'title' => 'Service limits',
        'text' => 'Estimates, timelines and recommendations can change after the vehicle or component is inspected. Final repair decisions are confirmed by the shop.',
    ],
    [
        'title' => 'Not allowed',
        'list' => [
            'Submitting false, misleading or unauthorized information.',
            'Trying to open accounts, records or pages without permission.',
            'Disrupting or damaging the website or its database.',
            'Using the system for fraud or abuse.',
        ],
    ],
    [
        'title' => 'Changes',
        'text' => 'The shop may update these terms when the system, services or processes change. Using the website after an update means you accept the new terms.',
    ],
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
    <title>Terms &amp; Conditions | Norily's Vehicle Repair Shop</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script>document.documentElement.classList.add('js');window.__motionFallback=setTimeout(function(){document.documentElement.classList.add('reveal-all');},3000);</script>
    <script src="../assets/js/motion.js?v=<?= filemtime(__DIR__ . '/../assets/js/motion.js') ?>" defer></script>

    <style>
        .terms-page {
            background: var(--paper);
            padding-bottom: 6rem;
        }

        .terms-wrap {
            max-width: 1000px;
        }

        .terms-crumbs {
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

        .terms-crumbs a {
            color: var(--ink-2);
            text-decoration: none;
        }

        .terms-crumbs a:hover {
            color: var(--ink);
        }

        .terms-crumbs li + li::before {
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

        /* ---------- Hero ---------- */
        .terms-hero {
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--ink);
        }

        .terms-hero h1 {
            font-size: var(--fs-page-title);
            overflow-wrap: anywhere;
        }

        /* Sits on its own line under the title, with clear space above it */
        .terms-updated {
            display: block;
            color: var(--ink-2);
            font-size: 0.75rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            text-align: right;
            margin: 2rem 0 0;
        }

        /* ---------- Body ---------- */
        .terms-body {
            max-width: 640px;
            margin: 2.5rem auto 0;
        }

        .terms-intro {
            color: var(--ink);
            font-size: 1.15rem;
            line-height: 1.6;
            margin-bottom: 0.5rem;
        }

        .terms-section {
            padding: 2.25rem 0 2.5rem;
            border-bottom: 1px solid var(--line);
        }

        .terms-section h2 {
            display: flex;
            align-items: baseline;
            gap: 0.75rem;
            color: var(--ink);
            font-size: clamp(1.15rem, 2vw, 1.35rem);
            font-weight: 400;
            font-stretch: 125%;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        .terms-number {
            color: var(--ink-2);
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
            font-size: 0.75rem;
            font-stretch: normal;
        }

        .terms-section p,
        .terms-section li {
            color: var(--ink);
            font-size: 0.95rem;
            line-height: 1.65;
        }

        .terms-section p {
            margin: 0;
        }

        .terms-section ul {
            margin: 0;
            padding-left: 1.1rem;
        }

        .terms-section li + li {
            margin-top: 0.5rem;
        }

        @media (max-width: 575.98px) {
            .terms-updated {
                text-align: left;
                margin-top: 1.25rem;
            }

            .terms-intro {
                font-size: 1.05rem;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="terms-page">
    <div class="container terms-wrap">
        <nav aria-label="Breadcrumb">
            <ol class="terms-crumbs">
                <li><a href="../" class="link-grow">Home</a></li>
                <li aria-current="page">Terms &amp; conditions</li>
            </ol>
        </nav>

        <header class="terms-hero">
            <h1 class="wide-heading">Terms &amp;<br>Conditions</h1>
            <p class="terms-updated">Last updated &middot; <?= e($lastUpdated) ?></p>
        </header>

        <div class="terms-body">
            <p class="terms-intro">
                By browsing services, viewing parts, creating an account or sending a request on the
                Norily's Vehicle Repair Shop website, you agree to these terms.
            </p>

            <?php foreach ($sections as $index => $section): ?>
                <section class="terms-section" data-reveal id="term-<?= $index + 1 ?>">
                    <h2>
                        <span class="terms-number"><?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?></span>
                        <?= e($section['title']) ?>
                    </h2>

                    <?php if (!empty($section['list'])): ?>
                        <ul>
                            <?php foreach ($section['list'] as $item): ?>
                                <li><?= e($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p><?= e($section['text']) ?></p>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
