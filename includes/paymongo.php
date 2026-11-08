<?php
/**
 * AralPal – PayMongo Payment Link Helper
 */

require_once __DIR__ . '/config.php';

// PAYMONGO_SECRET_KEY is defined in config.php; this is a fallback only.
if (!defined('PAYMONGO_SECRET_KEY')) {
    define('PAYMONGO_SECRET_KEY', 'sk_test_PLACEHOLDER_REPLACE_ME');
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
    // ── Mock mode: skip real API call when using placeholder keys ─────────────
    $placeholder_keys = ['sk_test_PLACEHOLDER_REPLACE_ME', 'sk_test_placeholder'];
    if (in_array(PAYMONGO_SECRET_KEY, $placeholder_keys, true)) {
        return [
            'data' => [
                'id'         => 'mock_' . uniqid(),
                'attributes' => [
                    'checkout_url' => SITE_URL . '/subscriptions/success.php?mock=1&plan_id=' . ($meta['plan_id'] ?? 0),
                ],
            ],
        ];
    }

    $payload = json_encode([
        'data' => [
            'attributes' => [
                'amount'      => (int) ($amount * 100),
                'description' => $description,
                'remarks'     => 'AralPal payment',
                'success_url' => SITE_URL . '/subscriptions/success.php',
                'cancel_url'  => SITE_URL . '/subscriptions/plans.php',
                'metadata'    => [
                    'user_id' => $meta['user_id'] ?? 0,
                    'plan_id' => $meta['plan_id'] ?? 0,
                ],
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
