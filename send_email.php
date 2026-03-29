<?php
// Filename: smart/send_email.php

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Load PHPMailer source files
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'config.php'; // SMTP credentials — gitignored, never committed

/**
 * Sends a plain text email notification using PHPMailer via SMTP.
 * @param string $to Recipient email address.
 * @param string $subject Email subject line.
 * @param string $message Email body content (HTML).
 * @return bool True on success, false on failure.
 */
function send_alert_email($to, $subject, $message) {
    $mail = new PHPMailer(true);

    try {
        // --- SMTP SERVER SETTINGS ---
        // SMTP Debugging is now turned OFF to prevent "headers already sent" errors.
        $mail->SMTPDebug = SMTP::DEBUG_OFF;

        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Implicit SSL
        $mail->Port       = SMTP_PORT;

        // SSL certificate verification is ENABLED (secure default).
        // If your mail server uses a self-signed cert, replace 'verify_peer'
        // with the path to your CA bundle instead of disabling verification.
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ]
        ];

        // --- RECIPIENTS ---
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // --- CONTENT ---
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $message;

        // Generate a basic plain-text version from the HTML for non-HTML clients
        $plain_text = str_ireplace(['<br>', '</tr>', '</h1>', '</h2>', '</p>', '</table>'], "\n", $message);
        $plain_text = strip_tags($plain_text);
        $mail->AltBody = html_entity_decode($plain_text);


        $mail->send();
        // Log success for debugging purposes
        error_log("Email alert sent successfully to " . $to);
        return true;
    } catch (Exception $e) {
        // Log the detailed error message from PHPMailer
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

