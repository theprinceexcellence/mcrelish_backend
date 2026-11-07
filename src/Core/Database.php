<?php
declare(strict_types=1);
namespace McRelish\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get('db');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);
            try {
                self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]);
            } catch (\PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                throw new HttpException(Config::get('app.env') === 'development'
                    ? 'Cannot connect to the database: ' . rtrim($e->getMessage(), '.') . '. Check that MySQL is running in XAMPP and that the db settings in backend/config/config.php are right (database "' . $c['name'] . '" is created by: php bin/install.php).'
                    : 'The service is temporarily unavailable. Please try again shortly.', 503);
            }
            // All timestamps are stored and compared in UTC; the API returns ISO-8601 with a Z suffix.
            self::$pdo->exec("SET time_zone = '+00:00'");
        }
        return self::$pdo;
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($params);
        return $s->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($params);
        $row = $s->fetch();
        return $row === false ? null : $row;
    }

    public static function run(string $sql, array $params = []): int
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($params);
        return $s->rowCount();
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try { $r = $fn($pdo); $pdo->commit(); return $r; }
        catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
}
