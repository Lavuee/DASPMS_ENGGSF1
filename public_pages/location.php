<?php
$basePath = '../';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Location | Norily's Vehicle Repair Shop</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        /* Page background */
        .location-page {
            background: var(--white);
        }

        /* Main location section */
        .location-main {
            padding: 5rem 0 3rem;
        }

        /* Top title */
        .location-title {
            color: var(--yellow-dark);
            font-size: clamp(2.2rem, 5vw, 4rem);
            font-weight: 900;
            letter-spacing: 4px;
            text-transform: uppercase;
            line-height: 1;
            margin-bottom: 3rem;
        }

        /* Two-column address and map layout */
        .location-top-grid {
            display: grid;
            grid-template-columns: 0.8fr 1.2fr;
            gap: 4rem;
            align-items: start;
            margin-bottom: 5rem;
        }

        /* Section label */
        .location-label {
            color: var(--black);
            font-size: 1rem;
            font-weight: 900;
            margin-bottom: 1rem;
        }

        /* Body text */
        .location-text {
            color: var(--muted-gray);
            font-size: 1rem;
            line-height: 1.8;
            margin-bottom: 1.5rem;
        }

        /* Address block */
        .location-address {
            color: var(--black);
            font-size: 1.1rem;
            font-weight: 800;
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        /* Small action buttons */
        .location-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Map container */
        .location-map-box {
            min-height: 320px;
            background: var(--light-gray);
            border: 1px solid var(--border-light);
            overflow: hidden;
            padding: 0;
        }

        /* Google map iframe */
        .location-map-box iframe {
            width: 100%;
            height: 100%;
            min-height: 320px;
            display: block;
            border: 0;
        }
        .location-map-box h3 {
            color: var(--black);
            font-size: 1.25rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
        }

        .location-map-box p {
            color: var(--muted-gray);
            margin: 0;
        }

        /* Visit information section */
        .visit-info-section {
            padding: 2rem 0 5rem;
        }

        /* Visit section grid */
        .visit-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: start;
        }

        /* Shop photo placeholder */
        .shop-photo-box {
            min-height: 420px;
            background: var(--light-gray);
            border: 1px dashed var(--border-light);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }

        .shop-photo-box h3 {
            color: var(--black);
            font-size: 1.25rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
        }

        .shop-photo-box p {
            color: var(--muted-gray);
            margin: 0;
        }

        /* Visit detail list */
        .visit-details {
            display: grid;
            gap: 2rem;
        }

        /* Individual visit item */
        .visit-detail-item {
            display: grid;
            grid-template-columns: 80px 1fr;
            gap: 1.5rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-light);
        }

        .visit-detail-item:last-child {
            border-bottom: 0;
        }

        .visit-icon {
            width: 48px;
            height: 48px;
            border: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--yellow-dark);
            font-weight: 900;
        }

        .visit-detail-item h3 {
            color: var(--black);
            font-size: 1.1rem;
            font-weight: 900;
            margin-bottom: 0.45rem;
        }

        .visit-detail-item p {
            color: var(--muted-gray);
            line-height: 1.75;
            margin: 0;
        }

        /* Bottom reminder */
        .location-reminder {
            padding: 3rem 0 6rem;
            border-top: 1px solid var(--border-light);
        }

        .reminder-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }

        .reminder-item {
            display: grid;
            grid-template-columns: 48px 1fr;
            gap: 1rem;
            align-items: start;
        }

        .reminder-item h4 {
            color: var(--black);
            font-size: 1rem;
            font-weight: 900;
            margin-bottom: 0.35rem;
        }

        .reminder-item p {
            color: var(--muted-gray);
            font-size: 0.95rem;
            line-height: 1.7;
            margin: 0;
        }

        @media (max-width: 991.98px) {
            .location-top-grid,
            .visit-info-grid,
            .reminder-grid {
                grid-template-columns: 1fr;
                gap: 2.5rem;
            }
        }

        @media (max-width: 575.98px) {
            .location-main {
                padding: 3.5rem 0 2rem;
            }

            .location-title {
                font-size: 2.45rem;
                letter-spacing: 2px;
                margin-bottom: 2rem;
            }

            .location-map-box,
            .shop-photo-box {
                min-height: 260px;
            }

            .visit-detail-item,
            .reminder-item {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="location-page">

    <section class="location-main">
        <div class="container">
            <h1 class="location-title">Store Location</h1>

            <div class="location-top-grid">
                <div>
                    <h2 class="location-label">Address</h2>

                    <p class="location-address">
                        Vila Rosario, La Union
                    </p>

                    <p class="location-text">
                        Norily’s Vehicle Repair Shop accepts walk-in customers for repair services,
                        rewinding work, electrical wiring concerns, and parts-related transactions.
                    </p>

                    <div class="location-actions">
                        <a href="contact.php" class="btn btn-dark-custom">Contact Shop</a>
                        <a href="services.php" class="btn btn-outline-dark-custom">View Services</a>
                    </div>
                </div>

                <div class="location-map-box">
                    <iframe
                        src="https://www.google.com/maps?q=Vila%20Rosario%20La%20Union&output=embed"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>
        </div>
    </section>

    <section class="visit-info-section">
        <div class="container">
            <div class="visit-info-grid">
                <div class="shop-photo-box">
                    <div>
                        <h3>Shop Photo</h3>
                        <p>A store photo can be added here to help customers recognize the shop before visiting.</p>
                    </div>
                </div>

                <div class="visit-details">
                    <div class="visit-detail-item">
                        <div class="visit-icon">01</div>
                        <div>
                            <h3>Walk-in Services</h3>
                            <p>
                                Repair services and parts assistance are available for visiting customers.
                            </p>
                        </div>
                    </div>

                    <div class="visit-detail-item">
                        <div class="visit-icon">02</div>
                        <div>
                            <h3>Before Visiting</h3>
                            <p>
                                Contact the shop first for parts availability or service updates.
                            </p>
                        </div>
                    </div>

                    <div class="visit-detail-item">
                        <div class="visit-icon">03</div>
                        <div>
                            <h3>What to Prepare</h3>
                            <p>
                                Prepare your vehicle concern, service request, or specific parts inquiry.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="location-reminder">
        <div class="container">
            <div class="reminder-grid">
                <div class="reminder-item">
                    <div class="visit-icon">04</div>
                    <div>
                        <h4>For repair visits</h4>
                        <p>Describe the vehicle issue clearly so the shop can assess the service needed.</p>
                    </div>
                </div>

                <div class="reminder-item">
                    <div class="visit-icon">05</div>
                    <div>
                        <h4>For parts inquiries</h4>
                        <p>Ask first if the specific part or supply is currently available.</p>
                    </div>
                </div>

                <div class="reminder-item">
                    <div class="visit-icon">06</div>
                    <div>
                        <h4>For follow-ups</h4>
                        <p>Use the contact page to ask about repair status or service-related updates.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>