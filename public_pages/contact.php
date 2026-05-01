<?php
$basePath = '../';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact | Norily's Vehicle Repair Shop</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Global Website CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        /* Main page background */
        .contact-page {
            background: var(--white);
        }

        /* Top hero spacing */
        .contact-hero {
            padding: 5rem 0 3rem;
            background: var(--white);
        }

        /* Small text above main title */
        .contact-breadcrumb {
            color: var(--yellow-dark);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        /* Hero title and short description layout */
        .contact-title-row {
            display: grid;
            grid-template-columns: 1fr 0.7fr;
            gap: 3rem;
            align-items: end;
            padding-bottom: 2rem;
        }

        /* Main Contact Us title */
        .contact-title {
            color: var(--yellow-dark);
            font-size: clamp(2.2rem, 5vw, 4rem);
            font-weight: 900;
            letter-spacing: 4px;
            text-transform: uppercase;
            line-height: 1;
            margin: 0;
        }

        /* Hero description text */
        .contact-lead {
            color: var(--muted-gray);
            font-size: 1rem;
            line-height: 1.8;
            margin: 0;
        }

        /* Contact details section spacing */
        .contact-details {
            padding: 2.5rem 0 4rem;
        }

        /* Keeps contact details centered and not too wide */
        .contact-details .container {
            max-width: 1080px;
        }

        /* Each contact detail row */
        .contact-detail-row {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 2rem;
            padding: 1.35rem 0;
            border-bottom: 1px solid var(--border-light);
            max-width: 980px;
            margin: 0 auto;
        }

        /* Adds top line to first contact detail row */
        .contact-detail-row:first-child {
            border-top: 1px solid var(--border-light);
        }

        /* Contact detail labels */
        .contact-detail-row h3 {
            color: var(--black);
            font-size: 1.05rem;
            font-weight: 800;
            margin: 0;
        }

        /* Contact detail values */
        .contact-detail-row p {
            color: var(--muted-gray);
            font-size: 1rem;
            line-height: 1.7;
            margin: 0;
        }

        /* Form section bottom spacing before footer */
        .contact-form-section {
            padding-bottom: 6rem;
        }

        /* Form section two-column layout */
        .contact-form-layout {
            display: grid;
            grid-template-columns: 0.45fr 1fr;
            gap: 3rem;
            align-items: start;
        }

        /* Small label beside form */
        .contact-form-label {
            color: var(--yellow-dark);
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        /* Form heading */
        .contact-form-copy h2 {
            color: var(--black);
            font-size: clamp(1.8rem, 3.2vw, 2.6rem);
            font-weight: 900;
            line-height: 1.08;
            letter-spacing: -1.2px;
            margin-bottom: 1rem;
        }

        /* Form description */
        .contact-form-copy p {
            color: var(--muted-gray);
            font-size: 1rem;
            line-height: 1.8;
            max-width: 420px;
            margin: 0;
        }

        /* Form wrapper */
        .contact-form {
            width: 100%;
            margin-top: 1.75rem;
        }

        /* Name, phone, and button row */
        .contact-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        /* Input and textarea base style */
        .contact-form input,
        .contact-form textarea {
            width: 100%;
            border: 0;
            border-bottom: 1px solid var(--border-light);
            border-radius: 0;
            padding: 0.85rem 0;
            font-size: 0.95rem;
            color: var(--black);
            background: transparent;
            outline: none;
        }

        /* Message textarea */
        .contact-form textarea {
            min-height: 110px;
            resize: vertical;
            margin-top: 1.25rem;
        }

        /* Input focus effect */
        .contact-form input:focus,
        .contact-form textarea:focus {
            border-bottom-color: var(--primary-yellow);
        }

        /* Send button */
        .contact-submit-btn {
            background: var(--black);
            color: var(--white);
            border: 2px solid var(--black);
            padding: 0.75rem 2rem;
            font-size: 0.9rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Send button hover */
        .contact-submit-btn:hover {
            background: var(--primary-yellow);
            color: var(--black);
            border-color: var(--primary-yellow);
        }

        /* Static prototype note */
        .contact-form-note {
            color: var(--muted-gray);
            font-size: 0.9rem;
            line-height: 1.7;
            margin: 1rem 0 0;
        }

        /* Tablet responsive layout */
        @media (max-width: 991.98px) {
            .contact-title-row,
            .contact-form-layout {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .contact-form-grid {
                grid-template-columns: 1fr;
            }

            .contact-submit-btn {
                width: 100%;
            }
        }

        /* Mobile responsive layout */
        @media (max-width: 575.98px) {
            .contact-hero {
                padding: 3.5rem 0 2rem;
            }

            .contact-title {
                font-size: 2.45rem;
                letter-spacing: 2px;
            }

            .contact-detail-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .contact-details {
                padding-bottom: 3.5rem;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="contact-page">
    <section class="contact-hero">
        <div class="container">

            <div class="contact-title-row">
                <h1 class="contact-title">Contact Us</h1>

                <p class="contact-lead">
                    Reach out for repair inquiries, parts availability, service updates,
                    and customer support.
                </p>
            </div>
        </div>
    </section>

    <section class="contact-details">
        <div class="container">

            <div class="contact-detail-row">
                <h3>Phone</h3>
                <p>0912-345-6789</p>
            </div>

            <div class="contact-detail-row">
                <h3>Payment</h3>
                <p>Cash, GCash, Cheque, Bank Transfer</p>
            </div>

            <div class="contact-detail-row">
                <h3>Support</h3>
                <p>Repair services, rewinding work, electrical wiring, parts availability, and customer follow-ups.</p>
            </div>
        </div>
    </section>

    <section class="contact-form-section">
        <div class="container">
            <div class="contact-form-layout">
                <div>
                    <p class="contact-form-label">Contact Us</p>
                </div>

                <div class="contact-form-copy">
                    <h2>Let’s discuss your service concern</h2>
                    <p>
                        Fill out the form below and our team will review your inquiry as soon as possible.
                    </p>

                    <form class="contact-form" action="#" method="post">
                        <div class="contact-form-grid">
                            <input type="text" name="name" placeholder="Name">
                            <input type="text" name="phone" placeholder="Phone number">
                            <button type="button" class="contact-submit-btn">Send</button>
                        </div>

                        <textarea name="message" placeholder="Message"></textarea>

                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>