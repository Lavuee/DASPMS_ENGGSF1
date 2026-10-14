<?php
require_once __DIR__ . '/../includes/auth.php';

$basePath = '../';
$activePage = 'how-it-works';

// The seven steps. "who" says who does that step.
$steps = [
    [
        'title' => 'Browse services and parts',
        'who' => 'Anyone',
        'text' => "Look through the shop's repair services, categories, parts and supplies before deciding what you need.",
    ],
    [
        'title' => 'Search',
        'who' => 'Anyone',
        'text' => 'Find a service or part by name, brand or category. If nothing matches, the page tells you so you can try another word.',
    ],
    [
        'title' => 'Log in or create an account',
        'who' => 'Customer',
        'text' => 'Requests and reservations are linked to your profile, so you need an account with a valid contact number.',
    ],
    [
        'title' => 'Request a service or reserve parts',
        'who' => 'Customer',
        'text' => 'Send your vehicle details, the concern and a preferred date, or reserve parts for pickup. A reservation is only confirmed once the shop approves it.',
    ],
    [
        'title' => 'Wait for the shop to confirm',
        'who' => 'Shop',
        'text' => 'The shop reviews every request. It may be confirmed, rescheduled or updated depending on availability and the repair needed.',
    ],
    [
        'title' => 'Follow the status',
        'who' => 'Customer',
        'text' => 'Check your repair or order online: pending, in progress, ready for pickup, completed.',
    ],
    [
        'title' => 'Pick up and pay',
        'who' => 'At the shop',
        'text' => 'Come in when it says ready for pickup. Pay by cash, GCash, cheque or bank transfer. Final details are kept on your record.',
    ],
];

// The request button sends visitors to log in, customers to the request form, and staff to their dashboard.
if (isLoggedIn()) {
    $isCustomer = ($_SESSION['role'] ?? '') === 'Customer';
    $requestUrl = '../views/' . ($isCustomer ? 'customer_request_service.php' : dashboardForRole($_SESSION['role'] ?? ''));
    $requestLabel = $isCustomer ? 'Request a service' : 'Go to dashboard';
} else {
    $requestUrl = '../views/login.php';
    $requestLabel = 'Log in to request';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How It Works | Norily's Vehicle Repair Shop</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script>document.documentElement.classList.add('js');window.__motionFallback=setTimeout(function(){document.documentElement.classList.add('reveal-all');},3000);</script>
    <script src="../assets/js/motion.js?v=<?= filemtime(__DIR__ . '/../assets/js/motion.js') ?>" defer></script>

    <style>
        .hiw-page {
            background: var(--paper);
            padding-bottom: 6rem;
        }

        .hiw-wrap {
            max-width: 1000px;
        }

        .hiw-crumbs {
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

        .hiw-crumbs a {
            color: var(--ink-2);
            text-decoration: none;
        }

        .hiw-crumbs a:hover {
            color: var(--ink);
        }

        .hiw-crumbs li + li::before {
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
        .hiw-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr);
            gap: 2rem;
            align-items: end;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--ink);
        }

        .hiw-hero h1 {
            font-size: var(--fs-page-title);
        }




        .hiw-hero p {
            color: var(--ink-2);
            font-size: var(--fs-lead);
            line-height: 1.6;
            max-width: 380px;
            margin: 0 0 0.75rem;
        }

        /* ---------- Steps ---------- */
        .hiw-steps {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .hiw-step {
            display: grid;
            grid-template-columns: 9rem minmax(0, 1fr) minmax(0, 1.05fr);
            gap: 1.5rem;
            align-items: start;
            padding: 1.75rem 0;
            border-bottom: 1px solid var(--line);
        }

        .hiw-step-number {
            color: var(--ink);
            font-size: clamp(2.75rem, 5vw, 3.75rem);
            font-weight: 400;
            font-stretch: 112%;
            line-height: 0.9;
        }

        .hiw-step-title {
            color: var(--ink);
            font-size: clamp(1.15rem, 2vw, 1.45rem);
            font-weight: 400;
            font-stretch: 125%;
            text-transform: uppercase;
            line-height: 1.1;
            margin: 0.15rem 0 0.6rem;
        }

        .hiw-step-who {
            color: var(--ink-2);
            font-size: 0.68rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .hiw-step-text {
            color: var(--ink-2);
            font-size: 0.9rem;
            line-height: 1.6;
            margin: 0.2rem 0 0;
        }

        /* ---------- Call to action ---------- */
        .hiw-cta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-top: 2.5rem;
            padding: 2rem;
            background: var(--sheet);
            box-shadow: 10px 10px 0 var(--ink);
            border: 1px solid var(--ink);
        }

        .hiw-cta h2 {
            font-size: var(--fs-section-title);
        }

        .hiw-cta-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .hiw-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 1.4rem;
            border: 1px solid var(--ink);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            text-decoration: none;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .hiw-btn-solid {
            background: var(--ink);
            color: var(--sheet);
        }

        .hiw-btn-solid:hover {
            background: #3a372f;
            color: var(--sheet);
        }

        .hiw-btn-outline {
            background: transparent;
            color: var(--ink);
        }

        .hiw-btn-outline:hover {
            background: var(--ink);
            color: var(--sheet);
        }

        @media (max-width: 860px) {
            .hiw-hero {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }

            .hiw-step {
                grid-template-columns: 4.5rem minmax(0, 1fr);
                gap: 0.75rem 1rem;
            }

            .hiw-step-text {
                grid-column: 2;
            }

            .hiw-step-number {
                font-size: 2.5rem;
            }


            .hiw-cta {
                padding: 1.5rem;
                box-shadow: 8px 8px 0 var(--ink);
            }

            .hiw-cta-actions,
            .hiw-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="hiw-page">
    <div class="container hiw-wrap">
        <nav aria-label="Breadcrumb">
            <ol class="hiw-crumbs">
                <li><a href="../" class="link-grow">Home</a></li>
                <li aria-current="page">How it works</li>
            </ol>
        </nav>

        <section class="hiw-hero">
            <h1 class="wide-heading">
                How it<br>
                <span class="title-band">Works</span>
            </h1>

            <p>From browsing to pickup, in seven steps. You only need an account from step three.</p>
        </section>

        <ol class="hiw-steps" data-reveal-group>
            <?php foreach ($steps as $index => $step): ?>
                <li class="hiw-step">
                    <span class="hiw-step-number" aria-hidden="true"><?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?></span>

                    <div>
                        <h2 class="hiw-step-title"><?= htmlspecialchars($step['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <span class="hiw-step-who"><?= htmlspecialchars($step['who'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <p class="hiw-step-text"><?= htmlspecialchars($step['text'], ENT_QUOTES, 'UTF-8') ?></p>
                </li>
            <?php endforeach; ?>
        </ol>

        <section class="hiw-cta" data-reveal>
            <h2 class="wide-heading">Ready to start?</h2>

            <div class="hiw-cta-actions">
                <a href="services.php" class="hiw-btn hiw-btn-solid">Browse services</a>
                <a href="<?= htmlspecialchars($requestUrl, ENT_QUOTES, 'UTF-8') ?>" class="hiw-btn hiw-btn-outline"><?= htmlspecialchars($requestLabel, ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </section>
    </div>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
