<?php
session_start();
$basePath = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | Norily's Vehicle Repair Shop</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Global Website CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        /* =========================
           Internal About Page CSS
        ========================= */
        .about-page-hero {
            background: var(--white);
            text-align: center;
        }

        .about-page-hero .section-kicker {
            font-size: clamp(2.2rem, 5vw, 4rem);
            line-height: 1;
            letter-spacing: 4px;
            margin-bottom: 1.25rem;
        }

        .about-page-title {
            color: var(--black);
            font-size: clamp(1.5rem, 2vw, 1rem);
            font-weight: 900;
            line-height: 1.08;
            letter-spacing: -1.2px;
            margin-bottom: 1rem;
        }

        .about-page-subtext {
            color: var(--muted-gray);
            font-size: 1.10rem;
            line-height: 1.8;
            max-width: 760px;
            margin: 0 auto;
            text-align: center;
        }

        .about-story-section {
            background: var(--white);
        }

        .about-story-content {
            max-width: 920px;
            margin: 0 auto 4rem;
            text-align: center;
        }

        .about-story-content .section-title {
            font-size: clamp(1.5rem, 2vw, 1rem);
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 1.75rem;
        }

        .about-story-content .section-text {
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
            font-size: 1.08rem;
            line-height: 1.9;
        }

        /* =========================
           What the Shop Offers
        ========================= */
        .about-offers-section {
            max-width: 1180px;
            margin: 0 auto;
            padding-top: 1rem;
            text-align: center;
        }

        .about-offers-section h3 {
            color: var(--black);
            font-size: clamp(1.5rem, 2vw, 1rem);
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 3rem;
        }

        .about-offers-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
        }

        .about-offer-item {
            padding: 0 2rem;
            position: relative;
        }

        .about-offer-item:not(:last-child)::after {
            content: "";
            position: absolute;
            top: 8px;
            right: 0;
            width: 1px;
            height: 100%;
            background: var(--border-light);
        }

        .about-offer-item h4 {
            color: var(--black);
            font-size: 1.05rem;
            font-weight: 900;
            letter-spacing: 4px;
            text-transform: uppercase;
            line-height: 1.4;
            margin-bottom: 1.25rem;
            padding-top: 1.55rem;
        }

        .about-offer-item p {
            color: var(--muted-gray);
            font-size: 0.98rem;
            line-height: 1.75;
            margin: 0;
        }

        /* =========================
           About Highlights Section
        ========================= */
        .about-highlights-section {
            background: var(--white);
            border-top: 1px solid var(--border-light);
            padding-bottom: 6rem;
        }

        .about-highlights-layout {
            display: grid;
            grid-template-columns: 0.8fr 1.2fr;
            gap: 4rem;
            align-items: start;
        }

        .about-highlights-heading .section-kicker {
            margin-bottom: 1rem;
        }

        .about-highlights-heading .section-title {
            font-size: clamp(2rem, 4vw, 3.2rem);
            line-height: 1.05;
            max-width: 420px;
        }

        .about-highlights-list {
            display: grid;
            gap: 1.8rem;
        }

        .about-highlight-row {
            display: grid;
            grid-template-columns: 90px 1fr;
            gap: 1.5rem;
            padding-bottom: 1.8rem;
            border-bottom: 1px solid var(--border-light);
        }

        .about-highlight-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .about-highlight-number {
            color: var(--black);
            font-size: 0.9rem;
            font-weight: 900;
            letter-spacing: 4px;
        }

        .about-highlight-row h5 {
            color: var(--black);
            font-size: 1.2rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
        }

        .about-highlight-row p {
            color: var(--muted-gray);
            line-height: 1.75;
            margin: 0;
        }

        @media (max-width: 767.98px) {
            .about-offers-grid {
                grid-template-columns: repeat(2, 1fr);
                row-gap: 2.5rem;
            }

            .about-offer-item:nth-child(2)::after {
                display: none;
            }

            .about-highlights-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .about-highlights-heading {
                text-align: center;
            }

            .about-highlights-heading .section-title {
                max-width: none;
            }
        }

        @media (max-width: 575.98px) {
            .about-page-hero .section-kicker {
                font-size: 2.45rem;
                letter-spacing: 2px;
            }

            .about-page-title {
                font-size: 1.75rem;
            }

            .about-story-content .section-title {
                font-size: 2.15rem;
                letter-spacing: 2px;
            }

            .about-offers-section h3 {
                font-size: 1.75rem;
                margin-bottom: 2rem;
            }

            .about-offers-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .about-offer-item {
                padding: 0 0 2rem;
            }

            .about-offer-item:not(:last-child)::after {
                top: auto;
                right: auto;
                left: 50%;
                bottom: 0;
                width: 80px;
                height: 1px;
                transform: translateX(-50%);
                display: block;
            }

            .about-highlight-row {
                grid-template-columns: 1fr;
                gap: 0.75rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main>
    <!-- About Hero -->
    <section class="about-page-hero section-padding">
        <div class="container">
            <p class="section-kicker">About Us</p>

            <h1 class="about-page-title">
                Norily’s Vehicle Repair Shop
            </h1>

            <p class="about-page-subtext">
                A trusted local repair shop in Vila Rosario, La Union,
                serving customers since 1998.
            </p>
        </div>
    </section>

    <!-- About Story -->
    <section class="about-story-section section-padding pt-0">
        <div class="container">
            <div class="about-story-content">
                <h2 class="section-title">Our Story</h2>

                <p class="section-text">
                    Norily’s Vehicle Repair Shop was founded in 1998 by
                    Mr. Oliver P. Dagohoy and Mrs. Norily B. Dagohoy.
                    The business grew through hands-on automotive experience,
                    dependable workmanship, fair service pricing, and strong
                    customer relationships.
                </p>

                <p class="section-text mb-0">
                    Over the years, the shop has continued to provide
                    practical repair services for regular and walk-in customers
                    in Vila Rosario, La Union.
                </p>
            </div>

            <div class="about-offers-section">
                <h3>What the Shop Offers</h3>

                <div class="about-offers-grid">
                    <div class="about-offer-item">
                        <h4>Automotive Repair</h4>
                        <p>
                            Vehicle repair services for regular and walk-in customers.
                        </p>
                    </div>

                    <div class="about-offer-item">
                        <h4>Rewinding Services</h4>
                        <p>
                            Alternator, starter, motor, water pump, and related rewinding work.
                        </p>
                    </div>

                    <div class="about-offer-item">
                        <h4>Electrical & Wiring Work</h4>
                        <p>
                            Automotive wiring, troubleshooting, repair, and electrical support.
                        </p>
                    </div>

                    <div class="about-offer-item">
                        <h4>Auto Parts & Supplies</h4>
                        <p>
                            Selected parts and supplies such as capacitors, starter armatures,
                            bearings, water seals, switches, magnetic wires, and auto wires.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Key Highlights -->
    <section class="about-highlights-section section-padding">
        <div class="container">
            <div class="about-highlights-layout">
                <div class="about-highlights-heading">
                    <p class="section-kicker">Quick Highlights</p>

                    <h2 class="section-title">
                        Why Customers Choose the Shop
                    </h2>
                </div>

                <div class="about-highlights-list">
                    <div class="about-highlight-row">
                        <span class="about-highlight-number">01</span>

                        <div>
                            <h5>Established Since 1998</h5>
                            <p>
                                Serving the local community through years of hands-on
                                automotive repair experience.
                            </p>
                        </div>
                    </div>

                    <div class="about-highlight-row">
                        <span class="about-highlight-number">02</span>

                        <div>
                            <h5>Fair Service Pricing</h5>
                            <p>
                                Known for reasonable service fees and practical repair support.
                            </p>
                        </div>
                    </div>

                    <div class="about-highlight-row">
                        <span class="about-highlight-number">03</span>

                        <div>
                            <h5>Trusted Workmanship</h5>
                            <p>
                                Recognized for dependable repair work, strong technical expertise,
                                and customer trust.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include '../partials/public-footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>