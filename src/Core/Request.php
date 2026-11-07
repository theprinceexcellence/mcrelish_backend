<?php
declare(strict_types=1);
namespace McRelish\Core;

final class Request
{
    private static ?string $raw = null;

    public static function raw(): string { return self::$raw ??= (string) file_get_contents('php://input'); }

    public static function json(): array
    {
        $data = json_decode(self::raw(), true);
        if (!is_array($data)) throw new HttpException('Invalid request body.', 422);
        return $data;
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }

    /** Session token from X-Auth-Token (preferred, survives Apache) or "Authorization: Bearer". */
    public static function token(): string
    {
        $t = self::header('X-Auth-Token');
        if ($t) return trim($t);
        $auth = self::header('Authorization') ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        return str_starts_with((string) $auth, 'Bearer ') ? trim(substr((string) $auth, 7)) : '';
    }

    public static function query(string $key, string $default = ''): string
    {
        return isset($_GET[$key]) && is_string($_GET[$key]) ? $_GET[$key] : $default;
    }

    public static function ip(): string { return (string) ($_SERVER['REMOTE_ADDR'] ?? ''); }
}
