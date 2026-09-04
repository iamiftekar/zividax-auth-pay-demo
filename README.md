# Zividax Pay Demo Store

A minimal, educational PHP example showing how a website can integrate **Sign in with Zividax** and **Pay with Zividax Pay** for a subscription checkout.

## What this repository demonstrates

- Zividax OAuth login
- Server-side OAuth code exchange
- Server-side `zx_live_...` payment request creation
- Subscription checkout using `request_type=subscription`
- Redirecting the customer to Zividax Pay
- Server-side payment-status verification
- A simple local JSON subscription record
- Pure PHP + HTML + CSS + JavaScript
- No `.env` files and no API credentials in browser code

> This repository is intentionally simple so developers can understand the complete request flow before replacing the demo storage layer with a production database.

## Repository structure

```text
.
├── callback.php             # OAuth callback + code exchange
├── config.php               # PHP-only configuration
├── dashboard.php            # Signed-in demo dashboard
├── index.php                # Landing page
├── login.php                # Starts Zividax OAuth
├── subscribe.php            # Creates the payment request
├── payment-complete.php     # Verifies payment and activates subscription
├── logout.php               # Destroys the local session
├── storage/
│   ├── users.json
│   ├── orders.json
│   ├── subscriptions.json
│   └── .htaccess
├── .htaccess
├── .gitignore
├── LICENSE
└── README.md
```

## Requirements

- PHP 8.1+
- PHP cURL extension
- HTTPS for a real OAuth/payment deployment
- A Zividax Sign-in App registered in Console
- An active `zx_live_...` developer API key

## 1. Configure the demo

Open `config.php` and set the PHP constants:

```php
const APP_URL = 'https://YOUR-DOMAIN.example/demo';
const ZIVIDAX_CLIENT_ID = 'zxc_your_client_id';
const ZIVIDAX_CLIENT_SECRET = 'your_oauth_client_secret';
const ZIVIDAX_API_KEY = 'zx_live_your_active_api_key';
```

Do **not** commit real client secrets or API keys.

Register this exact callback URL in the Zividax Sign-in Apps page:

```text
https://YOUR-DOMAIN.example/demo/callback.php
```

The payment API key stays on the server. It is never placed in JavaScript or returned to the browser.

## 2. Run locally

From the repository directory:

```bash
php -S 127.0.0.1:8080
```

Open:

```text
http://127.0.0.1:8080/
```

For live Zividax OAuth/payment calls, use a public HTTPS URL and register that URL as the callback.

## 3. Understand the OAuth flow

```text
Browser
   │
   │ Sign in with Zividax
   ▼
Zividax Console /oauth/authorize
   │
   │ redirect with ?code=...&state=...
   ▼
callback.php
   │
   │ POST /oauth/token
   ▼
Zividax Console
   │
   │ access_token
   ▼
callback.php
   │
   │ GET /oauth/userinfo
   ▼
Local JSON user record
   │
   ▼
dashboard.php
```

The app validates the OAuth `state` value before accepting the authorization code.

## 4. Understand the payment flow

```text
Customer
   │
   │ Create payment request
   ▼
subscribe.php
   │
   │ server-side POST
   │ Authorization: Bearer zx_live_...
   ▼
https://console.zividax.uk/public/api/v1/payments/request
   │
   │ payment_url + request_id
   ▼
Zividax Pay
   │
   │ customer reviews and accepts/rejects
   ▼
payment-complete.php
   │
   │ server-side status check
   ▼
/public/api/v1/payments/status
   │
   ▼
Local subscription record
```

Example request created by `subscribe.php`:

```json
{
  "customer_username": "customer_username",
  "company_name": "Zividax Demo Store",
  "service_name": "Demo Premium",
  "request_type": "subscription",
  "amount": 1.00,
  "description": "Monthly Demo Premium subscription for the Zividax Demo Store."
}
```

## 5. Payment economics

The Zividax Pay gateway documentation specifies:

- Platform fee: **3.50%**
- Developer net: **96.50%**
- Developer wallet release: **15-day hold**

For example, a `$1.00` payment produces a `$0.0350` platform fee and `$0.9650` developer net after the hold period.

The demo does not calculate or settle money itself. It only creates the request and verifies its status.

## 6. Storage

The demo uses JSON files so the implementation is easy to read:

- `storage/users.json`
- `storage/orders.json`
- `storage/subscriptions.json`

The storage directory is blocked from direct web access by `.htaccess`.

For production, replace JSON storage with a database and add:

- signed callbacks/webhooks
- idempotent order persistence
- renewal/cancellation logic
- retry handling
- audit logging
- stronger session/session-store configuration
- operational monitoring

## 7. Security rules

Never put these in browser JavaScript:

- `ZIVIDAX_CLIENT_SECRET`
- `zx_live_...` API key

Keep both in server-side PHP configuration only.

Do not copy real credentials into GitHub.

This repository intentionally ships with placeholders.

## 8. Troubleshooting

### `Could not exchange the Zividax code`

Check:

1. `ZIVIDAX_CLIENT_ID`
2. `ZIVIDAX_CLIENT_SECRET`
3. The registered callback URL
4. `APP_URL`
5. PHP cURL is enabled

The callback URL in Console must match:

```text
APP_URL/callback.php
```

### `Zividax Pay could not create the payment request`

Check:

1. The API key begins with `zx_live_`
2. The API key is active
3. The server can make HTTPS requests to `console.zividax.uk`
4. The developer account behind the key is permitted to create payment requests

`subscribe.php` logs the API response with `error_log()` to help diagnose HTTP errors without exposing the API key to the browser.

## 9. Learn the code in this order

1. `config.php`
2. `login.php`
3. `callback.php`
4. `dashboard.php`
5. `subscribe.php`
6. `payment-complete.php`

That sequence follows the same order a real integration uses: configure → authenticate → create payment → verify payment → grant service access.

## License

MIT. See `LICENSE`.

## Disclaimer

This is an integration demo, not a complete production billing platform. Review the current Zividax Console and Zividax Pay API documentation before deploying a real commercial integration.
