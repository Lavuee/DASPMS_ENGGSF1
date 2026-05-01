<?php
$basePath = '../';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions | Norily's Vehicle Repair Shop</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .legal-page {
            background: var(--white);
            border-top: 1px solid var(--border-light);
        }

        .legal-wrapper {
            max-width: 980px;
            margin: 0 auto;
            padding: 3.5rem 1rem 5rem;
        }

        .legal-title {
            color: var(--black);
            font-size: clamp(1.5rem, 2vw, 1rem);
            font-weight: 900;
            letter-spacing: 4px;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 4rem;
        }

        .legal-intro,
        .legal-section p,
        .legal-section li {
            color: var(--black);
            font-size: 1rem;
            line-height: 1.8;
        }

        .legal-intro {
            margin-bottom: 2.5rem;
        }

        .legal-section {
            margin-bottom: 2.2rem;
        }

        .legal-section h2 {
            color: var(--black);
            font-size: 1rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 1rem;
        }

        .legal-section ul {
            margin: 0.5rem 0 0;
            padding-left: 1.4rem;
        }

        .legal-updated {
            color: var(--muted-gray);
            font-size: 0.9rem;
            margin-top: 3rem;
            text-align: center;
        }

        @media (max-width: 575.98px) {
            .legal-wrapper {
                padding: 2.5rem 1rem 4rem;
            }

            .legal-title {
                margin-bottom: 2rem;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="legal-page">
    <section class="legal-wrapper">
        <h1 class="legal-title">Terms & Conditions</h1>

        <p class="legal-intro">
            Before using the Digital Auto Services and Parts Management System of Norily's Vehicle Repair Shop,
            please read these Terms and Conditions carefully. By accessing this website, browsing services,
            viewing vehicle parts, creating an account, or submitting a booking request, you agree to follow
            the policies stated below.
        </p>

        <div class="legal-section">
            <h2>Use of the Website</h2>
            <p>
                This website is provided to help customers browse available auto repair services, view selected
                automotive parts and supplies, create an account, and request service bookings. Users must provide
                accurate information when registering, booking a service, or sending inquiries through the system.
            </p>
        </div>

        <div class="legal-section">
            <h2>Account Responsibility</h2>
            <p>
                Registered users are responsible for maintaining the confidentiality of their account credentials.
                Any activity made through a user account may be treated as the responsibility of the account owner.
                Users should immediately report suspected unauthorized access or incorrect account information.
            </p>
        </div>

        <div class="legal-section">
            <h2>Service Booking</h2>
            <p>
                Guests may browse available services and service categories, but they cannot book directly without
                logging in or creating an account. A booking request submitted through the system does not guarantee
                immediate service approval. Bookings may still be reviewed, confirmed, rescheduled, or declined
                depending on shop availability, service capacity, and the nature of the repair request.
            </p>
        </div>

        <div class="legal-section">
            <h2>Parts and Supplies</h2>
            <p>
                Parts and supplies displayed on the website are provided for customer reference and ordering support.
                Product availability may change depending on inventory status. The shop may update item details,
                availability, or related information when necessary.
            </p>
        </div>

        <div class="legal-section">
            <h2>Customer Information</h2>
            <p>
                Users agree to provide correct and updated customer, vehicle, contact, and booking information.
                Incorrect or incomplete information may affect service processing, booking confirmation, repair
                tracking, communication, or parts-related transactions.
            </p>
        </div>

        <div class="legal-section">
            <h2>Service Limitations</h2>
            <p>
                Repair estimates, service timelines, and recommendations may vary depending on the actual condition
                of the vehicle or component after inspection. The system supports service management and customer
                communication, but final repair decisions and confirmations may still require shop review.
            </p>
        </div>

        <div class="legal-section">
            <h2>Prohibited Activities</h2>
            <p>Users must not misuse the website. Prohibited activities include:</p>
            <ul>
                <li>Submitting false, misleading, or unauthorized information.</li>
                <li>Attempting to access accounts, records, or pages without permission.</li>
                <li>Disrupting, damaging, or interfering with the website or its database.</li>
                <li>Using the system for fraudulent, abusive, or harmful activities.</li>
            </ul>
        </div>

        <div class="legal-section">
            <h2>Changes to Terms</h2>
            <p>
                Norily's Vehicle Repair Shop may update these Terms and Conditions when needed to reflect system
                changes, service updates, or business process improvements. Continued use of the website means
                that the user accepts the updated terms.
            </p>
        </div>

        <p class="legal-updated">
            Last updated: April 2026
        </p>
    </section>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>