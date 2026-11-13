<?php
// functions/mailFunctions.php
// Needs the constants from config/mail_config.php (BREVO_API_KEY, MAIL_FROM_EMAIL, MAIL_FROM_NAME)
// and err_log() from debugFunctions.php.

/**
 * Send one email through Brevo's HTTP API (InfinityFree blocks PHP mail()).
 *
 * @param string $toEmail  Recipient address
 * @param string $toName   Recipient name (use '' if you don't have one)
 * @param string $subject  Subject line
 * @param string $html     HTML body
 * @param string $text     Plain-text body (optional, but it helps with spam filters)
 * @return bool            true if Brevo accepted the message, false otherwise (the reason is logged)
 *
 * Example:
 *   sendEmail('maria@example.com', 'Maria', 'Hello', '<p>Hi Maria</p>', 'Hi Maria');
 */
function sendEmail(string $toEmail, string $toName, string $subject, string $html, string $text = ''): bool
{
    $recipient = ['email' => $toEmail];
    if ($toName !== '') {
        $recipient['name'] = $toName;
    }

    $body = [
        'sender'      => ['name' => $_ENV['MAIL_FROM_NAME'], 'email' => $_ENV['MAIL_FROM_EMAIL']],
        'to'          => [$recipient],
        'subject'     => $subject,
        'htmlContent' => $html,
    ];
    if ($text !== '') {
        $body['textContent'] = $text;
    }

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $_ENV['BREVO_API_KEY'],
        ],
        CURLOPT_POSTFIELDS     => json_encode($body),
    ]);
    $response = curl_exec($ch);
    $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    // No curl_close() needed: since PHP 8.0 the handle is freed automatically (and curl_close is deprecated in 8.5).

    if ($response === false) {
        err_log('sendEmail cURL error: ' . $curlErr);
        return false;
    }
    if ($status < 200 || $status >= 300) {
        err_log("sendEmail Brevo HTTP $status: $response");
        return false;
    }
    return true;
}