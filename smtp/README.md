# SMTP Setup

This directory contains the standalone SMTP bootstrap used by Karyalay ERP.

## Installation
1. Run `composer install` (already required) to load PHPMailer.
2. Copy `credentials.php` to `credentials.local.php` and provide your SMTP server values, or export the keys defined in `.env.example`.
3. Keep `credentials.local.php` untracked (ignored by .gitignore) and never commit production passwords.

## Usage
- Include `smtp/mailer.php` wherever you need to send email and call `smtp_create_mailer()` to get a configured PHPMailer instance.
- For a quick verification, open `smtp/test_email.php` in your browser, fill the form, and send a message.

## Troubleshooting
- Set `SMTP_DEBUG` to a numeric level on the `PHPMailer` instance inside `smtp/mailer.php` if you need verbose output temporarily.
- Make sure outbound connections to your SMTP host/port are allowed by your firewall or hosting provider.
