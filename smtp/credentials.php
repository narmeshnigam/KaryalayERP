<?php
/**
 * SMTP Credential Loader
 *
 * Secure usage checklist:
 * 1. Duplicate this file as credentials.local.php and populate the values with your SMTP vendor data.
 * 2. Add the same keys to .env (see .env.example) for production/CI deployments.
 * 3. Keep credentials.local.php out of source control (.gitignore already excludes it).
 * 4. NEVER hardcode production passwords in this template.
 *
 * Field reference:
 * - host / port / encryption : Match the SMTP server requirements (tls/ssl/empty).
 * - username / password      : Auth credentials issued by your email provider.
 * - from_* / reply_*         : Default sender + reply-to identities shown in outgoing emails.
 * - timeout                  : Seconds to wait before failing a connection attempt.
 *
 * The returned array is consumed by smtp/mailer.php to configure PHPMailer.
 */

$defaults = [
    'host'        => getenv('SMTP_HOST') ?: 'smtp.example.com',
    'port'        => (int) (getenv('SMTP_PORT') ?: 587),
    'encryption'  => getenv('SMTP_ENCRYPTION') ?: 'tls',
    'username'    => getenv('SMTP_USERNAME') ?: 'username@example.com',
    'password'    => getenv('SMTP_PASSWORD') ?: 'your-strong-password',
    'from_email'  => getenv('SMTP_FROM_EMAIL') ?: 'noreply@example.com',
    'from_name'   => getenv('SMTP_FROM_NAME') ?: 'Karyalay ERP Bot',
    'reply_to'    => getenv('SMTP_REPLY_TO') ?: '',
    'reply_to_name' => getenv('SMTP_REPLY_TO_NAME') ?: '',
    'timeout'     => (int) (getenv('SMTP_TIMEOUT') ?: 30),
    'debug'       => (int) (getenv('SMTP_DEBUG_LEVEL') ?: 0),
];

$localOverride = __DIR__ . '/credentials.local.php';
if (is_readable($localOverride)) {
    $overrides = require $localOverride;
    if (is_array($overrides)) {
        return array_merge($defaults, $overrides);
    }
}

return $defaults;
