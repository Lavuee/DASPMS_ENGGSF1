<?php
$basePath = '../';

$faqs = [
    [
        'question' => 'Do you accept walk-in customers?',
        'answer' => 'Yes. Customers may visit the shop directly to request repair services or purchase available auto parts and supplies.'
    ],
    [
        'question' => 'What services does the shop offer?',
        'answer' => 'The shop offers automotive repair, rewinding services, electrical wiring work, change oil, battery replacement, radiator repair, water pump repair, generator repair, and other related services.'
    ],
    [
        'question' => 'Can customers buy auto parts from the shop?',
        'answer' => 'Yes. The shop sells selected auto parts and supplies such as capacitors, starter armatures, bearings, water seals, switches, magnetic wires, and auto wires.'
    ],
    [
        'question' => 'How can customers ask for repair updates?',
        'answer' => 'Customers may contact the shop for repair updates, especially for longer repair services that require follow-up notification.'
    ],
    [
        'question' => 'What payment methods are accepted?',
        'answer' => 'The shop accepts cash, GCash, cheque, and bank transfer depending on the customer’s preferred payment method.'
    ],
    [
        'question' => 'Are longer repairs tracked?',
        'answer' => 'Yes. Longer repair services are recorded with customer contact information so the shop can provide updates and notify customers when the repair is completed.'
    ],
    [
        'question' => 'Does the shop offer warranty?',
        'answer' => 'Yes. The shop provides a 60- to 90-day warranty, especially for rewinding services.'
    ],
    [
        'question' => 'How can customers check parts availability?',
        'answer' => 'Customers may contact the shop or visit in person to confirm if a specific part or supply is currently available.'
    ],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQs | Norily's Vehicle Repair Shop</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Global Website CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        /* Main FAQ page background */
        .faqs-page {
            background: var(--white);
        }

        /* FAQ page spacing */
        .faqs-main {
            padding: 5rem 0 6rem;
        }

        /* Main FAQ title */
        .faqs-title {
            color: var(--black);
            font-size: clamp(1.5rem, 2vw, 1rem);
            font-weight: 900;
            letter-spacing: 4px;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 4rem;
        }

        /* FAQ content width */
        .faqs-list {
            max-width: 980px;
            margin: 0 auto;
        }

        /* Individual FAQ item */
        .faq-item {
            padding-bottom: 2.25rem;
            margin-bottom: 2.25rem;
            border-bottom: 1px solid var(--border-light);
        }

        /* Removes extra line from last item */
        .faq-item:last-child {
            border-bottom: 0;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        /* FAQ question */
        .faq-item h3 {
            color: var(--black);
            font-size: 1.15rem;
            font-weight: 900;
            line-height: 1.45;
            margin-bottom: 1rem;
        }

        /* FAQ answer */
        .faq-item p {
            color: var(--muted-gray);
            font-size: 1.05rem;
            line-height: 1.85;
            margin: 0;
        }

        /* Mobile spacing */
        @media (max-width: 575.98px) {
            .faqs-main {
                padding: 3.5rem 0 4.5rem;
            }

            .faqs-title {
                font-size: 2rem;
                letter-spacing: 2px;
                margin-bottom: 3rem;
            }

            .faq-item {
                padding-bottom: 1.75rem;
                margin-bottom: 1.75rem;
            }

            .faq-item p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="faqs-page">
    <section class="faqs-main">
        <div class="container">
            <h1 class="faqs-title">Frequently Asked Questions</h1>

            <div class="faqs-list">
                <?php foreach ($faqs as $faq): ?>
                    <article class="faq-item">
                        <h3><?= htmlspecialchars($faq['question']) ?></h3>
                        <p><?= htmlspecialchars($faq['answer']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>

<?php include '../partials/public-footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>