<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';

if (!function_exists('smtp_create_mailer')) {
    /**
     * Build a PHPMailer instance configured for the project SMTP settings.
     */
    function smtp_create_mailer(): PHPMailer
    {
        $config = require __DIR__ . '/credentials.php';

        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $config['host'];
        $mailer->Port = $config['port'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $config['username'];
        $mailer->Password = $config['password'];

        $encryption = strtolower((string) $config['encryption']);
        if (in_array($encryption, ['tls', 'starttls'], true)) {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mailer->SMTPSecure = false;
            $mailer->SMTPAutoTLS = false;
        }
        $mailer->CharSet = 'UTF-8';
        $mailer->Timeout = $config['timeout'];
        $mailer->setFrom($config['from_email'], $config['from_name']);
        $mailer->SMTPDebug = (int) ($config['debug'] ?? 0);
        $mailer->Debugoutput = 'error_log';

        if (!empty($config['reply_to'])) {
            $mailer->addReplyTo($config['reply_to'], $config['reply_to_name'] ?: $config['reply_to']);
        }

        return $mailer;
    }
}

if (!function_exists('smtp_send_test_message')) {
    /**
     * Convenience helper that sends a plain-text SMTP test message.
     */
    function smtp_send_test_message(string $recipientEmail, string $recipientName, string $subject, string $body): array
    {
        try {
            $mailer = smtp_create_mailer();
            $mailer->clearAllRecipients();
            $mailer->addAddress($recipientEmail, $recipientName ?: $recipientEmail);
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->AltBody = $body;
            $mailer->isHTML(false);
            $mailer->send();

            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (Exception $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }
}
