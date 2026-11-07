<?php
// Creates the database (if needed), the tables and the starting menu.
//   php bin/install.php          (from the backend folder)
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit('Run this from the command line.');
$config = dirname(__DIR__) . '/config/config.php';
if (!is_file($config)) exit("Missing config/config.php. Copy config/config.example.php to config/config.php first.\n");
$cfg = (require $config)['db'];

try {
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $cfg['host'], $cfg['port']), $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true,
    ]);
} catch (PDOException $e) {
    exit("Could not connect to MySQL: {$e->getMessage()}\nIs MySQL running in XAMPP, and are the db settings in config.php right?\n");
}

$name = str_replace('`', '', (string) $cfg['name']);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$name`");
$pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
echo "Tables are ready in database \"$name\".\n";

$seeded = (int) $pdo->query('SELECT COUNT(*) FROM menu_sections')->fetchColumn() > 0;
if ($seeded) {
    echo "Menu already exists, so seed.sql was skipped.\n";
} else {
    $pdo->exec((string) file_get_contents(dirname(__DIR__) . '/database/seed.sql'));
    echo "Starting menu loaded.\n";
}
echo "Next: php bin/create-admin.php you@example.com \"a-strong-password\"\n";
