<?php
/*
    Public "Send a message" form (public_pages/contact.php).
    Emails the message to the shop inbox (CONTACT_INBOX in .env). Nothing is stored in the database.
    The customer's email is optional; when given, "Reply" in the shop's email app goes straight to them.
*/

require_once '../includes/auth.php'; // session + CSRF check on POST
require_once '../includes/mailer.php';
require_once '../includes/rate_limit.php';
require_once '../includes/contact_form.php';

const CONTACT_MAX_PER_HOUR = 5;

$back = '../public_pages/contact.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . $back);
    exit;
}

function contactBack($back, array $errors, array $old) {
    $_SESSION['contact_errors'] = $errors;
    $_SESSION['contact_old'] = $old;
    header('Location: ' . $back . '#contactForm');
    exit;
}

$old = [
    'name' => trim((string)($_POST['name'] ?? '')),
    'phone' => preg_replace('/\D+/', '', (string)($_POST['phone'] ?? '')),
    'email' => trim((string)($_POST['email'] ?? '')),
    'topic' => (string)($_POST['topic'] ?? ''),
    'message' => trim((string)($_POST['message'] ?? '')),
];

// Hidden field that people never see; bots fill it in. Pretend it worked so they move on.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    $_SESSION['contact_sent'] = true;
    header('Location: ' . $back . '#contactForm');
    exit;
}

$errors = [];

if ($old['name'] === '') {
    $errors['name'] = 'Enter your name.';
} elseif (mb_strlen($old['name']) > 100) {
    $errors['name'] = 'Name is too long (100 characters max).';
}

if ($old['phone'] !== '' && !preg_match('/^09\d{9}$/', $old['phone'])) {
    $errors['phone'] = 'Use 11 digits starting with 09.';
}

if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address, or leave it blank.';
}

if ($old['phone'] === '' && $old['email'] === '') {
    $errors['phone'] = 'Give a contact number or an email so the shop can get back to you.';
}

if (!in_array($old['topic'], contactTopics(), true)) {
    $old['topic'] = contactTopics()[0];
}

if (mb_strlen($old['message']) < 5) {
    $errors['message'] = 'Tell us what you need.';
} elseif (mb_strlen($old['message']) > 2000) {
    $errors['message'] = 'Message is too long (2,000 characters max).';
}

if ($errors) {
    contactBack($back, $errors, $old);
}

$db = (new Database())->getConnection();
$limitKey = rateLimitKey('contact_ip', clientIp());

if (isRateLimited($db, $limitKey, CONTACT_MAX_PER_HOUR, 3600)) {
    contactBack($back, ['form' => 'You have sent several messages already. Please wait a while, or call the shop.'], $old);
}

recordRateLimitAttempt($db, $limitKey, CONTACT_MAX_PER_HOUR, 3600, 3600);

$subject = contactEmailSubject($old);
$replyTo = $old['email'] !== '' ? $old['email'] : null;

if (!sendHtmlMail(CONTACT_INBOX, $subject, contactEmailHtml($old), $replyTo, CONTACT_FROM_NAME)) {
    error_log('Contact form email failed: ' . mailLastError());
    contactBack($back, ['form' => 'Your message could not be sent right now. Please try again later, or call the shop.'], $old);
}

$_SESSION['contact_sent'] = true;
header('Location: ' . $back . '#contactForm');
exit;
