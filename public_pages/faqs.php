<?php
$basePath = '../';
$activePage = '';

// Edit the questions here. The first one opens on page load.
$faqs = [
    [
        'question' => 'Do you accept walk-in customers?',
        'answer' => 'Yes. You can visit the shop directly to request repair services or buy available parts and supplies.',
    ],
    [
        'question' => 'What services does the shop offer?',
        'answer' => 'Automotive repair, rewinding (alternators, starters, motors and water pumps), electrical wiring, change oil, battery replacement, radiator repair and other related work.',
    ],
    [
        'question' => 'Can I buy auto parts from the shop?',
        'answer' => 'Yes. The shop sells parts and supplies such as capacitors, starter armatures, bearings, water seals, switches, magnetic wires and auto wires.',
    ],
    [
        'question' => 'Can I reserve parts online?',
        'answer' => 'Yes. Log in, add the parts to your cart and choose a preferred pickup date. The reservation is confirmed once the shop approves it.',
    ],
    [
        'question' => 'How long is a reservation held?',
        'answer' => 'A reservation is confirmed once the shop approves it. Please pick up your parts on your chosen pickup date, or contact the shop if your plans change.',
    ],
    [
        'question' => 'Can I cancel my order?',
        'answer' => 'You can cancel a service request yourself while it is still pending. For a parts order, contact the shop and they can cancel it as long as no payment has been recorded.',
    ],
    [
        'question' => 'How will I know my vehicle is done?',
        'answer' => 'Log in and check your repair status: pending, in progress, ready for pickup, completed. When it says ready for pickup, you can come in.',
    ],
    [
        'question' => 'What payment methods are accepted?',
        'answer' => 'Cash, GCash, cheque and bank transfer.',
    ],
    [
        'question' => 'Is there a down payment?',
        'answer' => 'Only for some services. Each service page shows whether a down payment is required. Down payments are non-refundable once the service has started.',
    ],
    [
        'question' => 'Does the shop offer a warranty?',
        'answer' => 'Yes. Rewinding work carries a 60 to 90 day warranty.',
    ],
    [
        'question' => 'Do you give a price before repairing?',
        'answer' => 'Yes. The head mechanic checks your vehicle first and gives you an estimate before any work starts.',
    ],
    [
        'question' => 'Is there a minimum service fee?',
        'answer' => 'Yes. The minimum service fee is ₱450. Each service also lists a starting rate, and the final cost is confirmed after the vehicle is checked.',
    ],
    [
        'question' => 'Can I pay later?',
        'answer' => 'Only if the shop allows it, and only for services. In that case, the balance must be settled within 7 days. Parts are paid in full before release.',
    ],
    [
        'question' => "What if the part I need isn't in stock?",
        'answer' => 'Out-of-stock parts cannot be reserved online. Contact the shop to ask when it will be available.',
    ],
    [
        'question' => 'Do you repair generators and water pump motors?',
        'answer' => 'Yes. The shop rewinds and repairs generators, submersible motors and water pump motors.',
    ],
    [
        'question' => 'What if I have a concern after the repair?',
        'answer' => 'Contact the shop. Concerns and complaints are handled personally by the owner. Bring your original receipt: it serves as your warranty bond for a free back-job.',
    ],
    [
        'question' => 'Can I return a part?',
        'answer' => 'No receipt, no return, no exchange. Keep your original receipt, and ask the shop to confirm the part fits your vehicle before buying.',
    ],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQs | Norily's Vehicle Repair Shop</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script>document.documentElement.classList.add('js');window.__motionFallback=setTimeout(function(){document.documentElement.classList.add('reveal-all');},3000);</script>
    <script src="../assets/js/motion.js?v=<?= filemtime(__DIR__ . '/../assets/js/motion.js') ?>" defer></script>

    <style>
        .faq-page {
            background: var(--paper);
            padding-bottom: 6rem;
        }

        .faq-wrap {
            max-width: 1000px;
        }

        .faq-crumbs {
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

        .faq-crumbs a {
            color: var(--ink-2);
            text-decoration: none;
        }

        .faq-crumbs a:hover {
            color: var(--ink);
        }

        .faq-crumbs li + li::before {
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

        /* ---------- Hero ----------
           The heading and the intro sit side by side only when there is room;
           otherwise the intro drops below, so the two never overlap. */
        .faq-hero {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1.75rem 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--ink);
        }

        .faq-hero h1 {
            max-width: 100%;
            font-size: var(--fs-page-title);
        }




        .faq-hero p {
            flex: 0 1 340px;
            color: var(--ink-2);
            font-size: var(--fs-lead);
            line-height: 1.6;
            margin: 0 0 0.5rem auto;
        }

        /* ---------- Layout ---------- */
        .faq-grid {
            display: grid;
            grid-template-columns: minmax(0, 0.62fr) minmax(0, 1.25fr);
            gap: 3.75rem;
            align-items: start;
            padding-top: 1.75rem;
        }

        .faq-aside {
            position: sticky;
            top: calc(var(--pub-header-h) + 1.5rem);
        }

        .faq-kicker {
            color: var(--ink-2);
            font-size: 0.68rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        .faq-aside-text {
            color: var(--ink-2);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1.25rem;
        }

        .faq-contact-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            border: 1px solid var(--ink);
            color: var(--ink);
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            text-decoration: none;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .faq-contact-btn:hover {
            background: var(--ink);
            color: var(--sheet);
        }

        /* ---------- Questions ---------- */
        .faq-list {
            border-top: 1px solid var(--ink);
            margin-top: 1.5rem;
        }

        .faq-item {
            border-bottom: 1px solid var(--line);
        }

        .faq-item summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            padding: 1.15rem 0;
            color: var(--ink);
            font-size: 1.05rem;
            cursor: pointer;
            list-style: none;
        }

        .faq-item summary::-webkit-details-marker {
            display: none;
        }

        .faq-item summary:focus-visible {
            outline: 2px solid var(--ink);
            outline-offset: 4px;
        }

        /* Plus that turns into a close mark when the answer is open */
        .faq-icon {
            position: relative;
            width: 12px;
            height: 12px;
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }

        .faq-icon::before,
        .faq-icon::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            height: 1.5px;
            margin-top: -0.75px;
            background: var(--ink);
        }

        .faq-icon::after {
            transform: rotate(90deg);
        }

        .faq-item[open] .faq-icon {
            transform: rotate(45deg) scale(0.85);
        }

        .faq-answer {
            color: var(--ink-2);
            font-size: 0.9rem;
            line-height: 1.6;
            max-width: 500px;
            margin: -0.25rem 0 1.25rem;
        }


        @media (max-width: 860px) {
            .faq-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .faq-aside {
                position: static;
                order: 2;
            }

            .faq-hero h1 {
                font-size: var(--fs-page-title);
            }

            .faq-hero p {
                flex-basis: 100%;
                margin-left: 0;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .faq-icon {
                transition: none;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="faq-page">
    <div class="container faq-wrap">
        <nav aria-label="Breadcrumb">
            <ol class="faq-crumbs">
                <li><a href="../" class="link-grow">Home</a></li>
                <li aria-current="page">FAQs</li>
            </ol>
        </nav>

        <section class="faq-hero">
            <h1 class="wide-heading">
                Good<br>
                <span class="title-band">Questions</span>
            </h1>

            <p>Answers about walk-ins, parts, payments, warranty and repair updates.</p>
        </section>

        <div class="faq-grid">
            <aside class="faq-aside" data-reveal>
                <p class="faq-kicker">Still unsure?</p>
                <p class="faq-aside-text">Call the shop during opening hours or send a message.</p>
                <a href="contact.php" class="faq-contact-btn">Contact the shop</a>
            </aside>

            <div class="faq-list" data-reveal-group>
                <?php foreach ($faqs as $index => $faq): ?>
                    <details class="faq-item"<?= $index === 0 ? ' open' : '' ?>>
                        <summary>
                            <span><?= htmlspecialchars($faq['question'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="faq-icon" aria-hidden="true"></span>
                        </summary>
                        <p class="faq-answer"><?= htmlspecialchars($faq['answer'], ENT_QUOTES, 'UTF-8') ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// One answer open at a time: opening a question closes the others.
(function () {
    const items = document.querySelectorAll('.faq-item');

    items.forEach(function (item) {
        item.addEventListener('toggle', function () {
            if (!item.open) {
                return;
            }

            items.forEach(function (other) {
                if (other !== item) {
                    other.open = false;
                }
            });
        });
    });
})();
</script>

</body>
</html>
