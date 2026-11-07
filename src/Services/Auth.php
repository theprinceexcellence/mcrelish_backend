<?php
declare(strict_types=1);
namespace McRelish\Services;

use McRelish\Core\{Database, HttpException, Request};

/** Session handling. The browser holds a random token; only its sha256 is stored. */
final class Auth
{
    private static bool $loaded = false;
    private static ?array $user = null;

    public static function issueSession(string $userId): string
    {
        Database::run('DELETE FROM sessions WHERE expires_at < UTC_TIMESTAMP()');
        $token = bin2hex(random_bytes(32));
        Database::run('INSERT INTO sessions (token_hash, user_id, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))', [hash('sha256', $token), $userId]);
        self::$loaded = false;
        return $token;
    }

    public static function destroyCurrent(): void
    {
        $token = Request::token();
        if ($token !== '') Database::run('DELETE FROM sessions WHERE token_hash = ?', [hash('sha256', $token)]);
        self::$loaded = false;
        self::$user = null;
    }

    /** The signed-in user (with roles), or null for guests and invalid or expired tokens. */
    public static function user(): ?array
    {
        if (self::$loaded) return self::$user;
        self::$loaded = true;
        self::$user = null;
        $token = Request::token();
        if ($token === '') return null;
        $row = Database::one(
            'SELECT u.id, u.email, u.full_name, u.phone FROM sessions s JOIN users u ON u.id = s.user_id WHERE s.token_hash = ? AND s.expires_at > UTC_TIMESTAMP()',
            [hash('sha256', $token)]
        );
        if ($row) {
            $row['roles'] = self::rolesFor($row['id']);
            self::$user = $row;
        }
        return self::$user;
    }

    public static function require(): array
    {
        return self::user() ?? throw new HttpException('Please sign in.', 401);
    }

    /** @param string ...$roles any one of these roles is enough */
    public static function requireRole(string ...$roles): array
    {
        $user = self::require();
        if (!array_intersect($roles, $user['roles'])) throw new HttpException('You do not have access to this.', 403);
        return $user;
    }

    /** @return string[] */
    public static function rolesFor(string $userId): array
    {
        return array_column(Database::all('SELECT role FROM user_roles WHERE user_id = ? ORDER BY role', [$userId]), 'role');
    }

    public static function present(array $user): array
    {
        return [
            'id' => $user['id'], 'email' => $user['email'],
            'full_name' => $user['full_name'], 'phone' => $user['phone'],
            'roles' => array_values($user['roles'] ?? self::rolesFor($user['id'])),
        ];
    }
}
