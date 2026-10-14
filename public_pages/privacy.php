<?php
$basePath = '../';
$activePage = '';
$lastUpdated = 'October 2026';

// Each section: a title and either 'text' (a paragraph) or 'list' (bullet points).
$sections = [
    [
        'title' => 'What we collect',
        'list' => [
            'Your name and account details.',
            'Email address, phone number and other contact details.',
            'Vehicle information needed for requests and repair records.',
            'Service requests, schedules and status records.',
            'Parts reservation and payment details, when applicable.',
        ],
    ],
    [
        'title' => 'How we use it',
        'list' => [
            'To create and manage your account.',
            'To process service requests and parts reservations.',
            'To keep your vehicle repair and maintenance records.',
            'To show you request updates and repair status, and to send account emails such as verification and password resets.',
            'To improve the website, customer service and shop operations.',
        ],
    ],
    [
        'title' => 'Service records',
        'text' => 'Request history, vehicle service details and repair records are kept so you and authorized shop staff can track requests, follow repair progress and plan future maintenance.',
    ],
    [
        'title' => 'Protection',
        'text' => 'The shop uses reasonable security practices to protect your information from unauthorized access, misuse, change or disclosure. Only authorized staff can see customer and service records.',
    ],
    [
        'title' => 'Sharing',
        'text' => 'Your information is never sold. It is shared only when needed to run the system, process a service, follow the law, or resolve a concern about your request, repair or order.',
    ],
    [
        'title' => 'Your part',
        'text' => 'Give accurate information and keep your password private. Report suspicious account activity or wrong records as soon as possible.',
    ],
    [
        'title' => 'Corrections',
        'text' => 'You can ask the shop to correct personal, contact, vehicle or service information that is wrong.',
    ],
    [
        'title' => 'Changes',
        'text' => 'This policy may change when the system, services or data handling change. Using the website after an update means you accept the latest version.',
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
    <title>Privacy Policy | Norily's Vehicle Repair Shop</title>
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
                <li aria-current="page">Privacy policy</li>
            </ol>
        </nav>

        <header class="terms-hero">
            <h1 class="wide-heading">Privacy<br>Policy</h1>
            <p class="terms-updated">Last updated &middot; <?= e($lastUpdated) ?></p>
        </header>

        <div class="terms-body">
            <p class="terms-intro">
                This explains how the shop's website collects, uses, stores and protects your information
                when you browse, register, request a service or manage your vehicle records.
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
