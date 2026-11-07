<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'McRelish\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require $file;
});

$configFile = dirname(__DIR__) . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'Missing config/config.php. Copy config/config.example.php to config/config.php.']));
}
McRelish\Core\Config::load(require $configFile);
date_default_timezone_set(McRelish\Core\Config::get('app.timezone', 'Africa/Lagos'));
ini_set('display_errors', McRelish\Core\Config::get('app.env') === 'development' ? '1' : '0');
