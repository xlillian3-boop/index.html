<?php

$botToken = '8791002628:AAFVALs75rwnumG2ImZXx90aVwkZWmH8-8Y';
$chatId   = '6717107884';

header('Content-Type: application/json');

// -----------------------------
// Load .env
// -----------------------------
function loadEnv(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    $env = [];

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');

        $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }

    return $env;
}

$env = loadEnv(__DIR__ . '/.env');

$botToken = $env['TELEGRAM_BOT_TOKEN'] ?? '';
$chatId   = $env['TELEGRAM_CHAT_ID'] ?? '';

if (!$botToken || !$chatId) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Telegram configuration is missing.'
    ]);

    exit;
}


// -----------------------------
// Receive frontend data
// -----------------------------
$rawInput = file_get_contents('php://input');

$data = json_decode($rawInput, true);

if (!is_array($data)) {
    $data = $_POST;
}


// -----------------------------
// Request information
// -----------------------------
$page = $_SERVER['REQUEST_URI'] ?? '/';

$ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

$time = date('Y-m-d H:i:s T');


// -----------------------------
// Format frontend data
// -----------------------------
function formatData($data, $indent = 0): string
{
    if (!is_array($data)) {
        return htmlspecialchars((string) $data);
    }

    $output = '';

    foreach ($data as $key => $value) {

        $key = htmlspecialchars((string) $key);

        if (is_array($value)) {
            $output .= str_repeat('  ', $indent)
                . "• <b>{$key}</b>\n"
                . formatData($value, $indent + 1);
        } else {
            $value = htmlspecialchars((string) $value);

            $output .= str_repeat('  ', $indent)
                . "• <b>{$key}:</b> {$value}\n";
        }
    }

    return $output ?: 'No client data received.';
}


// -----------------------------
// Telegram message
// -----------------------------
$clientData = formatData($data);

$message = "
<b>🚨 NEW SITE VISIT</b>

━━━━━━━━━━━━━━━━━━━━

<b>📄 Page</b>
<code>" . htmlspecialchars($page) . "</code>

<b>🌐 IP Address</b>
<code>" . htmlspecialchars($ip) . "</code>

<b>🕐 Time</b>
<code>" . htmlspecialchars($time) . "</code>

<b>🖥 Browser / User Agent</b>
<code>" . htmlspecialchars($userAgent) . "</code>

━━━━━━━━━━━━━━━━━━━━

<b>📦 Client Data</b>

{$clientData}

━━━━━━━━━━━━━━━━━━━━
";


// -----------------------------
// Send to Telegram
// -----------------------------
$telegramUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

$postData = [
    'chat_id' => $chatId,
    'text' => $message,
    'parse_mode' => 'HTML',
    'disable_web_page_preview' => true
];

$ch = curl_init($telegramUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);

$curlError = curl_error($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);


// -----------------------------
// Response
// -----------------------------
if ($curlError || $httpCode !== 200) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Failed to send Telegram message.'
    ]);

    exit;
}

echo json_encode([
    'success' => true
]);