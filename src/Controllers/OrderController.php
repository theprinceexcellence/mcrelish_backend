<?php
declare(strict_types=1);
namespace McRelish\Controllers;

use McRelish\Core\{Config, Database, HttpException, Request, Response, Uuid, Validate};
use McRelish\Repositories\{OrderRepository, SettingsRepository};
use McRelish\Services\{Auth, FlutterwaveService, PaymentService, PricingService};

final class OrderController
{
    /** Development only: pay without Flutterwave so the whole flow can be tried locally. */
    public static function simulating(): bool
    {
        return Config::get('app.env') === 'development' && (bool) Config::get('flutterwave.simulate_payments', false);
    }

    public function create(): void
    {
        $in = Request::json();
        $lines = $in['items'] ?? null;
        if (!is_array($lines) || count($lines) < 1 || count($lines) > 30) throw new HttpException('Your cart is empty.', 422);
        $method = $in['method'] ?? '';
        if (!in_array($method, ['pickup', 'delivery'], true)) throw new HttpException('Choose pickup or delivery.', 422);
        $c = is_array($in['customer'] ?? null) ? $in['customer'] : [];
        $name = Validate::str($c['name'] ?? null, 2, 100, 'Enter your full name.');
        $phone = Validate::str($c['phone'] ?? null, 1, 40, 'Enter a valid phone number.');
        if (!preg_match('/^\+?[\d\s-]{10,20}$/', $phone)) throw new HttpException('Enter a valid phone number.', 422);
        $email = Validate::email($c['email'] ?? null);
        $preferredAt = Validate::optStr($in['preferredAt'] ?? '', 40, 'Choose a valid time.');
        $address = Validate::optStr($in['address'] ?? '', 400, 'That address is too long.');
        $hub = Validate::optStr($in['hub'] ?? '', 80, 'Choose a pickup food hub.');
        $notes = Validate::optStr($in['notes'] ?? '', 500, 'Your notes are too long (500 characters at most).');
        $zoneId = ($in['zoneId'] ?? null) === null || $in['zoneId'] === '' ? null : Validate::uuid($in['zoneId'], 'Choose your delivery area.');
        $origin = Validate::origin($in['origin'] ?? null);

        $settings = new SettingsRepository();
        $store = $settings->store();
        if (!$store['store_open']) {
            throw new HttpException(trim($store['note']) !== '' ? trim($store['note']) : "We're not taking online orders right now. Please try again later or call us.", 503);
        }
        if (!self::simulating() && (string) Config::get('flutterwave.secret_key', '') === '') {
            throw new HttpException('Online payment is not set up yet. Please call McRelish to order.', 503);
        }

        $priced = (new PricingService())->priceOrder($lines);

        $deliveryFee = 0;
        $zoneName = null;
        if ($method === 'delivery') {
            if ($zoneId === null || mb_strlen($address) < 5) throw new HttpException('Choose your delivery area and enter your address.', 422);
            $zone = $settings->zone($zoneId);
            if (!$zone || !$zone['active']) throw new HttpException('That delivery area is not available. Please choose another.', 422);
            $deliveryFee = $zone['fee'];
            $zoneName = $zone['name'];
        } elseif (!in_array($hub, Config::get('hubs', []), true)) {
            throw new HttpException('Choose a pickup food hub.', 422);
        }

        $reference = 'MCR-' . strtoupper(base_convert((string) (int) (microtime(true) * 1000), 10, 36)) . '-' . strtoupper(bin2hex(random_bytes(2)));
        $txRef = $reference . '-' . bin2hex(random_bytes(3));
        $total = $priced['subtotal'] + $deliveryFee;
        $token = Uuid::v4();
        (new OrderRepository())->insert([
            'id' => Uuid::v4(), 'reference' => $reference, 'access_token' => $token, 'user_id' => Auth::user()['id'] ?? null,
            'customer_name' => $name, 'customer_phone' => $phone, 'customer_email' => $email, 'method' => $method,
            'hub' => $method === 'pickup' ? $hub : null, 'zone_name' => $zoneName, 'address' => $method === 'delivery' ? $address : null,
            'preferred_at' => $preferredAt, 'notes' => $notes !== '' ? $notes : null,
            'items' => json_encode($priced['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'subtotal' => $priced['subtotal'], 'delivery_fee' => $deliveryFee, 'total' => $total, 'tx_ref' => $txRef,
        ]);

        $redirect = $origin . '/payment/callback?ref=' . rawurlencode($reference);
        $link = self::simulating()
            ? $redirect . '&status=successful&transaction_id=SIM-' . $reference
            : (new FlutterwaveService())->createPaymentLink($txRef, $total, $redirect, $reference, ['email' => $email, 'phone' => $phone, 'name' => $name]);
        Response::json(['reference' => $reference, 'token' => $token, 'link' => $link, 'total' => $total], 201);
    }

    public function confirm(): void
    {
        $in = Request::json();
        $reference = Validate::str($in['reference'] ?? null, 1, 60, 'We could not find that order.');
        $token = Validate::uuid($in['token'] ?? null, 'We could not find that order.');
        $order = (new OrderRepository())->byReferenceAndToken($reference, $token) ?? throw new HttpException('We could not find that order.', 404);
        $txId = $in['transactionId'] ?? null;
        if ($order['payment_status'] === 'paid' || $txId === null || $txId === '') {
            Response::json(['status' => $order['payment_status']]);
            return;
        }
        if (self::simulating() && $txId === 'SIM-' . $reference) {
            Database::run("UPDATE orders SET payment_status = 'paid', flw_transaction_id = ?, paid_at = UTC_TIMESTAMP() WHERE id = ? AND payment_status <> 'paid'", [$txId, $order['id']]);
            Response::json(['status' => 'paid']);
            return;
        }
        if (!is_string($txId) && !is_int($txId) || !preg_match('/^\d{1,20}$/', (string) $txId)) throw new HttpException('We could not verify that payment.', 422);
        $result = PaymentService::settle((string) $txId);
        if ($result && $result['reference'] !== $reference) throw new HttpException('This payment belongs to a different order.', 409);
        Response::json(['status' => $result['status'] ?? 'pending']);
    }

    public function ticket(string $reference): void
    {
        $token = Validate::uuid(Request::query('token'), 'We could not find that ticket.');
        $order = (new OrderRepository())->byReferenceAndToken($reference, $token) ?? throw new HttpException('We could not find that ticket.', 404);
        Response::json(OrderRepository::ticket($order));
    }
}
