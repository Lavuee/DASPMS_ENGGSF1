<?php
$basePath = '../';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How It Works | Norily's Vehicle Repair Shop</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .info-page {
            background: var(--white);
            border-top: 1px solid var(--border-light);
        }

        .info-wrapper {
            max-width: 980px;
            margin: 0 auto;
            padding: 3.5rem 1rem 5rem;
        }

        .info-title {
            color: var(--black);
            font-size: clamp(1.5rem, 2vw, 1rem);
            font-weight: 900;
            letter-spacing: 4px;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 4rem;
        }

        .info-intro,
        .info-section p,
        .info-section li {
            color: var(--black);
            font-size: 1rem;
            line-height: 1.8;
        }

        .info-intro {
            margin-bottom: 2.5rem;
        }

        .info-section {
            margin-bottom: 2.2rem;
        }

        .info-section h2 {
            color: var(--black);
            font-size: 1rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 1rem;
        }

        .info-section ul {
            margin: 0.5rem 0 0;
            padding-left: 1.4rem;
        }

        .info-actions {
            display: flex;
            justify-content: center;
            gap: 0.8rem;
            flex-wrap: wrap;
            margin-top: 3rem;
        }

        .info-action-primary,
        .info-action-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.65rem 1.2rem;
            font-size: 0.78rem;
            font-weight: 900;
            text-transform: uppercase;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .info-action-primary {
            background: var(--primary-yellow);
            color: var(--black);
            border: 1px solid var(--primary-yellow);
        }

        .info-action-primary:hover {
            background: var(--black);
            color: var(--white);
            border-color: var(--black);
        }

        .info-action-secondary {
            background: var(--black);
            color: var(--white);
            border: 1px solid var(--black);
        }

        .info-action-secondary:hover {
            background: var(--primary-yellow);
            color: var(--black);
            border-color: var(--primary-yellow);
        }

        @media (max-width: 575.98px) {
            .info-wrapper {
                padding: 2.5rem 1rem 4rem;
            }

            .info-title {
                margin-bottom: 2rem;
            }

            .info-actions {
                flex-direction: column;
            }

            .info-action-primary,
            .info-action-secondary {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="info-page">
    <section class="info-wrapper">
        <h1 class="info-title">How It Works</h1>

        <p class="info-intro">
            The Digital Auto Services and Parts Management System helps customers browse available
            repair services, view automotive parts and supplies, create an account, request bookings,
            and track service-related records through the website.
        </p>

        <div class="info-section">
            <h2>1. Browse Services and Parts</h2>
            <p>
                Guests can browse the available auto repair services, service categories, parts,
                and automotive supplies offered by Norily's Vehicle Repair Shop. This allows customers
                to check available options before creating an account or requesting assistance.
            </p>
        </div>

        <div class="info-section">
            <h2>2. Search for Services or Parts</h2>
            <p>
                Customers can use the search feature to quickly find specific services, categories,
                parts, or supplies. If no matching result is found, the system will show a clear
                message so the customer can try another keyword.
            </p>
        </div>

        <div class="info-section">
            <h2>3. Login or Create an Account</h2>
            <p>
                Guests cannot book a service directly. To request a service booking, the customer
                must log in or create an account first. This keeps booking records connected to the
                correct customer profile.
            </p>
        </div>

        <div class="info-section">
            <h2>4. Book a Service</h2>
            <p>
                After logging in, customers can submit a service booking request. The booking may
                include service details, vehicle information, preferred schedule, and other information
                needed by the repair shop.
            </p>
        </div>

        <div class="info-section">
            <h2>5. Wait for Shop Confirmation</h2>
            <p>
                Submitted booking requests are reviewed by the shop. A booking may be confirmed,
                rescheduled, or updated depending on service availability, shop capacity, and the
                nature of the repair concern.
            </p>
        </div>

        <div class="info-section">
            <h2>6. Track Service Records</h2>
            <p>
                Registered customers can use the system to monitor booking details, repair progress,
                service status, and related vehicle service records. This helps both the customer
                and the shop manage transactions more clearly.
            </p>
        </div>

        <div class="info-section">
            <h2>7. Complete the Service Process</h2>
            <p>
                Once the service is completed, the customer may proceed with payment and pickup based
                on the shop's instructions. Final service details, parts used, and repair status may
                be recorded in the system for future reference.
            </p>
        </div>

        <div class="info-actions">
            <a href="../public_pages/services.php" class="info-action-primary">
                Browse Services
            </a>

            <a href="../views/login.php" class="info-action-secondary">
                Login to Book
            </a>
        </div>
    </section>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>