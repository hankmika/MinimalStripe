<?php

class MinimalStripe
{
	private $secretKey;
	private $apiBase = 'https://api.stripe.com/v1/';

	/**
	 * Constructor
	 *
	 * @param string $secretKey Your Stripe Secret Key
	 */
	public function __construct($secretKey)
	{
		$this->secretKey = $secretKey;
	}

	/**
	 * Sends requests to Stripe's API via cURL with minimal handling
	 *
	 * @param string $method   HTTP method (GET|POST|DELETE)
	 * @param string $endpoint e.g. 'payment_intents'
	 * @param array  $params   Body/query params
	 * @return array           Decoded JSON or an array with 'error'
	 */
	private function request($method, $endpoint, $params = [])
	{
		$ch = curl_init();

		// Build URL
		$url = $this->apiBase . $endpoint;

		// Handle GET query params
		if (strtoupper($method) === 'GET' && !empty($params)) {
			$url .= '?' . http_build_query($params);
		}

		curl_setopt_array($ch, [
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERPWD        => $this->secretKey . ':', // Basic Auth
			CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
		]);

		// Handle POST/DELETE methods
		if (strtoupper($method) === 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		} elseif (strtoupper($method) === 'DELETE') {
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		}

		// Execute
		$responseBody = curl_exec($ch);
		$curlError    = curl_error($ch);
		$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$errPrefix = "We’re experiencing payment issues right now. Please try again later.";

		// 1.1 Handle cURL/connection failure
		if ($curlError) {
			// Log the raw error for debugging
			error_log("Stripe cURL connection error: " . $curlError);

			// Return a generic error to the caller
			return [
				'error' => $errPrefix . "(1.1)"
			];
		}

		// Attempt to parse JSON response
		$decoded = json_decode($responseBody, true);

		// 1.2. Handle unexpected HTTP status codes
		if ($httpCode < 200 || $httpCode >= 300) {
			// Log entire response for debugging
			error_log("Stripe HTTP Error ($httpCode): " . $responseBody);

			// 2. Stripe-Specific Error Handling
			// Stripe often returns an "error" object in the JSON
			$stripeError = isset($decoded['error']) ? $decoded['error'] : null;
			if ($stripeError) {
				$errorType = $stripeError['type'] ?? '';
				$errorCode = $stripeError['code'] ?? '';
				
				switch ($errorType) {
					case 'invalid_request_error':
						// 2.1 Invalid Request Errors
						// Log details; user sees a generic message
						// (already logged above with error_log())
						return [
							'error' => $errPrefix . "(2.1)"
						];

					case 'authentication_error':
						// 2.2 Authentication Errors
						return [
							'error' => $errPrefix . "(2.2)"
						];

					case 'card_error':
						// 2.3 Card Errors - typically handled by Stripe.js on the front end
						// However, if you do server-side confirmation, you might see these here.
						// We'll return the generic message so your front end can decide what to show.
						return [
							'error' => $errPrefix . "(2.3)"
						];

					case 'rate_limit_error':
						// 2.4 Rate Limit
						return [
							'error' => $errPrefix . "(2.4)"
						];

					default:
						// 2.5 API Errors or unknown
						return [
							'error' => $errPrefix . "(2.5)"
						];
				}
			}

			// If we got here, we didn't get a structured error from Stripe or no 'error' object
			return [
				'error' => $errPrefix . "(0)"
			];
		}

		// If successful, return decoded JSON
		return $decoded;
	}

	/**
	 * Creates a PaymentIntent with the specified amount and currency.
	 * Also does basic validation to avoid sending invalid data to Stripe.
	 *
	 * @param int    $amount             Amount in smallest currency unit (e.g., cents)
	 * @param string $currency           e.g., 'usd'
	 * @param array  $paymentMethodTypes e.g., ['card']
	 * @return array                     Stripe API response or ['error' => '...']
	 */
	public function createPaymentIntent($amount, $currency = 'usd', $paymentMethodTypes = ['card'])
	{
		// 3.1 Invalid Amount or Currency
		if (!is_int($amount) || $amount <= 0) {
			// Log it for debugging
			error_log("Invalid amount passed to createPaymentIntent: $amount");

			return [
				'error' => $errPrefix . "(3.1a)"
			];
		}

		// (Very basic currency check: 3-letter alpha code)
		if (!preg_match('/^[A-Za-z]{3}$/', $currency)) {
			error_log("Invalid currency passed to createPaymentIntent: $currency");
			return [
				'error' => $errPrefix . "(3.1b)"
			];
		}

		// Build params
		$params = [
			'amount'   => $amount,
			'currency' => strtolower($currency),
		];

		// Build payment_method_types array in the correct format
		foreach ($paymentMethodTypes as $i => $type) {
			$params["payment_method_types[$i]"] = $type;
		}

		// Send request
		$response = $this->request('POST', 'payment_intents', $params);
		return $response;
	}

	/**
	 * Confirms a PaymentIntent on the server
	 *
	 * @param string $paymentIntentId
	 * @param string $paymentMethod   The ID of the payment method (from front end)
	 * @param string $returnUrl       Optional return URL for 3D Secure
	 *
	 * @return array Stripe API response or ['error' => '...']
	 */
	public function confirmPaymentIntent($paymentIntentId, $paymentMethod, $returnUrl = null)
	{
		$params = [
			'payment_method' => $paymentMethod,
		];

		// If you’re using manual confirmation with next_action.redirect_to_url
		if ($returnUrl) {
			$params['return_url']          = $returnUrl;
			$params['confirmation_method'] = 'manual';
		}

		return $this->request('POST', "payment_intents/{$paymentIntentId}/confirm", $params);
	}

	/**
	 * Retrieve an existing PaymentIntent
	 *
	 * @param string $paymentIntentId
	 * @return array
	 */
	public function retrievePaymentIntent($paymentIntentId)
	{
		return $this->request('GET', "payment_intents/{$paymentIntentId}");
	}
}
