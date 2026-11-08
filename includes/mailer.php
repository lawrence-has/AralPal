<?php
/**
 * AralPal – Mail Helper (PHPMailer via Composer)
 */

require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

// ── SMTP constants (define once; pages may define them before including this) ─
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com');
}
if (!defined('SMTP_USER')) {
    define('SMTP_USER', 'aralpal@gmail.com');
}
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', '');
}

/**
 * Sends an HTML email via SMTP, with a fallback to PHP's mail() when no
 * SMTP password is configured.
 *
 * @param string $to      Recipient email address
 * @param string $subject Email subject
 * @param string $body    HTML email body
 *
 * @return bool True on success, false on failure
 */
function send_mail(string $to, string $subject, string $body): bool
{
    // If no SMTP password configured, fall back to PHP mail()
    if (empty(SMTP_PASS)) {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: AralPal <" . SMTP_USER . ">\r\n";
        return mail($to, $subject, $body, $headers);
    }

    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = 587;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = 'tls';

        $mail->setFrom(SMTP_USER, 'AralPal');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (MailerException $e) {
        error_log('AralPal mailer error: ' . $e->getMessage());
        return false;
    }
}
