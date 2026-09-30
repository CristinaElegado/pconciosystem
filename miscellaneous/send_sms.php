<?php
/**
 * send_sms.php
 *
 * Helper function for sending SMS via Semaphore (https://semaphore.co).
 * This is the most common and affordable SMS API for Philippine numbers.
 *
 * HOW TO USE:
 *   include __DIR__ . '/../miscellaneous/send_sms.php';
 *   $result = send_sms('09123456789', 'Hello! Your appointment is tomorrow at 10:00 AM.');
 *   if ($result['success']) { // sent successfully }
 *
 * SETUP:
 *   1. Register at https://semaphore.co and buy credits.
 *   2. Get your API key from the dashboard.
 *   3. Set SMS_API_KEY and SMS_SENDER_NAME below (or in a config file).
 *
 * Semaphore API Docs: https://semaphore.co/docs
 */

// ============================================================
// CONFIGURATION — change these to your actual values
// ============================================================
define('SMS_API_KEY',     getenv('SEMAPHORE_API_KEY') ?: 'cfe2aeb5787e714463eac76ebec2b5ce');
define('SMS_SENDER_NAME', getenv('SEMAPHORE_SENDER')  ?: 'DentalClinic');  // Max 11 chars, registered sender name
define('SMS_API_URL',     'https://api.semaphore.co/api/v4/messages');
// ============================================================

/**
 * Sends an SMS to a Philippine mobile number.
 *
 * @param  string $phone   Recipient number. Accepts 09xxxxxxxxx, +639xxxxxxxxx, or 639xxxxxxxxx.
 * @param  string $message Text message body (max 160 chars per SMS segment).
 * @return array  ['success' => bool, 'message_id' => string|null, 'error' => string|null]
 */
function send_sms(string $phone, string $message): array
{
    // Normalize the phone number to 09xxxxxxxxx format
    $phone = normalize_ph_number($phone);
    if ($phone === null) {
        return ['success' => false, 'message_id' => null, 'error' => 'Invalid Philippine phone number.'];
    }

    $payload = [
        'apikey'      => SMS_API_KEY,
        'number'      => $phone,
        'message'     => $message,
        'sendername'  => SMS_SENDER_NAME,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => SMS_API_URL,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("SMS send_sms() cURL error: {$curlError}");
        return ['success' => false, 'message_id' => null, 'error' => "cURL error: {$curlError}"];
    }

    $data = json_decode($response, true);

    // Semaphore returns an array of message objects on success (HTTP 200)
    if ($httpCode === 200 && is_array($data) && !empty($data)) {
        $first = $data[0];
        $msgId = $first['message_id'] ?? null;
        return ['success' => true, 'message_id' => $msgId, 'error' => null];
    }

    // On error, Semaphore returns {"status": "error", "message": "..."}
    $errMsg = $data['message'] ?? $response;
    error_log("SMS send_sms() API error (HTTP {$httpCode}): {$errMsg}");
    return ['success' => false, 'message_id' => null, 'error' => $errMsg];
}

/**
 * Normalizes a Philippine mobile number to 09xxxxxxxxx format.
 * Returns null if the number is unrecognizable.
 */
function normalize_ph_number(string $phone): ?string
{
    // Strip all non-digit characters except leading +
    $cleaned = preg_replace('/[^\d]/', '', $phone);

    if (strlen($cleaned) === 11 && str_starts_with($cleaned, '09')) {
        return $cleaned; // Already 09xxxxxxxxx
    }

    if (strlen($cleaned) === 12 && str_starts_with($cleaned, '639')) {
        return '0' . substr($cleaned, 2); // 639xxxxxxxxx → 09xxxxxxxxx
    }

    if (strlen($cleaned) === 10 && str_starts_with($cleaned, '9')) {
        return '0' . $cleaned; // 9xxxxxxxxx → 09xxxxxxxxx
    }

    return null; // Unrecognized format
}
