<?php
// Checks the setup and says what to fix.   php bin/check.php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit('Run this from the command line.');
$ok = true;
function line(bool $good, string $text, string $fix = ''): void
{
    global $ok;
    if (!$good) $ok = false;
    echo ($good ? '  ok    ' : '  FIX   ') . $text . ($good || $fix === '' ? '' : "\n          -> $fix") . "\n";
}

line(PHP_VERSION_ID >= 80100, 'PHP ' . PHP_VERSION . ' (8.1 or newer needed)', 'Use a newer XAMPP, or run the API with the php.exe from one.');
foreach (['pdo_mysql', 'curl', 'mbstring', 'fileinfo', 'openssl'] as $ext) {
    line(extension_loaded($ext), "PHP extension $ext", "Open php.ini (XAMPP control panel, Apache, Config) and remove the ';' before extension=$ext, then restart.");
}
$configFile = dirname(__DIR__) . '/config/config.php';
line(is_file($configFile), 'config/config.php exists', 'Copy config/config.example.php to config/config.php.');
if (!is_file($configFile)) exit(1);

require dirname(__DIR__) . '/src/bootstrap.php';
use McRelish\Core\{Config, Database, HttpException};

try {
    $pdo = Database::pdo();
    line(true, 'Connected to database "' . Config::get('db.name') . '"');
    $have = array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0);
    $need = ['users', 'sessions', 'password_resets', 'login_attempts', 'user_roles', 'menu_sections', 'menu_items', 'menu_prices', 'delivery_zones', 'store_settings', 'hero_promo', 'orders', 'audit_log'];
    $missing = array_diff($need, $have);
    line(!$missing, 'All tables exist' . ($missing ? ' (missing: ' . implode(', ', $missing) . ')' : ''), 'Run: php bin/install.php');
    if (!$missing) {
        line((int) $pdo->query('SELECT COUNT(*) FROM menu_items')->fetchColumn() > 0, 'The menu has items', 'Run: php bin/install.php (it loads the starting menu into an empty menu).');
        line((int) $pdo->query("SELECT COUNT(*) FROM user_roles WHERE role = 'admin'")->fetchColumn() > 0, 'An admin account exists', 'Run: php bin/create-admin.php you@example.com "a-strong-password"');
    }
} catch (HttpException $e) {
    line(false, 'Database connection', $e->getMessage());
}
foreach (['public/uploads/menu', 'public/uploads/promo', 'storage'] as $dir) {
    $path = dirname(__DIR__) . '/' . $dir;
    line(is_dir($path) && is_writable($path), "$dir is writable", 'Make sure the folder exists and your user can write to it.');
}
line(Config::get('flutterwave.secret_key', '') !== '' || Config::get('flutterwave.simulate_payments', false), 'Flutterwave secret key set (or simulate_payments on)', 'Without it, checkout says online payment is not set up. Add your keys in config.php.');
echo $ok ? "\nAll good.\n" : "\nFix the items marked FIX, then run this again.\n";
exit($ok ? 0 : 1);
