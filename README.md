# MinimalStripe

Minimalistic **Stripe** implementation to process online card payments without using the full Stripe PHP SDK with its hundreds of files.

Contents:

- **`MinimalStripe.php`**: a PHP class for sending requests to Stripe’s API via cURL.
- **`ak.php`**: a file to store your private API key.
- **`demo/create_payment_intent.php`**: a server-side endpoint that creates the Stripe PaymentIntent (and returns the client secret).  
- **`demo/payment.html`**: a minimal front-end that collects card details using **Stripe.js** and confirms the PaymentIntent.

---

## 1. Prerequisites

- **PHP 7.2+** (or higher) with cURL support enabled.  
- A Stripe **test** account (or live account) with **API keys**.  
- An HTTPS environment is **highly recommended**, especially if you go live (Stripe enforces HTTPS for live payments).

---

## 2. Configuration

### 2.1. Stripe API Keys

- In **`MinimalStripe.php`** (or wherever you instantiate it), provide your **secret key** (`sk_test_...` or `sk_live_...`).
- In **`index.html`**, provide your **publishable key** (`pk_test_...` or `pk_live_...`).

> **Important**: Make sure you use **matching** test or live keys from the same Stripe account. Mixing keys from different environments leads to errors like “No such payment_intent”.

### 2.2. MinimalStripe Configuration (Server Side)

```php
// MinimalStripe.php (constructor example)
public function __construct($secretKey)
{
    $this->secretKey = $secretKey; // e.g., 'sk_test_abc123...'
}
```

### 2.3. Index.html Configuration (Client Side)

```html
<script>
  const stripe = Stripe('pk_test_1234...'); // Replace with your publishable key
  // ...
</script>
```

## 3. Usage

Start a local server (e.g., using PHP’s built-in server):

```bash
php -S localhost:8000
```

Then visit [http://localhost:8000/index.html](http://localhost:8000/index.html) in your browser.

Open `index.html` in your browser.  
You’ll see a basic form with a card field and a **“Pay”** button.

1. Enter a Test Card (e.g., `4242 4242 4242 4242` with a valid future date and any 3-digit CVC).
2. Click **Pay**.
3. If all is configured correctly, Stripe will process the payment.  
   You should see an alert saying **“Payment succeeded!”** in test mode.

---

## 4. Explanation of How It Works

- `index.html` loads **Stripe.js** and creates a **Card Element**.
- When you click **“Pay”**, it sends a `POST` request to `create_payment_intent.php`, which:
	- Instantiates the `MinimalStripe` class (with your secret key).
	- Calls `createPaymentIntent($amount, $currency, ['card'])`.
	- Returns the `client_secret` of the new **PaymentIntent** (or an error).

- The front end calls:
	```js
	stripe.confirmCardPayment(clientSecret, {
	  payment_method: { card: cardElement }
	});
	```

	- **Stripe.js** securely transmits the card details and finalizes the **PaymentIntent**.
	- If **3D Secure / SCA** is required, **Stripe.js** prompts the user to authenticate.
	- If successful, you see **“Payment succeeded!”**.

---

## 5. Handling 3D Secure (SCA)

Stripe automatically triggers 3D Secure when required.

- If the card needs authentication, `stripe.confirmCardPayment(...)` responds with `requires_action`.
- You can make a second call to `stripe.confirmCardPayment(...)` without parameters to prompt the user to complete 3D Secure.
- If the user completes it successfully, the `PaymentIntent` moves to `succeeded`.

---

## 6. Troubleshooting

- **No such payment_intent**: Commonly caused by mixing test/live keys, using an expired `client_secret`, or a mismatch in keys.
- **Card errors (insufficient funds, etc.)**: Display `error.message` from Stripe.js to the user.
- **Network errors**: If cURL fails or Stripe is unreachable, the script returns a generic error.

---

## 7. Going Live

1. **Switch to live keys**:
	- Secret key: `sk_live_...`
	- Publishable key: `pk_live_...`
2. **Host on a production server with HTTPS**.
3. **Update domain or environment settings** in your Stripe Dashboard if necessary.



















