<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/contact_form.php';

$basePath = '../';
$activePage = 'contact';

// Same number as the footer (partials/public-footer.php); change both together.
$shopPhone = '0912-345-6789';
$topics = contactTopics();

// Result of the last send (set by controllers/ContactController.php), shown once
$contactSent = !empty($_SESSION['contact_sent']);
$contactErrors = $_SESSION['contact_errors'] ?? [];
$contactOld = $_SESSION['contact_old'] ?? [];
unset($_SESSION['contact_sent'], $_SESSION['contact_errors'], $_SESSION['contact_old']);

$old = function ($key) use ($contactOld) {
    return htmlspecialchars((string)($contactOld[$key] ?? ''), ENT_QUOTES, 'UTF-8');
};
$fieldError = function ($key) use ($contactErrors) {
    return isset($contactErrors[$key])
        ? '<p class="contact-error" id="contactError_' . $key . '" role="alert">' . htmlspecialchars($contactErrors[$key], ENT_QUOTES, 'UTF-8') . '</p>'
        : '';
};
$invalid = function ($key) use ($contactErrors) {
    return isset($contactErrors[$key]) ? ' is-invalid" aria-invalid="true" aria-describedby="contactError_' . $key : '';
};
$selectedTopic = in_array($contactOld['topic'] ?? '', $topics, true) ? $contactOld['topic'] : $topics[0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Norily's Vehicle Repair Shop</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script>document.documentElement.classList.add('js');window.__motionFallback=setTimeout(function(){document.documentElement.classList.add('reveal-all');},3000);</script>
    <script src="../assets/js/motion.js?v=<?= filemtime(__DIR__ . '/../assets/js/motion.js') ?>" defer></script>

    <style>
        .contact-page {
            background: var(--paper);
            padding-bottom: 6rem;
        }

        .contact-wrap {
            max-width: 1000px;
        }

        .contact-crumbs {
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

        .contact-crumbs a {
            color: var(--ink-2);
            text-decoration: none;
        }

        .contact-crumbs a:hover {
            color: var(--ink);
        }

        .contact-crumbs li + li::before {
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

        .mono {
            font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
        }

        /* ---------- Hero ---------- */
        .contact-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr);
            gap: 2rem;
            align-items: end;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--ink);
        }

        .contact-hero h1 {
            font-size: var(--fs-page-title);
        }


        .contact-hero p {
            color: var(--ink-2);
            font-size: var(--fs-lead);
            line-height: 1.6;
            max-width: 380px;
            margin: 0 0 0.75rem;
        }

        /* ---------- Details + form ---------- */
        .contact-grid {
            display: grid;
            grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.1fr);
            gap: 3.75rem;
            align-items: start;
            padding-top: 1.75rem;
        }

        .contact-details {
            list-style: none;
            padding: 0;
            margin: 0;
            border-top: 1px solid var(--ink);
        }

        .contact-detail {
            padding: 1.1rem 0 1.15rem;
            border-bottom: 1px solid var(--line);
        }

        .contact-label {
            display: block;
            color: var(--ink-2);
            font-size: var(--fs-label);
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .contact-value {
            color: var(--ink);
            font-size: 1.05rem;
            font-stretch: 112%;
            text-transform: uppercase;
            margin: 0;
        }

        .contact-phone-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .contact-phone {
            color: var(--ink);
            font-size: 1.6rem;
            letter-spacing: 0.02em;
            text-decoration: none;
        }

        .contact-phone:hover {
            color: var(--ink);
        }

        .contact-small-btn {
            min-height: 32px;
            padding: 0 0.7rem;
            background: transparent;
            border: 1px solid var(--ink);
            color: var(--ink);
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            cursor: pointer;
        }

        .contact-small-btn:hover {
            background: var(--ink);
            color: var(--sheet);
        }

        .contact-note {
            color: var(--ink);
            font-size: 0.9rem;
            margin: 0;
        }

        .contact-visit {
            color: var(--ink);
            text-decoration: none;
        }

        .contact-visit:hover {
            color: var(--ink);
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        /* ---------- Message form ---------- */
        .contact-form-card {
            background: var(--sheet);
            border: 1px solid var(--ink);
            padding: 1.75rem;
        }

        .contact-form-card h2 {
            font-size: clamp(1.5rem, 2.6vw, 1.9rem);
            margin-bottom: 1.4rem;
        }

        .contact-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.9rem;
        }

        .contact-field {
            margin-bottom: 1.1rem;
        }

        .contact-field > label,
        .contact-field legend {
            display: block;
            color: var(--ink);
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            float: none;
            width: auto;
            padding: 0;
        }

        .contact-field fieldset {
            border: 0;
            margin: 0;
            padding: 0;
            min-width: 0;
        }

        .contact-input {
            width: 100%;
            min-height: 46px;
            padding: 0.6rem 0.75rem;
            background: var(--paper);
            border: 1px solid var(--ink);
            border-radius: 0;
            color: var(--ink);
            font-size: 0.95rem;
            outline: none;
        }

        .contact-input::placeholder {
            color: var(--ink-3);
        }

        .contact-input:focus {
            box-shadow: 0 0 0 3px rgba(94, 107, 52, 0.2);
        }

        textarea.contact-input {
            display: block;
            min-height: 115px;
            resize: vertical;
        }

        .contact-hint {
            color: var(--ink-2);
            font-size: 0.72rem;
            margin-top: 0.4rem;
        }

        .contact-topics {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .contact-topic input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .contact-topic span {
            display: inline-flex;
            align-items: center;
            min-height: 36px;
            padding: 0 0.95rem;
            border: 1px solid var(--ink);
            color: var(--ink);
            font-size: 0.82rem;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
        }

        .contact-topic input:checked + span {
            background: var(--ink);
            color: var(--sheet);
            font-weight: 600;
        }

        .contact-topic input:focus-visible + span {
            outline: 2px solid var(--ink);
            outline-offset: 3px;
        }

        .contact-submit {
            width: 100%;
            min-height: 48px;
            background: var(--ink);
            border: 1px solid var(--ink);
            color: var(--sheet);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .contact-submit:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .contact-input.is-invalid {
            border-color: #9b2c2c;
            box-shadow: 0 0 0 3px rgba(155, 44, 44, 0.12);
        }

        .contact-error {
            margin: 0.4rem 0 0;
            color: #9b2c2c;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .contact-optional {
            color: var(--ink-3);
            font-weight: 500;
            letter-spacing: 0.04em;
            text-transform: none;
        }

        /* Hidden spam trap: people never see or fill it */
        .contact-trap {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }

        .contact-status {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            margin: 0 0 1.25rem;
            padding: 0.9rem 1rem;
            border: 1px solid var(--ink);
            border-left-width: 3px;
            background: var(--paper);
            color: var(--ink);
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .contact-status.is-bad {
            border-color: #9b2c2c;
            color: #9b2c2c;
        }

        .contact-status strong {
            display: block;
            font-size: 0.75rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .contact-form-notice {
            color: var(--ink-2);
            font-size: 0.8rem;
            line-height: 1.55;
            margin: 0.75rem 0 0;
        }

        @media (max-width: 860px) {
            .contact-hero,
            .contact-grid {
                grid-template-columns: 1fr;
            }

            .contact-hero {
                gap: 1.25rem;
            }

            .contact-grid {
                gap: 2.5rem;
            }
        }

        @media (max-width: 575.98px) {
            .contact-form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .contact-form-card {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>

<?php include '../partials/public-navbar.php'; ?>

<main class="contact-page">
    <div class="container contact-wrap">
        <nav aria-label="Breadcrumb">
            <ol class="contact-crumbs">
                <li><a href="../" class="link-grow">Home</a></li>
                <li aria-current="page">Contact</li>
            </ol>
        </nav>

        <section class="contact-hero">
            <h1 class="wide-heading">Talk<br>to <span class="title-band">us</span></h1>
            <p>Repair questions, parts availability, service updates and support.</p>
        </section>

        <div class="contact-grid">
            <ul class="contact-details" data-reveal-group>
                <li class="contact-detail">
                    <span class="contact-label">Phone</span>
                    <div class="contact-phone-row">
                        <a href="tel:<?= preg_replace('/\D/', '', $shopPhone) ?>" class="contact-phone mono" id="shopPhone"><?= $shopPhone ?></a>
                        <button type="button" class="contact-small-btn" id="copyPhone">Copy</button>
                    </div>
                </li>

                <li class="contact-detail">
                    <span class="contact-label">Hours</span>
                    <p class="contact-value">Mon &ndash; Sat &middot; 8:00 AM &ndash; 5:00 PM</p>
                </li>

                <li class="contact-detail">
                    <span class="contact-label">Payment</span>
                    <p class="contact-value">Cash &middot; GCash &middot; Cheque &middot; Bank transfer</p>
                </li>

                <li class="contact-detail">
                    <span class="contact-label">Concerns</span>
                    <p class="contact-note">Complaints are handled personally by the owner.</p>
                </li>

                <li class="contact-detail">
                    <span class="contact-label">Visit</span>
                    <a href="location.php" class="contact-value contact-visit link-grow">Vila Rosario, La Union <span class="arrow" aria-hidden="true">&#8599;</span></a>
                </li>
            </ul>

            <section class="contact-form-card" data-reveal aria-labelledby="messageHeading">
                <h2 class="wide-heading" id="messageHeading">Send a message</h2>

                <form id="contactForm" action="../controllers/ContactController.php" method="POST" novalidate>
                    <?= csrfField() ?>

                    <?php if ($contactSent): ?>
                        <div class="contact-status" role="status">
                            <div>
                                <strong>Message sent</strong>
                                Thank you. The shop will get back to you by call, text or email.
                            </div>
                        </div>
                    <?php elseif (isset($contactErrors['form'])): ?>
                        <div class="contact-status is-bad" role="alert">
                            <div>
                                <strong>Not sent</strong>
                                <?= htmlspecialchars($contactErrors['form'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="contact-trap" aria-hidden="true">
                        <label for="contactWebsite">Leave this empty</label>
                        <input type="text" id="contactWebsite" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="contact-field">
                        <label for="contactName">Full name</label>
                        <input type="text" id="contactName" name="name" class="contact-input<?= $invalid('name') ?>" autocomplete="name" maxlength="100" value="<?= $old('name') ?>" required>
                        <?= $fieldError('name') ?>
                    </div>

                    <div class="contact-form-row">
                        <div class="contact-field">
                            <label for="contactPhone">Contact number</label>
                            <input type="tel" id="contactPhone" name="phone" class="contact-input<?= $invalid('phone') ?>" placeholder="09XXXXXXXXX" maxlength="11" inputmode="numeric" autocomplete="tel" value="<?= $old('phone') ?>"<?= isset($contactErrors['phone']) ? '' : ' aria-describedby="contactPhoneHint"' ?>>
                            <?= $fieldError('phone') ?: '<div class="contact-hint" id="contactPhoneHint">11 digits, starting with 09</div>' ?>
                        </div>

                        <div class="contact-field">
                            <label for="contactEmail">Email <span class="contact-optional">(optional)</span></label>
                            <input type="email" id="contactEmail" name="email" class="contact-input<?= $invalid('email') ?>" placeholder="name@example.com" maxlength="150" autocomplete="email" value="<?= $old('email') ?>"<?= isset($contactErrors['email']) ? '' : ' aria-describedby="contactEmailHint"' ?>>
                            <?= $fieldError('email') ?: '<div class="contact-hint" id="contactEmailHint">So the shop can reply by email</div>' ?>
                        </div>
                    </div>

                    <div class="contact-field">
                        <fieldset>
                            <legend>Topic</legend>
                            <div class="contact-topics">
                                <?php foreach ($topics as $index => $topic): ?>
                                    <label class="contact-topic">
                                        <input type="radio" name="topic" value="<?= $topic ?>"<?= $topic === $selectedTopic ? ' checked' : '' ?>>
                                        <span><?= $topic ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                    </div>

                    <div class="contact-field">
                        <label for="contactMessage">Message</label>
                        <textarea id="contactMessage" name="message" class="contact-input<?= $invalid('message') ?>" placeholder="Vehicle, plate number and what you need" maxlength="2000" required><?= $old('message') ?></textarea>
                        <?= $fieldError('message') ?>
                    </div>

                    <button type="submit" class="contact-submit" aria-describedby="contactFormNotice">Send message</button>

                    <p class="contact-form-notice" id="contactFormNotice">
                        Leave a contact number or an email so the shop can reply. For urgent concerns, call
                        <?= $shopPhone ?>, Mon&ndash;Sat, 8:00 AM&ndash;5:00 PM.
                    </p>
                </form>
            </section>
        </div>
    </div>
</main>

<?php include '../partials/public-footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Sending takes a few seconds (the email goes out through Gmail); stop double clicks meanwhile
document.getElementById('contactForm').addEventListener('submit', function () {
    const button = this.querySelector('.contact-submit');
    button.disabled = true;
    button.textContent = 'Sending...';
});
// Coming back with the browser's Back button: make the button usable again
window.addEventListener('pageshow', function () {
    const button = document.querySelector('#contactForm .contact-submit');
    button.disabled = false;
    button.textContent = 'Send message';
});

// Copy the shop number; falls back to selecting the text if the clipboard is unavailable.
(function () {
    const button = document.getElementById('copyPhone');
    const phone = document.getElementById('shopPhone');

    button.addEventListener('click', function () {
        const done = function () {
            button.textContent = 'Copied';
            setTimeout(function () { button.textContent = 'Copy'; }, 1500);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(phone.textContent.trim()).then(done);
        } else {
            const range = document.createRange();
            range.selectNodeContents(phone);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            document.execCommand('copy');
            selection.removeAllRanges();
            done();
        }
    });

    // The form is not connected to a backend yet, so it never submits.
    document.getElementById('contactForm').addEventListener('submit', function (event) {
        event.preventDefault();
    });
})();
</script>

</body>
</html>
