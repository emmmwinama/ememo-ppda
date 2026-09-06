<?php
// send-mail.php

// 1) Include PHPMailer’s class files from your `phpmailer/src` folder
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email via Outlook.com (Microsoft 365) SMTP, with debug output.
 *
 * @param string $to
 * @param string $subject
 * @param string $body
 * @param array  $options  Optional overrides:
 *                         - smtpHost, smtpPort, smtpUser, smtpPass
 *                         - fromEmail, fromName
 * @return bool
 */
function sendEmailNotification(
    string $to,
    string $subject,
    string $body,
    array  $options = []
): bool {
    // — load settings (swap in getenv() or pass via $options in production)
    $smtpHost  = $options['smtpHost']  ?? getenv('SMTP_HOST')     ?: 'smtp-mail.outlook.com';
    $smtpPort  = $options['smtpPort']  ?? getenv('SMTP_PORT')     ?: 587;
    $smtpUser  = $options['smtpUser']  ?? getenv('SMTP_USERNAME') ?: 'notifications@ppda.mw';
    $smtpPass  = $options['smtpPass']  ?? getenv('SMTP_PASSWORD') ?: '#Ppda@2025';
    $fromEmail = $options['fromEmail'] ?? getenv('FROM_EMAIL')     ?: $smtpUser;
    $fromName  = $options['fromName']  ?? getenv('FROM_NAME')      ?: 'PPDA Notifications';

    $mail = new PHPMailer(true);
    try {
        // — SMTP configuration
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)$smtpPort;

        // — DEBUGGING ON
        $mail->SMTPDebug  = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            echo "[" . $level . "] " . htmlspecialchars($str) . "\n";
        };

        // — Sender & recipient
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        // — Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        echo "✔ Email sent successfully.\n";
        return true;
    } catch (Exception $e) {
        echo "✘ Mailer Error: " . $mail->ErrorInfo . "\n";
        echo "Exception Message: " . $e->getMessage() . "\n";
        return false;
    }
}

// If run from CLI, do a test send:
if (php_sapi_name() === 'cli') {
    sendEmailNotification(
        'recipient@example.com',
        'SMTP Debug Test',
        '<p>If you see this, SMTP is working.</p>'
    );
}
