<?php
// For PHP's built-in server: php -S localhost:8080 -t public router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/public' . $path;
if ($path !== '/' && is_file($file) && !str_ends_with($file, '.php')) {
    if (str_starts_with($path, '/uploads/')) header('Access-Control-Allow-Origin: *');
    return false; // serve uploaded images directly
}
require __DIR__ . '/public/index.php';
