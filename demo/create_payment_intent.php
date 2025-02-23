<?php
require_once '../MinimalStripe.php';
require_once '../ak.php';

$stripe = new MinimalStripe($stripeApiKey);

// Suppose you have validated product / cart info on the server:
$amount   = 999; // $9.99 in cents
$currency = 'usd';

$result = $stripe->createPaymentIntent($amount, $currency, ['card']);

// If $result contains 'error', handle it:
if (isset($result['error'])) {
	// Return JSON to front end with the error
	echo json_encode(['error' => $result['error']]);
	exit;
}

// Otherwise, return the client_secret
echo json_encode(['clientSecret' => $result['client_secret']]);
