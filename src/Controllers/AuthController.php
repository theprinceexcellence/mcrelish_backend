<?php
declare(strict_types=1);
namespace McRelish\Controllers;

use McRelish\Core\{Config, Database, HttpException, Request, Response, Uuid, Validate};
use McRelish\Services\{Auth, MailService};

final class AuthController
{
    private function session(string $userId, int $status = 200): void
    {
        $user = Database::one('SELECT id, email, full_name, phone FROM users WHERE id = ?', [$userId]);
        Response::json(['token' => Auth::issueSession($userId), 'user' => Auth::present($user)], $status);
    }

    private function password(mixed $v): string
    {
        if (!is_string($v) || strlen($v) < 8) throw new HttpException('Use a password of at least 8 characters.', 422);
        if (strlen($v) > 72) throw new HttpException('Use a password of up to 72 characters.', 422);
        return $v;
    }

    /** Allows 8 attempts per 15 minutes for one email and address. */
    private function throttle(string $bucket, int $max, string $window, string $message): void
    {
        $hash = hash('sha256', $bucket);
        $n = Database::one("SELECT COUNT(*) AS n FROM login_attempts WHERE bucket = ? AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL $window)", [$hash]);
        if ((int) $n['n'] >= $max) throw new HttpException($message, 429);
    }

    private function recordAttempt(string $bucket): void
    {
        Database::run('INSERT INTO login_attempts (bucket) VALUES (?)', [hash('sha256', $bucket)]);
        Database::run('DELETE FROM login_attempts WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');
    }

    public function signup(): void
    {
        $in = Request::json();
        $email = Validate::email($in['email'] ?? null);
        $password = $this->password($in['password'] ?? null);
        $name = Validate::optStr($in['full_name'] ?? '', 160, 'That name is too long.');
        if (Database::one('SELECT 1 AS x FROM users WHERE email = ?', [$email])) throw new HttpException('An account with this email already exists.', 409);
        $id = Uuid::v4();
        Database::run('INSERT INTO users (id, email, password_hash, full_name) VALUES (?, ?, ?, ?)', [$id, $email, password_hash($password, PASSWORD_DEFAULT), $name]);
        $this->session($id, 201);
    }

    public function login(): void
    {
        $in = Request::json();
        $email = Validate::email($in['email'] ?? null, 'Enter your email and password.');
        $password = is_string($in['password'] ?? null) ? $in['password'] : '';
        $bucket = 'login|' . $email . '|' . Request::ip();
        $this->throttle($bucket, 8, '15 MINUTE', 'Too many attempts. Please wait a few minutes and try again.');
        $user = Database::one('SELECT id, password_hash FROM users WHERE email = ?', [$email]);
        $hash = $user['password_hash'] ?? null;
        // Do comparable work whether or not the account exists, so response time does not reveal it.
        if (is_string($hash) && $hash !== '') {
            $valid = password_verify($password, $hash);
        } else {
            password_hash($password, PASSWORD_DEFAULT);
            $valid = false;
        }
        if (!$user || !$valid) {
            $this->recordAttempt($bucket);
            throw new HttpException('Unable to sign in. Check your email and password.', 401);
        }
        Database::run('DELETE FROM login_attempts WHERE bucket = ?', [hash('sha256', $bucket)]);
        $this->session($user['id']);
    }

    public function logout(): void
    {
        Auth::destroyCurrent();
        Response::json(['ok' => true]);
    }

    public function me(): void
    {
        Response::json(['user' => Auth::present(Auth::require())]);
    }

    public function profile(): void
    {
        $user = Auth::require();
        $in = Request::json();
        $name = Validate::str($in['full_name'] ?? null, 1, 160, 'Enter your full name.');
        $phone = Validate::optStr($in['phone'] ?? '', 40, 'That phone number is too long.');
        Database::run('UPDATE users SET full_name = ?, phone = ? WHERE id = ?', [$name, $phone, $user['id']]);
        $fresh = Database::one('SELECT id, email, full_name, phone FROM users WHERE id = ?', [$user['id']]);
        Response::json(['user' => Auth::present($fresh)]);
    }

