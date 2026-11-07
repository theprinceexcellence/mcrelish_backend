<?php
// Copy to config/config.php and fill in real values. Never commit config.php.
return [
    'app' => [
        'env'      => 'development',   // development | production (development shows PHP errors and password-reset links)
        'timezone' => 'Africa/Lagos',
        // Every website address that may call this API (browser CORS) and may be used in payment/reset links.
        'allowed_origins' => ['http://localhost:5173', 'http://127.0.0.1:5173', 'http://localhost:3000'],
        'frontend_url'    => 'http://localhost:5173', // used in emails when no origin is supplied
    ],
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'mcrelish',
        'user'    => 'root',          // XAMPP default
        'pass'    => '',              // XAMPP default
        'charset' => 'utf8mb4',
    ],
    // Pickup locations accepted at checkout (must match the names shown on the website).
    'hubs' => ['Awka Food Hub', 'Aroma Food Hub'],
    'flutterwave' => [
        'public_key'   => '',   // FLWPUBK-...
        'secret_key'   => '',   // FLWSECK-...  (server only)
        'webhook_hash' => '',   // same value as "Secret hash" in the Flutterwave dashboard
        'base_url'     => 'https://api.flutterwave.com/v3',
        'currency'     => 'NGN',
        // Local testing only: with app.env = development, "pay" without Flutterwave so the whole flow can be tried.
        'simulate_payments' => false,
        // XAMPP on Windows sometimes cannot verify Flutterwave's certificate. Prefer pointing ca_bundle at a
        // cacert.pem file (https://curl.se/ca/cacert.pem). verify_ssl => false is a last resort for local testing only.
        'verify_ssl' => true,
        'ca_bundle'  => '',
    ],
    // Password-reset emails. Leave host empty to write emails to storage/mail.log instead of sending.
    'mail' => [
        'host'       => '',            // e.g. smtp.gmail.com
        'port'       => 587,
        'username'   => '',
        'password'   => '',
        'encryption' => 'tls',         // tls | ssl | ''
        'from_email' => 'no-reply@mcrelish.com',
        'from_name'  => 'McRelish',
    ],
    // Optional "Continue with Google". Paste a Google OAuth Web client ID, or leave empty to hide the button.
    'google' => ['client_id' => ''],
];
