<?php
$basePath = '../';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy | Norily's Vehicle Repair Shop</title>

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
        <h1 class="legal-title">Privacy Policy</h1>

        <p class="legal-intro">
            Your privacy is important to Norily's Vehicle Repair Shop. This Privacy Policy explains how the
            Digital Auto Services and Parts Management System collects, uses, stores, and protects user
            information when customers browse services, register an account, book repair services, or manage
            related vehicle service records.
        </p>

        <div class="legal-section">
            <h2>Information We Collect</h2>
            <p>We may collect the following information when you use the website:</p>
            <ul>
                <li>Name and account details.</li>
                <li>Email address, phone number, and other contact information.</li>
                <li>Vehicle information needed for service booking and repair records.</li>
                <li>Service booking details, repair requests, schedules, and service status records.</li>
                <li>Parts or supply-related transaction details, when applicable.</li>
                <li>Messages, inquiries, or support requests submitted through the system.</li>
            </ul>
        </div>

        <div class="legal-section">
            <h2>Use of Personal Information</h2>
            <p>Collected information may be used for the following purposes:</p>
            <ul>
                <li>Creating and managing customer accounts.</li>
                <li>Processing service booking requests.</li>
                <li>Managing vehicle repair and maintenance records.</li>
                <li>Communicating booking updates, service status, and shop notifications.</li>
                <li>Supporting parts and supplies management.</li>
                <li>Improving system functionality, customer service, and shop operations.</li>
            </ul>
        </div>

        <div class="legal-section">
            <h2>Booking and Service Records</h2>
            <p>
                The system may store booking history, vehicle service details, and repair-related records to help
                customers and authorized shop personnel track service requests, monitor repair progress, and manage
                future maintenance needs.
            </p>
        </div>

        <div class="legal-section">
            <h2>Data Protection</h2>
            <p>
                We apply reasonable security practices to help protect user information against unauthorized access,
                misuse, alteration, or disclosure. Access to customer and service records should be limited to
                authorized users and personnel only.
            </p>
        </div>

        <div class="legal-section">
            <h2>Information Sharing</h2>
            <p>
                Personal information is not sold to third parties. User information may only be shared when necessary
                for system operation, service processing, legal compliance, or when required to resolve customer
                concerns related to bookings, repairs, or parts transactions.
            </p>
        </div>

        <div class="legal-section">
            <h2>User Responsibility</h2>
            <p>
                Users are responsible for providing accurate information and protecting their login credentials.
                Customers should avoid sharing account passwords and should report suspicious account activity or
                incorrect records as soon as possible.
            </p>
        </div>

        <div class="legal-section">
            <h2>Data Updates and Correction</h2>
            <p>
                Users may request correction of inaccurate personal, contact, vehicle, booking, or service-related
                information. Keeping records accurate helps the shop provide better service and avoid booking or
                communication issues.
            </p>
        </div>

        <div class="legal-section">
            <h2>Changes to This Policy</h2>
            <p>
                This Privacy Policy may be updated when the system, services, or data handling practices change.
                Continued use of the website means that the user acknowledges the latest version of this policy.
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