    public function forgot(): void
    {
        $in = Request::json();
        $email = Validate::email($in['email'] ?? null);
        $origin = isset($in['origin']) ? Validate::origin($in['origin']) : rtrim((string) Config::get('app.frontend_url', ''), '/');
        $bucket = 'forgot|' . Request::ip();
        $this->throttle($bucket, 5, '1 HOUR', 'Too many reset requests. Please try again later.');
        $this->recordAttempt($bucket);

        $devLink = null;
        $user = Database::one('SELECT id FROM users WHERE email = ?', [$email]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            Database::run('INSERT INTO password_resets (token_hash, user_id, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR))', [hash('sha256', $token), $user['id']]);
            $link = $origin . '/reset-password?token=' . $token;
            $text = "Hello,\n\nUse this link to choose a new McRelish password. It works for one hour:\n$link\n\nIf you did not ask for this, you can ignore this email.";
            $html = '<p>Hello,</p><p>Use this link to choose a new McRelish password. It works for one hour:</p><p><a href="' . htmlspecialchars($link, ENT_QUOTES) . '">Choose a new password</a></p><p>If you did not ask for this, you can ignore this email.</p>';
            MailService::send($email, 'Reset your McRelish password', $html, $text);
            if (Config::get('app.env') === 'development') $devLink = $link;
        }
        // Same answer whether or not the email has an account.
        Response::json(['ok' => true] + ($devLink ? ['dev_link' => $devLink] : []));
    }

    public function reset(): void
    {
        $in = Request::json();
        $token = is_string($in['token'] ?? null) ? $in['token'] : '';
        $password = $this->password($in['password'] ?? null);
        $row = $token === '' ? null : Database::one(
            'SELECT user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            [hash('sha256', $token)]
        );
        if (!$row) throw new HttpException('This reset link may have expired. Please request a new one.', 400);
        Database::transaction(function () use ($row, $token, $password) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['user_id']]);
            Database::run('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE token_hash = ?', [hash('sha256', $token)]);
            Database::run('DELETE FROM sessions WHERE user_id = ?', [$row['user_id']]);
        });
        $this->session($row['user_id']);
    }

    /** "Continue with Google": verifies a Google Identity Services ID token. */
    public function google(): void
    {
        $clientId = (string) Config::get('google.client_id', '');
        if ($clientId === '') throw new HttpException('Google sign-in is not set up.', 503);
        $credential = Request::json()['credential'] ?? null;
        if (!is_string($credential) || $credential === '' || strlen($credential) > 4096) throw new HttpException('Sign-in could not be completed.', 422);

        $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => (bool) Config::get('flutterwave.verify_ssl', true)]);
        $bundle = (string) Config::get('flutterwave.ca_bundle', '');
        if ($bundle !== '') curl_setopt($ch, CURLOPT_CAINFO, $bundle);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $claims = is_string($raw) ? json_decode($raw, true) : null;
        if ($code !== 200 || !is_array($claims)
            || ($claims['aud'] ?? '') !== $clientId
            || !in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
            || ($claims['email_verified'] ?? '') !== 'true' && ($claims['email_verified'] ?? false) !== true
            || (int) ($claims['exp'] ?? 0) < time()
            || empty($claims['sub']) || empty($claims['email'])) {
            throw new HttpException('Sign-in could not be completed.', 401);
        }
        $email = strtolower((string) $claims['email']);
        $sub = (string) $claims['sub'];
        $user = Database::one('SELECT id FROM users WHERE google_sub = ?', [$sub]) ?? Database::one('SELECT id FROM users WHERE email = ?', [$email]);
        if ($user) {
            Database::run('UPDATE users SET google_sub = COALESCE(google_sub, ?) WHERE id = ?', [$sub, $user['id']]);
            $id = $user['id'];
        } else {
            $id = Uuid::v4();
            Database::run('INSERT INTO users (id, email, password_hash, full_name, google_sub) VALUES (?, ?, NULL, ?, ?)', [$id, $email, mb_substr((string) ($claims['name'] ?? ''), 0, 160), $sub]);
        }
        $this->session($id);
    }
}
