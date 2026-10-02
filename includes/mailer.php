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
*/
function sendHtmlMail($to, $subject, $html) {
    global $mailLastError;
    $mailLastError = '';

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $mailLastError = 'Invalid recipient address';
        return false;
    }

    $driver = strtolower((string)env('MAIL_DRIVER', 'mail'));

    if ($driver === 'log') {
        return logMail($to, $subject, $html);
    }

    if ($driver === 'smtp') {
        return sendSmtpMail($to, $subject, $html);
    }

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDRESS . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM_ADDRESS . "\r\n";

    if (@mail($to, $subject, $html, $headers)) {
        return true;
    }

    $error = error_get_last();
    $mailLastError = $error['message'] ?? 'Unknown mail error';
    return false;
}

function sendSmtpMail($to, $subject, $html) {
    global $mailLastError;

    require_once __DIR__ . '/../vendor/autoload.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = (string)env('SMTP_HOST', '');
        $mail->Port = intval(env('SMTP_PORT', 587));
        $mail->SMTPAuth = true;
        $mail->Username = (string)env('SMTP_USERNAME', '');
        $mail->Password = (string)env('SMTP_PASSWORD', '');
        $mail->SMTPSecure = env('SMTP_ENCRYPTION', 'tls') === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 15;

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($to);
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

function logMail($to, $subject, $html) {
    $logDir = __DIR__ . '/../logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $entry = "===== " . date('Y-m-d H:i:s') . " =====\nTo: $to\nSubject: $subject\n\n$html\n\n";
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
    Wraps content in the shop's standard email layout.
    $bodyHtml must already be escaped.
*/
function renderEmailLayout($bodyHtml) {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f4f5; margin: 0; padding: 0; -webkit-font-smoothing: antialiased; }
        .wrapper { width: 100%; background-color: #f4f4f5; padding: 40px 0; }
        .container { max-width: 540px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); border: 1px solid #e4e4e7; }
        .header { background-color: #ffffff; padding: 30px 40px 20px; text-align: center; border-bottom: 1px solid #f4f4f5; }
        .header h1 { margin: 0; color: #18181b; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
        .body { padding: 40px; color: #3f3f46; font-size: 16px; line-height: 1.6; }
        .body p { margin: 0 0 20px 0; }
        .btn-wrapper { text-align: center; margin: 35px 0; }
        .btn { display: inline-block; padding: 14px 32px; background-color: #f5c518; color: #111111 !important; text-decoration: none; border-radius: 999px; font-weight: 900; font-size: 15px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 12px rgba(245, 197, 24, 0.25); }
        .footer { background-color: #fafafa; padding: 24px 40px; text-align: center; font-size: 13px; color: #a1a1aa; border-top: 1px solid #e4e4e7; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>Norily's Repair Shop</h1>
            </div>
            <div class="body">
                {$bodyHtml}
            </div>
            <div class="footer">
                <p>&copy; 2026 Norily's Vehicle Repair Shop. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
}

// Standard email with a greeting, message paragraphs and one call-to-action button.
function renderActionEmail($name, array $paragraphs, $buttonLabel, $url, $footnote) {
    $safeUrl = mailEscape($url);
    $body = '<p>Hi <strong>' . mailEscape($name) . '</strong>,</p>';

    foreach ($paragraphs as $paragraph) {
        $body .= '<p>' . mailEscape($paragraph) . '</p>';
    }

    $body .= '<div class="btn-wrapper"><a href="' . $safeUrl . '" class="btn">' . mailEscape($buttonLabel) . '</a></div>'
        . '<p style="font-size: 14px; color: #71717a;">If the button above doesn\'t work, copy and paste this link into your web browser:</p>'
        . '<p style="font-size: 13px; word-break: break-all;"><a href="' . $safeUrl . '" style="color: #3b82f6;">' . $safeUrl . '</a></p>'
        . '<p style="font-size: 13px; color: #71717a;">' . mailEscape($footnote) . '</p>';

    return renderEmailLayout($body);
}
