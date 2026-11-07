<?php
declare(strict_types=1);
namespace McRelish\Controllers;

use McRelish\Core\{HttpException, Request, Response, Validate};
use McRelish\Repositories\OrderRepository;
use McRelish\Services\Auth;

final class AdminOrderController
{
    public function index(): void
    {
        Auth::requireRole('admin', 'staff', 'dispatcher');
        $limit = Validate::int(Request::query('limit', '200'), 1, 500, 'Invalid limit.');
        Response::json(['orders' => (new OrderRepository())->recent($limit)]);
    }

    public function setStatus(string $id): void
    {
        Auth::requireRole('admin', 'staff', 'dispatcher');
        $status = Request::json()['status'] ?? '';
        if (!in_array($status, ['new', 'preparing', 'ready', 'completed', 'cancelled'], true)) throw new HttpException('Invalid status.', 422);
        if (!(new OrderRepository())->setStatus(Validate::uuid($id, 'Order not found.'), $status)) throw new HttpException('Order not found.', 404);
        Response::json(['ok' => true, 'status' => $status]);
    }

    public function sales(): void
    {
        Auth::requireRole('admin');
        Response::json(['orders' => (new OrderRepository())->sales(1000)]);
    }
}
