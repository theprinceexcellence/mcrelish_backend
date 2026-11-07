<?php
declare(strict_types=1);
namespace McRelish\Repositories;

use McRelish\Core\{Database, Time};

final class OrderRepository
{
    public function insert(array $o): void
    {
        Database::run(
            'INSERT INTO orders (id, reference, access_token, user_id, customer_name, customer_phone, customer_email, method, hub, zone_name, address, preferred_at, notes, items, subtotal, delivery_fee, total, tx_ref)
             VALUES (:id, :reference, :access_token, :user_id, :customer_name, :customer_phone, :customer_email, :method, :hub, :zone_name, :address, :preferred_at, :notes, :items, :subtotal, :delivery_fee, :total, :tx_ref)',
            $o
        );
    }

    public function byReferenceAndToken(string $reference, string $token): ?array
    {
        return Database::one('SELECT * FROM orders WHERE reference = ? AND access_token = ?', [$reference, $token]);
    }

    public function byTxRef(string $txRef): ?array
    {
        return Database::one('SELECT * FROM orders WHERE tx_ref = ?', [$txRef]);
    }

    /** Fields the customer's ticket shows. */
    public static function ticket(array $o): array
    {
        return [
            'reference' => $o['reference'], 'customer_name' => $o['customer_name'], 'customer_phone' => $o['customer_phone'],
            'customer_email' => $o['customer_email'], 'method' => $o['method'], 'hub' => $o['hub'], 'zone_name' => $o['zone_name'],
            'address' => $o['address'], 'preferred_at' => $o['preferred_at'], 'notes' => $o['notes'],
            'items' => self::items($o['items']), 'subtotal' => (int) $o['subtotal'], 'delivery_fee' => (int) $o['delivery_fee'],
            'total' => (int) $o['total'], 'payment_status' => $o['payment_status'], 'status' => $o['status'],
            'paid_at' => Time::iso($o['paid_at']), 'created_at' => Time::iso($o['created_at']), 'flw_transaction_id' => $o['flw_transaction_id'],
        ];
    }

    private static function items(mixed $json): array
    {
        $items = json_decode((string) $json, true);
        return is_array($items) ? $items : [];
    }

    /** Rows for the Live Orders board. */
    public function recent(int $limit): array
    {
        $rows = Database::all(
            'SELECT id, reference, customer_name, customer_phone, method, hub, zone_name, address, preferred_at, items, total, payment_status, status, created_at
             FROM orders ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(500, $limit))
        );
        return array_map(fn ($o) => [
            'id' => $o['id'], 'reference' => $o['reference'], 'customer_name' => $o['customer_name'], 'customer_phone' => $o['customer_phone'],
            'method' => $o['method'], 'hub' => $o['hub'], 'zone_name' => $o['zone_name'], 'address' => $o['address'],
            'preferred_at' => $o['preferred_at'], 'items' => self::items($o['items']), 'total' => (int) $o['total'],
            'payment_status' => $o['payment_status'], 'status' => $o['status'], 'created_at' => Time::iso($o['created_at']),
        ], $rows);
    }

    /** Rows for Sales Analytics. */
    public function sales(int $limit): array
    {
        $rows = Database::all(
            'SELECT reference, items, total, payment_status, status, created_at, paid_at, method, customer_name
             FROM orders ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(2000, $limit))
        );
        return array_map(fn ($o) => [
            'reference' => $o['reference'], 'items' => self::items($o['items']), 'total' => (int) $o['total'],
            'payment_status' => $o['payment_status'], 'status' => $o['status'], 'created_at' => Time::iso($o['created_at']),
            'paid_at' => Time::iso($o['paid_at']), 'method' => $o['method'], 'customer_name' => $o['customer_name'],
        ], $rows);
    }

    public function setStatus(string $id, string $status): bool
    {
        return Database::one('SELECT 1 AS x FROM orders WHERE id = ?', [$id]) !== null
            && (Database::run('UPDATE orders SET status = ? WHERE id = ?', [$status, $id]) >= 0);
    }
}
