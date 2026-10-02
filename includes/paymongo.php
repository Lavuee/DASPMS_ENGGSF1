<?php
/*
    PayMongo API client (GCash through PayMongo Checkout).

    Customers pay on PayMongo's hosted checkout page, so this app never sees or
    stores GCash credentials. Payment status is only trusted after reading it back
    from PayMongo's API (see online_payment.php); webhooks just trigger that check.

    Configuration (.env): PAYMONGO_SECRET_KEY, PAYMONGO_WEBHOOK_SECRET,
    PAYMONGO_LIVEMODE, PAYMONGO_API_BASE, PAYMONGO_CA_BUNDLE.
*/

require_once __DIR__ . '/../config/app.php';

const PAYMONGO_SIGNATURE_TOLERANCE_SECONDS = 300;

function paymongoIsConfigured() {
    return (string)env('PAYMONGO_SECRET_KEY', '') !== '';
}

function paymongoLivemode() {
    return env('PAYMONGO_LIVEMODE', false) === true;
}

function paymongoMinimumAmount() {
    return floatval(env('PAYMONGO_MIN_AMOUNT', 20));
}

// Converts pesos to centavos (PayMongo amounts are integers in the smallest unit).
function toCentavos($amount) {
    return intval(round(floatval($amount) * 100));
}

/*
    Calls the PayMongo API and returns the decoded JSON response.
    Throws a generic exception on failure; details go to the error log only.
*/
function paymongoRequest($method, $path, array $body = null) {
    if (!paymongoIsConfigured()) {
        throw new Exception("Online GCash payments are not configured.");
    }

    $url = rtrim((string)env('PAYMONGO_API_BASE', 'https://api.paymongo.com/v1'), '/') . $path;
    $ch = curl_init($url);

    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => env('PAYMONGO_SECRET_KEY') . ':',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ];

    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    }

    if ($caBundle = env('PAYMONGO_CA_BUNDLE')) {
        $options[CURLOPT_CAINFO] = $caBundle;
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log("DASPMS PAYMONGO CONNECTION ERROR ($method $path): $curlError");
        throw new Exception("Could not reach the payment gateway. Please try again.");
    }

    $decoded = json_decode($response, true);

    if ($status >= 400 || !is_array($decoded)) {
        error_log("DASPMS PAYMONGO API ERROR ($method $path) HTTP $status: " . substr($response, 0, 1000));
        throw new Exception("The payment gateway rejected the request. Please try again later.");
    }

    return $decoded;
}

/*
    Creates a hosted checkout session limited to GCash.
    Returns ['id', 'checkout_url', 'payment_intent_id', 'livemode'].
*/
function paymongoCreateCheckoutSession($amount, $itemName, $description, $referenceNumber, array $metadata, $successUrl, $cancelUrl) {
    $response = paymongoRequest('POST', '/checkout_sessions', [
        'data' => [
            'attributes' => [
                'line_items' => [[
                    'name' => $itemName,
                    'amount' => toCentavos($amount),
                    'currency' => 'PHP',
                    'quantity' => 1
                ]],
                'payment_method_types' => ['gcash'],
                'description' => $description,
                'reference_number' => $referenceNumber,
                'metadata' => array_map('strval', $metadata),
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'send_email_receipt' => false,
                'show_description' => true,
                'show_line_items' => true
            ]
        ]
    ]);

    $session = $response['data'] ?? [];
    $attributes = $session['attributes'] ?? [];

    if (empty($session['id']) || empty($attributes['checkout_url'])) {
        error_log("DASPMS PAYMONGO: unexpected checkout session response: " . json_encode($response));
        throw new Exception("The payment gateway returned an unexpected response.");
    }

    return [
        'id' => $session['id'],
        'checkout_url' => $attributes['checkout_url'],
        'payment_intent_id' => $attributes['payment_intent']['id'] ?? null,
        'livemode' => !empty($attributes['livemode'])
    ];
}

// Returns the checkout session resource ('data' element) from PayMongo.
function paymongoRetrieveCheckoutSession($sessionId) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$sessionId)) {
        throw new Exception("Invalid checkout session id.");
    }

    $response = paymongoRequest('GET', '/checkout_sessions/' . $sessionId);
    return $response['data'] ?? [];
}

// Expires an unpaid checkout session so it can no longer be paid.
function paymongoExpireCheckoutSession($sessionId) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', (string)$sessionId)) {
        return;
    }

    paymongoRequest('POST', '/checkout_sessions/' . $sessionId . '/expire');
}

/*
    Returns the first successful payment of a checkout session as
    ['id', 'amount' (centavos), 'currency'], or null when nothing is paid.
*/
function paymongoPaidPayment(array $session) {
    foreach ($session['attributes']['payments'] ?? [] as $payment) {
        $attributes = $payment['attributes'] ?? [];

        if (($attributes['status'] ?? '') === 'paid') {
            return [
                'id' => $payment['id'] ?? null,
                'amount' => intval($attributes['amount'] ?? 0),
                'currency' => strtoupper($attributes['currency'] ?? 'PHP')
            ];
        }
    }

    return null;
}

/*
    Verifies the Paymongo-Signature header ("t=<timestamp>,te=<test sig>,li=<live sig>").
    The signature is HMAC-SHA256 of "<timestamp>.<raw body>" keyed with the webhook secret.
    Uses the live or test signature according to PAYMONGO_LIVEMODE and rejects old timestamps.
*/
function paymongoVerifyWebhookSignature($rawBody, $header, $secret) {
    if (!is_string($header) || $header === '' || (string)$secret === '') {
        return false;
    }

    $parts = [];

    foreach (explode(',', $header) as $pair) {
        $kv = explode('=', trim($pair), 2);

        if (count($kv) === 2) {
            $parts[$kv[0]] = $kv[1];
        }
    }

    $timestamp = $parts['t'] ?? '';
    $signature = paymongoLivemode() ? ($parts['li'] ?? '') : ($parts['te'] ?? '');

    if (!ctype_digit($timestamp) || $signature === '') {
        return false;
    }

    if (abs(time() - intval($timestamp)) > PAYMONGO_SIGNATURE_TOLERANCE_SECONDS) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

    return hash_equals($expected, $signature);
}
