<?php
/*
    Outgoing email. The transport is chosen with MAIL_DRIVER in .env:
      log  - writes the message to logs/mail.log (local testing; nothing is sent)
      smtp - sends through an authenticated SMTP server using PHPMailer
      mail - PHP mail() / XAMPP sendmail (previous behaviour)
*/

require_once __DIR__ . '/../config/app.php';

$mailLastError = '';

/*
    Sends an HTML email. Returns true on success; on failure the reason is
    available through mailLastError() (never shown to end users).
    $replyTo (optional): address that "Reply" in the recipient's email app should go to.
    $fromName (optional): sender name shown in the inbox instead of MAIL_FROM_NAME.
*/
function sendHtmlMail($to, $subject, $html, $replyTo = null, $fromName = null) {
    global $mailLastError;
    $mailLastError = '';

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $mailLastError = 'Invalid recipient address';
        return false;
    }

    if ($replyTo !== null && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $replyTo = null;
    }

    $fromName = ($fromName !== null && trim($fromName) !== '') ? trim($fromName) : MAIL_FROM_NAME;
    $driver = strtolower((string)env('MAIL_DRIVER', 'mail'));

    if ($driver === 'log') {
        return logMail($to, $subject, $html, $replyTo, $fromName);
    }

    if ($driver === 'smtp') {
        return sendSmtpMail($to, $subject, $html, $replyTo, $fromName);
    }

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . $fromName . " <" . MAIL_FROM_ADDRESS . ">\r\n";
    $headers .= "Reply-To: " . ($replyTo ?? MAIL_FROM_ADDRESS) . "\r\n";

    if (@mail($to, $subject, $html, $headers)) {
        return true;
    }

    $error = error_get_last();
    $mailLastError = $error['message'] ?? 'Unknown mail error';
    return false;
}

function sendSmtpMail($to, $subject, $html, $replyTo = null, $fromName = MAIL_FROM_NAME) {
    global $mailLastError;

    require_once __DIR__ . '/../vendor/autoload.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = (string)env('SMTP_HOST', '');
        $mail->Port = intval(env('SMTP_PORT', 587));
        $mail->SMTPAuth = true;
        $mail->Username = (string)env('SMTP_USERNAME', '');
        // Gmail shows App Passwords in groups of four ("abcd efgh ..."); the spaces are not part of it
        $mail->Password = preg_replace('/\s+/', '', (string)env('SMTP_PASSWORD', ''));
        $mail->SMTPSecure = env('SMTP_ENCRYPTION', 'tls') === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 15;

        $mail->setFrom(MAIL_FROM_ADDRESS, $fromName);
        $mail->addAddress($to);
        if ($replyTo) {
            $mail->addReplyTo($replyTo);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));

        return $mail->send();
    } catch (Exception $e) {
        $mailLastError = $mail->ErrorInfo ?: $e->getMessage();
        return false;
    }
}

function logMail($to, $subject, $html, $replyTo = null, $fromName = MAIL_FROM_NAME) {
    $logDir = __DIR__ . '/../logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $replyLine = $replyTo ? "Reply-To: $replyTo\n" : '';
    $entry = "===== " . date('Y-m-d H:i:s') . " =====\nFrom: $fromName\nTo: $to\n{$replyLine}Subject: $subject\n\n$html\n\n";
    return file_put_contents($logDir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
}

function mailLastError() {
    global $mailLastError;
    return $mailLastError !== '' ? $mailLastError : 'Unknown mail error';
}

// Escapes a value for an email template (values may already be HTML-encoded in the DB).
function mailEscape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8', false);
}

/*
    Plain email frame used by every email DASPMS sends: ordinary text on white, like a
    message typed in Gmail, with one small gray line at the bottom. Inline styles only,
    because many email apps (Gmail, Outlook) ignore <style> blocks.
    $bodyHtml must already be escaped. $footerText replaces the default bottom line.
*/
function renderEmailLayout($bodyHtml, $footerText = '') {
    $footer = $footerText !== ''
        ? mailEscape($footerText)
        : "Sent automatically by DASPMS &middot; Norily's Vehicle Repair Shop, Vila Rosario, La Union";

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background-color:#ffffff;">
    <div style="max-width:600px; padding:16px; color:#202124; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:1.6;">
        {$bodyHtml}
        <div style="margin-top:24px; padding-top:12px; border-top:1px solid #dadce0; color:#5f6368; font-size:12px;">
            {$footer}
        </div>
    </div>
</body>
</html>
HTML;
}

// Paragraph of plain text (already escaped)
function emailParagraph($html) {
    return '<p style="margin:0 0 14px;">' . $html . '</p>';
}

// Plain link for an email action (verify, reset password)
function renderEmailLink($label, $url) {
    return '<a href="' . mailEscape($url) . '" style="color:#1a73e8; font-weight:bold;">' . mailEscape($label) . '</a>';
}

// Label / value lines, e.g. Name: Maria Santos ($valueHtml already escaped)
function emailDetails(array $rows) {
    $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 14px; font-size:14px;">';
    foreach ($rows as $label => $valueHtml) {
        $html .= '<tr><td style="padding:2px 18px 2px 0; font-weight:bold; vertical-align:top; white-space:nowrap;">' . mailEscape($label) . ':</td>'
            . '<td style="padding:2px 0;">' . $valueHtml . '</td></tr>';
    }
    return $html . '</table>';
}

/*
    Greeting, message paragraphs, one action link and a closing note.
    Used by the account emails (verify email, reset password).
*/
function renderActionEmail($name, array $paragraphs, $linkLabel, $url, $footnote) {
    $safeUrl = mailEscape($url);
    $body = emailParagraph('Hi ' . mailEscape($name) . ',');

    foreach ($paragraphs as $paragraph) {
        $body .= emailParagraph(mailEscape($paragraph));
    }

    $body .= emailParagraph(renderEmailLink($linkLabel, $url))
        . emailParagraph('<span style="color:#5f6368; font-size:13px;">If the link does not open, copy this address into your web browser:<br>'
            . '<a href="' . $safeUrl . '" style="color:#1a73e8; word-break:break-all;">' . $safeUrl . '</a></span>')
        . emailParagraph(mailEscape($footnote))
        . '<p style="margin:0;">Thank you,<br>Norily&#039;s Vehicle Repair Shop</p>';

    return renderEmailLayout($body);
}
