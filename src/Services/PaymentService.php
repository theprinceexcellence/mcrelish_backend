<?php
declare(strict_types=1);
namespace McRelish\Services;

use McRelish\Core\Database;

final class PaymentService
{
    /**
     * Re-verifies a transaction with Flutterwave and marks the matching order paid
     * only when status, currency, amount and reference all match.
     * @return array{reference: string, status: string}|null
     */
    public static function settle(string $transactionId): ?array
    {
        $tx = (new FlutterwaveService())->verifyTransaction($transactionId);
        $txRef = (string) ($tx['tx_ref'] ?? '');
        if ($txRef === '') return null;
        $order = Database::one('SELECT id, total, payment_status, reference FROM orders WHERE tx_ref = ?', [$txRef]);
        if (!$order) return null;
        if ($order['payment_status'] === 'paid') return ['reference' => $order['reference'], 'status' => 'paid'];

        $ok = ($tx['status'] ?? '') === 'successful'
            && ($tx['currency'] ?? '') === 'NGN'
            && is_numeric($tx['amount'] ?? null)
            // A successful transaction must match the server-calculated order total exactly.
            // Accepting an overpayment here can settle an order against a transaction for a different amount.
            && round((float) $tx['amount'], 2) === round((float) $order['total'], 2);
        $status = $ok ? 'paid' : (($tx['status'] ?? '') === 'failed' ? 'failed' : 'pending');
        if ($status !== 'pending') {
            Database::run(
                'UPDATE orders SET payment_status = ?, flw_transaction_id = ?, paid_at = ' . ($ok ? 'UTC_TIMESTAMP()' : 'paid_at') . ' WHERE id = ? AND payment_status <> \'paid\'',
                [$status, (string) ($tx['id'] ?? $transactionId), $order['id']]
            );
        }
        return ['reference' => $order['reference'], 'status' => $status];
    }
}
