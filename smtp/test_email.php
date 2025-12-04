<?php
require_once __DIR__ . '/mailer.php';

$status = null;
$toEmail = '';
$toName = '';
$subject = 'SMTP Test Message';
$message = 'Hello from the Karyalay ERP SMTP test page.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toEmail = filter_input(INPUT_POST, 'to_email', FILTER_SANITIZE_EMAIL) ?: '';
    $toName = trim((string) ($_POST['to_name'] ?? ''));
    $subject = trim((string) ($_POST['subject'] ?? $subject));
    $message = trim((string) ($_POST['message'] ?? $message));

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $status = ['type' => 'error', 'message' => 'Please provide a valid recipient email address.'];
    } else {
        $result = smtp_send_test_message($toEmail, $toName, $subject !== '' ? $subject : 'SMTP Test Message', $message !== '' ? $message : 'Hello from the Karyalay ERP SMTP test page.');
        $status = $result['success']
            ? ['type' => 'success', 'message' => $result['message']]
            : ['type' => 'error', 'message' => $result['message']];
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMTP Test Email</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 24px; }
        .container { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; font-size: 1.5rem; }
        label { display: block; margin-bottom: 6px; font-weight: bold; }
        input, textarea { width: 100%; padding: 10px; margin-bottom: 16px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
        button { background: #0066cc; color: #fff; border: none; padding: 12px 20px; font-size: 1rem; border-radius: 4px; cursor: pointer; }
        button:hover { background: #004999; }
        .alert { padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; }
        .alert.success { background: #e6ffed; border: 1px solid #22c55e; color: #14532d; }
        .alert.error { background: #ffefef; border: 1px solid #ef4444; color: #7f1d1d; }
        .note { font-size: 0.9rem; color: #666; }
    </style>
</head>
<body>
<div class="container">
    <h1>SMTP Test Email</h1>
    <p class="note">Use this form to verify that your SMTP credentials are working. Ensure <code>smtp/credentials.php</code> (or <code>credentials.local.php</code>) contains valid values before sending.</p>

    <?php if ($status): ?>
        <div class="alert <?php echo htmlspecialchars($status['type'], ENT_QUOTES); ?>">
            <?php echo htmlspecialchars($status['message'], ENT_QUOTES); ?>
        </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <label for="to_email">Recipient Email</label>
        <input type="email" id="to_email" name="to_email" required value="<?php echo htmlspecialchars($toEmail ?? '', ENT_QUOTES); ?>">

        <label for="to_name">Recipient Name (optional)</label>
        <input type="text" id="to_name" name="to_name" value="<?php echo htmlspecialchars($toName ?? '', ENT_QUOTES); ?>">

        <label for="subject">Subject</label>
        <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($subject ?? 'SMTP Test Message', ENT_QUOTES); ?>">

        <label for="message">Message</label>
        <textarea id="message" name="message" rows="6"><?php echo htmlspecialchars($message ?? 'Hello from the Karyalay ERP SMTP test page.', ENT_QUOTES); ?></textarea>

        <button type="submit">Send Test Email</button>
    </form>
</div>
</body>
</html>
