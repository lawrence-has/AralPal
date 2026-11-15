<?php
/**
 * AralPal – PayMongo Helper
 *
 * Functions:
 *   create_payment_link()       – POST /v1/links  (create a hosted payment link)
 *   retrieve_payment_link()     – GET  /v1/links/{id} (verify status after return)
 *   generate_payment_token()    – create a signed, expiring URL token for success_url
 *   verify_payment_token()      – validate and decode the token
 *   validate_webhook_signature() – verify Paymongo-Signature header (HMAC-SHA256)
 */

require_once __DIR__ . '/config.php';

// Fallback when loaded without config.php (e.g. webhook handler loaded stand-alone)
if (!defined('PAYMONGO_SECRET_KEY')) {
    define('PAYMONGO_SECRET_KEY',     'sk_test_PLACEHOLDER_REPLACE_ME');
}
if (!defined('PAYMONGO_WEBHOOK_SECRET')) {
    define('PAYMONGO_WEBHOOK_SECRET', '');
}

/** Placeholder keys that trigger mock mode instead of real API calls. */
const PAYMONGO_PLACEHOLDER_KEYS = ['sk_test_PLACEHOLDER_REPLACE_ME', 'sk_test_placeholder'];

// ─────────────────────────────────────────────────────────────────────────────
// Internal helper – execute a cURL request against the PayMongo API.
// Returns the decoded JSON array or false on network / parse failure.
// Never logs the secret key value.
// ─────────────────────────────────────────────────────────────────────────────
function _paymongo_request(string $method, string $path, ?array $body = null): array|false
{
    $url        = 'https://api.paymongo.com/v1' . $path;
    $authHeader = 'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':');

    $ch = curl_init($url);
    $curlOpts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            $authHeader,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
    ];

    if ($method === 'POST' && $body !== null) {
        $curlOpts[CURLOPT_POST]       = true;
        $curlOpts[CURLOPT_POSTFIELDS] = json_encode($body);
    } elseif ($method === 'GET') {
        $curlOpts[CURLOPT_HTTPGET] = true;
    }

    curl_setopt_array($ch, $curlOpts);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr || $response === false) {
        error_log('AralPal PayMongo cURL error on ' . $method . ' ' . $path . ': ' . $curlErr);
        return false;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log('AralPal PayMongo invalid JSON (' . $httpCode . ') on ' . $method . ' ' . $path);
        return false;
    }

    return $decoded;
}

// ─────────────────────────────────────────────────────────────────────────────
// create_payment_link()
//
// Creates a PayMongo hosted payment link and returns the decoded API response.
// In mock mode (placeholder key), returns a fake response for local testing.
//
// @param float  $amount      Amount in PHP pesos (e.g. 299.00)
// @param string $description Short description shown on the payment page
// @param array  $meta        Must contain 'user_id' and 'payment_id' keys
// @return array|false
// ─────────────────────────────────────────────────────────────────────────────
function create_payment_link(float $amount, string $description, array $meta = []): array|false
{
    // ── Mock mode ─────────────────────────────────────────────────────────────
    if (in_array(PAYMONGO_SECRET_KEY, PAYMONGO_PLACEHOLDER_KEYS, true)) {
        $mockLinkId = 'mock_' . uniqid();
        // Embed a signed token so the mock success path works without a session
        $token = generate_payment_token((int) ($meta['payment_id'] ?? 0));
        return [
            'data' => [
                'id'         => $mockLinkId,
                'attributes' => [
                    'checkout_url' => SITE_URL . '/subscriptions/success.php?mock=1&token=' . urlencode($token),
                    'status'       => 'unpaid',
                ],
            ],
        ];
    }

    // ── Real API ──────────────────────────────────────────────────────────────
    $token = generate_payment_token((int) ($meta['payment_id'] ?? 0));

    $body = [
        'data' => [
            'attributes' => [
                'amount'      => (int) round($amount * 100),
                'description' => $description,
                'remarks'     => 'AralPal subscription',
                'success_url' => SITE_URL . '/subscriptions/success.php?token=' . urlencode($token),
                'cancel_url'  => SITE_URL . '/subscriptions/plans.php',
                'metadata'    => [
                    'user_id'    => $meta['user_id']    ?? 0,
                    'payment_id' => $meta['payment_id'] ?? 0,
                ],
            ],
        ],
    ];

    return _paymongo_request('POST', '/links', $body);
}

