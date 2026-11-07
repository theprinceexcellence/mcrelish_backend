<?php
declare(strict_types=1);
namespace McRelish\Core;

/** Small input checks that throw a 422 with a customer-friendly message. */
final class Validate
{
    public static function str(mixed $v, int $min, int $max, string $message): string
    {
        if (!is_string($v)) throw new HttpException($message, 422);
        $v = trim($v);
        $len = mb_strlen($v);
        if ($len < $min || $len > $max) throw new HttpException($message, 422);
        return $v;
    }

    public static function optStr(mixed $v, int $max, string $message): string
    {
        if ($v === null || $v === '') return '';
        return self::str($v, 0, $max, $message);
    }

    public static function int(mixed $v, int $min, int $max, string $message): int
    {
        if (is_string($v) && preg_match('/^-?\d+$/', $v)) $v = (int) $v;
        if (!is_int($v) && !(is_float($v) && floor($v) === $v)) throw new HttpException($message, 422);
        $v = (int) $v;
        if ($v < $min || $v > $max) throw new HttpException($message, 422);
        return $v;
    }

    public static function bool(mixed $v, string $message): bool
    {
        if (!is_bool($v)) throw new HttpException($message, 422);
        return $v;
    }

    public static function email(mixed $v, string $message = 'Enter a valid email address.'): string
    {
        $v = strtolower(self::str($v, 3, 190, $message));
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) throw new HttpException($message, 422);
        return $v;
    }

    public static function id(mixed $v, string $message = 'Invalid id.'): string
    {
        if (!is_string($v) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $v)) throw new HttpException($message, 422);
        return $v;
    }

    public static function uuid(mixed $v, string $message = 'Invalid id.'): string
    {
        if (!is_string($v) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v)) throw new HttpException($message, 422);
        return strtolower($v);
    }

    /** A file name produced by UploadService (or empty). */
    public static function imagePath(mixed $v): string
    {
        if ($v === null || $v === '') return '';
        if (!is_string($v) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,110}$/', $v)) throw new HttpException('Invalid image.', 422);
        return $v;
    }

    /** The website origin must be one this API is configured to serve. */
    public static function origin(mixed $v): string
    {
        $origin = is_string($v) ? rtrim(trim($v), '/') : '';
        if ($origin === '' || !in_array($origin, Config::get('app.allowed_origins', []), true)) {
            $hint = Config::get('app.env') === 'development'
                ? "This website address ($origin) is not allowed by the server. Add it to allowed_origins in backend/config/config.php."
                : 'Invalid site.';
            throw new HttpException($hint, 422);
        }
        return $origin;
    }
}
