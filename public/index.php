<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use McRelish\Core\{Router, Response, HttpException};
use McRelish\Controllers\{ConfigController, MenuController, AuthController, OrderController, PaymentController, AdminOrderController, SettingsController, StaffController};

Response::cors();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$r = new Router();
// Public
$r->get('/api/config',          [ConfigController::class, 'publicConfig']);
$r->get('/api/menu',            [MenuController::class, 'index']);
$r->get('/api/delivery-zones',  [SettingsController::class, 'zones']);
$r->get('/api/promo',           [SettingsController::class, 'promo']);
// Accounts
$r->post('/api/auth/signup',    [AuthController::class, 'signup']);
$r->post('/api/auth/login',     [AuthController::class, 'login']);
$r->post('/api/auth/logout',    [AuthController::class, 'logout']);
$r->get('/api/auth/me',         [AuthController::class, 'me']);
$r->post('/api/auth/profile',   [AuthController::class, 'profile']);
$r->post('/api/auth/forgot',    [AuthController::class, 'forgot']);
$r->post('/api/auth/reset',     [AuthController::class, 'reset']);
$r->post('/api/auth/google',    [AuthController::class, 'google']);
// Orders and payments
$r->post('/api/orders',                  [OrderController::class, 'create']);
$r->post('/api/orders/confirm',          [OrderController::class, 'confirm']);
$r->get('/api/orders/{reference}',       [OrderController::class, 'ticket']);
$r->post('/api/payments/webhook',        [PaymentController::class, 'webhook']);
// Dashboard
$r->get('/api/admin/orders',                [AdminOrderController::class, 'index']);
$r->post('/api/admin/orders/{id}/status',   [AdminOrderController::class, 'setStatus']);
$r->get('/api/admin/sales',                 [AdminOrderController::class, 'sales']);
$r->get('/api/admin/menu',                  [MenuController::class, 'adminIndex']);
$r->post('/api/admin/menu/sections',        [MenuController::class, 'saveSection']);
$r->post('/api/admin/menu/items',           [MenuController::class, 'saveItem']);
$r->post('/api/admin/menu/reorder',         [MenuController::class, 'reorder']);
$r->post('/api/admin/menu/prices',          [MenuController::class, 'updatePrice']);
$r->post('/api/admin/uploads/{kind}',       [SettingsController::class, 'upload']);
$r->get('/api/admin/delivery',              [SettingsController::class, 'adminDelivery']);
$r->post('/api/admin/store',                [SettingsController::class, 'updateStore']);
$r->post('/api/admin/zones',                [SettingsController::class, 'addZone']);
$r->patch('/api/admin/zones/{id}',          [SettingsController::class, 'updateZone']);
$r->delete('/api/admin/zones/{id}',         [SettingsController::class, 'deleteZone']);
$r->post('/api/admin/promo',                [SettingsController::class, 'updatePromo']);
$r->get('/api/admin/staff',                 [StaffController::class, 'index']);
$r->post('/api/admin/staff',                [StaffController::class, 'add']);
$r->post('/api/admin/staff/remove',         [StaffController::class, 'remove']);
$r->get('/api/admin/audit',                 [StaffController::class, 'auditList']);
$r->post('/api/admin/audit',                [StaffController::class, 'auditAdd']);

try {
    // Works from any folder (for example /mcrelish/backend/public/api/menu on XAMPP): routing starts at "/api/".
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    $at = strpos($path, '/api/');
    if ($at === false && str_ends_with($path, '/api')) $at = strlen($path) - 4;
    $r->dispatch($_SERVER['REQUEST_METHOD'], $at === false ? $path : substr($path, $at));
} catch (HttpException $e) {
    Response::json(['error' => $e->getMessage()], $e->status);
} catch (PDOException $e) {
    error_log((string) $e);
    $dev = McRelish\Core\Config::get('app.env') === 'development';
    $missing = in_array($e->getCode(), ['42S02', '42S22'], true); // table or column not found
    Response::json(['error' => $missing && $dev
        ? 'The database tables are missing or out of date. Run "php bin/install.php" in the backend folder (on a database you can reset, re-run it after dropping the old one).'
        : 'Something went wrong on the server. Please try again.'] + ($dev ? ['debug' => $e->getMessage()] : []), 500);
} catch (Throwable $e) {
    error_log((string) $e);
    $dev = McRelish\Core\Config::get('app.env') === 'development';
    Response::json(['error' => $dev ? 'Server error: ' . $e->getMessage() : 'Something went wrong on the server. Please try again.'], 500);
}
