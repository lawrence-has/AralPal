<?php
/**
 * AralPal – PayMongo Payment Link Helper
 */

require_once __DIR__ . '/config.php';

if (!defined('PAYMONGO_SECRET_KEY')) {
    define('PAYMONGO_SECRET_KEY', 'sk_test_placeholder');
}

/**
 * Creates a PayMongo payment link and returns the decoded API response.
 *
 * @param float  $amount      Amount in PHP (e.g. 299.00)
 * @param string $description Short description shown on the payment page
 * @param array  $meta        Optional metadata (currently unused in body)
 *
 * @return array|false Decoded JSON response array, or false on failure
 */
function create_payment_link(float $amount, string $description, array $meta = []): array|false
{
    $payload = json_encode([
        'data' => [
            'attributes' => [
                'amount'      => (int) ($amount * 100),
                'description' => $description,
                'remarks'     => 'AralPal payment',
            ],
        ],
    ]);

    $authHeader = 'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':');

    $ch = curl_init('https://api.paymongo.com/v1/links');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            $authHeader,
            'Content-Type: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error || $response === false) {
        error_log('AralPal PayMongo cURL error: ' . $error);
        return false;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log('AralPal PayMongo invalid JSON response: ' . $response);
        return false;
    }

    return $decoded;
}