// ─────────────────────────────────────────────────────────────────────────────
// retrieve_payment_link()
//
// Fetches a PayMongo link by its ID and returns the decoded API response.
// In mock mode, returns a synthetic "paid" response so local testing works.
//
// @param string $link_id  The PayMongo link ID (data.id from create response)
// @return array|false
// ─────────────────────────────────────────────────────────────────────────────
function retrieve_payment_link(string $link_id): array|false
{
    if (in_array(PAYMONGO_SECRET_KEY, PAYMONGO_PLACEHOLDER_KEYS, true)) {
        // Mock: treat any mock link as paid
        return [
            'data' => [
                'id'         => $link_id,
                'attributes' => [
                    'status' => 'paid',
                    'amount' => 0, // mock — amount check skipped for placeholder keys
                ],
            ],
        ];
    }

    return _paymongo_request('GET', '/links/' . rawurlencode($link_id));
}

// ─────────────────────────────────────────────────────────────────────────────
// generate_payment_token()
//
// Creates a signed, expiring URL token that embeds the payment_id.
// Embedded in success_url so the return path works even when the PHP session
// has been lost (mobile, new tab, session timeout).
//
// Token format (URL-safe base64): base64( payment_id . '|' . expires_at ) + '.' + HMAC
//
// @param int $payment_id  The local payments.id row
// @param int $ttl         Token lifetime in seconds (default 2 hours)
// @return string
// ─────────────────────────────────────────────────────────────────────────────
function generate_payment_token(int $payment_id, int $ttl = 7200): string
{
    $expires  = time() + $ttl;
    $payload  = base64_encode($payment_id . '|' . $expires);
    $sig      = hash_hmac('sha256', $payload, PAYMONGO_SECRET_KEY);
    return $payload . '.' . $sig;
}

// ─────────────────────────────────────────────────────────────────────────────
// verify_payment_token()
//
// Validates a token generated by generate_payment_token().
// Returns the payment_id on success, or null on failure (tampered / expired).
//
// @param string $token
// @return int|null
// ─────────────────────────────────────────────────────────────────────────────
function verify_payment_token(string $token): ?int
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return null;
    }

    [$payload, $sig] = $parts;
    $expected = hash_hmac('sha256', $payload, PAYMONGO_SECRET_KEY);

    if (!hash_equals($expected, $sig)) {
        return null; // tampered
    }

    $decoded = base64_decode($payload, strict: true);
    if ($decoded === false) {
        return null;
    }

    [$payment_id_str, $expires_str] = array_pad(explode('|', $decoded, 2), 2, '0');

    if (time() > (int) $expires_str) {
        return null; // expired
    }

    $payment_id = (int) $payment_id_str;
    return $payment_id > 0 ? $payment_id : null;
}

// ─────────────────────────────────────────────────────────────────────────────
// validate_webhook_signature()
//
// Validates the Paymongo-Signature header using the official HMAC-SHA256 method.
//
// PayMongo signature header format:
//   t=<unix_timestamp>,te=<hmac_hex>,li=<hmac_hex>
//
// Signing string:  "<timestamp>.<raw_body>"
// Algorithm:       HMAC-SHA256 keyed with PAYMONGO_WEBHOOK_SECRET
// Replay window:   300 seconds
//
// @param string $raw_body        Raw request body (not parsed)
// @param string $sig_header      Value of the Paymongo-Signature header
// @param int    $replay_window   Max age in seconds (default 300)
// @return bool
// ─────────────────────────────────────────────────────────────────────────────
function validate_webhook_signature(string $raw_body, string $sig_header, int $replay_window = 300): bool
{
    if (PAYMONGO_WEBHOOK_SECRET === '') {
        error_log('AralPal: PAYMONGO_WEBHOOK_SECRET is not set — webhook signature validation skipped.');
        return false;
    }

    // Parse "t=...,te=...,li=..." header
    $parts = [];
    foreach (explode(',', $sig_header) as $segment) {
        [$key, $val] = array_pad(explode('=', $segment, 2), 2, '');
        $parts[trim($key)] = trim($val);
    }

    $timestamp = isset($parts['t']) ? (int) $parts['t'] : 0;
    $received_sig = $parts['te'] ?? ($parts['li'] ?? '');

    if ($timestamp === 0 || $received_sig === '') {
        return false;
    }

    // Reject replays
    if (abs(time() - $timestamp) > $replay_window) {
        error_log('AralPal: PayMongo webhook replay detected (timestamp delta: ' . abs(time() - $timestamp) . 's).');
        return false;
    }

    $signing_payload = $timestamp . '.' . $raw_body;
    $expected_sig    = hash_hmac('sha256', $signing_payload, PAYMONGO_WEBHOOK_SECRET);

    return hash_equals($expected_sig, $received_sig);
}
