<?php
/*
    Shared pieces of the public Contact form: the topic list (used by the page and the controller)
    and the plain email the shop receives.
*/

require_once __DIR__ . '/mailer.php';

const CONTACT_FROM_NAME = 'DASPMS Contact Form';

function contactTopics() {
    return ['Repair', 'Parts', 'Repair status', 'Other'];
}

// 09171234567 -> 0917 123 4567
function contactPhoneDisplay($digits) {
    return strlen($digits) === 11 ? substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7) : $digits;
}

// "Parts inquiry from Maria Santos"; the "Other" topic reads "General inquiry from ..."
function contactEmailSubject(array $data) {
    $topic = $data['topic'] === 'Other' ? 'General' : $data['topic'];
    return $topic . ' inquiry from ' . $data['name'];
}

// $data: name, phone (digits), email, topic, message - raw values, escaped here
function contactEmailHtml(array $data) {
    $hasPhone = $data['phone'] !== '';
    $hasEmail = $data['email'] !== '';
    $phone = mailEscape(contactPhoneDisplay($data['phone']));
    $notProvided = '<span style="color:#5f6368;">Not provided</span>';

    $details = emailDetails([
        'Name' => mailEscape($data['name']),
        'Contact number' => $hasPhone ? $phone : $notProvided,
        'Email' => $hasEmail ? mailEscape($data['email']) : $notProvided,
        'Topic' => mailEscape($data['topic']),
        'Received' => date('M j, Y, g:i A'),
    ]);

    if ($hasEmail && $hasPhone) {
        $howToReply = 'To answer, reply to this email or call ' . $phone . '.';
    } elseif ($hasEmail) {
        $howToReply = 'To answer, reply to this email.';
    } else {
        $howToReply = 'The customer did not leave an email. To answer, call or text ' . $phone . '.';
    }

    $body = emailParagraph('New inquiry from the DASPMS contact page.')
        . $details
        . '<p style="margin:0 0 6px; font-weight:bold;">Message:</p>'
        . '<p style="margin:0 0 14px; white-space:pre-wrap;">' . mailEscape($data['message']) . '</p>'
        . '<p style="margin:0;">' . $howToReply . '</p>';

    return renderEmailLayout($body);
}
