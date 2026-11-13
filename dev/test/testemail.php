<?php
// test_email.php  -  TEMPORARY DEBUG FILE
// Open it once in the browser, read the result, then DELETE it from the server.
require_once("../../config/initialize.php");   // adjust the path if needed

$to = 'orlinoiancurt@gmail.com';   // <-- put YOUR email address here

header('Content-Type: text/plain; charset=utf-8');

// Quick checks on the key itself (shows only the first 9 characters)
echo "Key starts with: " . substr($_ENV['BREVO_API_KEY'], 0, 9) . "...\n";
if (str_starts_with($_ENV['BREVO_API_KEY'], 'xsmtpsib')) {
    echo "PROBLEM: this looks like an SMTP key. The code needs an API key (usually starts with xkeysib-).\n";
}
echo "Sender: " . $_ENV['MAIL_FROM_EMAIL'] . "  (must be verified in Brevo)\n\n";

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
    CURLOPT_POSTFIELDS     => json_encode([
        'sender'      => ['name' => $_ENV['MAIL_FROM_NAME'], 'email' => $_ENV['MAIL_FROM_EMAIL']],
        'to'          => [['email' => $to]],
        'subject'     => 'Test email',
        'htmlContent' => '<p>If you can read this, sending works.</p>',
    ]),
]);
$response = curl_exec($ch);
$status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);

echo "HTTP status : $status   (201 = Brevo accepted it)\n";
echo "cURL error  : " . ($curlErr !== '' ? $curlErr : '(none)') . "\n";
echo "Brevo said  : " . ($response === false ? '(no response)' : $response) . "\n";