# McRelish PHP backend

Plain PHP 8.1+ and MySQL/MariaDB (XAMPP works). No Composer: PHPMailer is included as plain files in `lib/PHPMailer`.

## Set up on XAMPP

1. Start **MySQL** in the XAMPP control panel.
2. Copy `config/config.example.php` to `config/config.php`. The defaults match a fresh XAMPP (user `root`, empty password, database `mcrelish`).
3. Open a terminal in this `backend` folder and run (use `C:\xampp\php\php.exe` if `php` is not on your PATH):
   ```
   php bin/install.php
   php bin/create-admin.php you@example.com "a-strong-password" "Your Name"
   ```
   `install.php` creates the database, the tables and the starting menu. It is safe to run again.
4. Make the API reachable. Pick one:
   - **Apache (XAMPP `htdocs`)**: copy this whole `backend` folder into `C:\xampp\htdocs\` (for example `C:\xampp\htdocs\mcrelish-api\backend`). Start Apache. The API is then at `http://localhost/mcrelish-api/backend/public`. Open `http://localhost/mcrelish-api/backend/public/api/config` in the browser; you should see a line of JSON. Use that address (without `/api/config`) as `VITE_API_URL`.
   - **Without Apache**: double-click `start-api.bat` (or run `php -S 127.0.0.1:8080 -t public router.php`). The API is at `http://127.0.0.1:8080`.
5. In the website folder, open `.env` and set `VITE_API_URL` to the API address from step 4, then run `npm install` and `npm run dev`. Restart `npm run dev` whenever you change `.env`.

### Quick check

Run `php bin/check.php` in the backend folder. It tests PHP, the database connection, the tables, the admin account and the upload folders, and tells you what to fix.

### If something shows "Not found"

- A plain grey or white Apache page saying "Object not found" means the folder path in the address is wrong. Check it against where you copied `backend`.
- `{"error":"Not found"}` means the API is running but the address after `/api/` is wrong, so check `VITE_API_URL` ends at `.../backend/public` with nothing after it.
- Apache must have `mod_rewrite` on and allow `.htaccess` (both are the XAMPP default).

## Settings in `config/config.php`

- `app.allowed_origins`: the address(es) the website runs on, for example `http://localhost:5173`. If checkout says the address "is not allowed by the server", add it here. In production list only your real site.
- `app.env`: `development` shows error details and lets you try payments without Flutterwave (`simulate_payments`). Use `production` when the site is live.
- `flutterwave`: your public key, secret key and secret hash. Set the webhook URL in the Flutterwave dashboard to `https://YOUR-API/api/payments/webhook`.
- `mail`: SMTP details for password-reset emails. While `host` is empty, emails are written to `storage/mail.log` (and in development the reset link is also printed in the browser console).
- `google.client_id`: optional "Continue with Google" button.
- `hubs`: the pickup hubs accepted at checkout.

## What is in the database

`users`, `sessions`, `password_resets`, `user_roles` (admin, staff, dispatcher), `menu_sections`, `menu_items`, `menu_prices`, `delivery_zones`, `store_settings`, `hero_promo`, `orders`, `audit_log`, `login_attempts`. Money is stored as whole naira. Uploaded photos live in `public/uploads/`.

## Checking everything works

With the API running and a Flutterwave stand-in on port 9100:

```
php -S 127.0.0.1:9100 tests/mock-flutterwave.php
php tests/smoke.php http://127.0.0.1:8080 you@example.com "your-admin-password"
```

(temporarily set `flutterwave.base_url` to `http://127.0.0.1:9100/v3`, `secret_key` to any text and `webhook_hash` to `whsecret`). It runs about 150 checks across accounts, pricing, checkout, payment, the dashboard, uploads, delivery and the store switch, and prints any failure.

```
ebukachuksc@email.com - Email
mcrelishadmin2. - Password
McRelish-Admin - Name

Production
DB Password: Njcfh7xT@e^F4N5y
DB User: mcrelish_main
```
