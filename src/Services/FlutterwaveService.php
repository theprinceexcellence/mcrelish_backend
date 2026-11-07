<?php
declare(strict_types=1);
namespace McRelish\Services;

use McRelish\Core\{Config, HttpException};

final class FlutterwaveService
{
    private function call(string $method, string $path, ?array $body = null): array
    {
        $secret = (string) Config::get('flutterwave.secret_key', '');
        if ($secret === '') throw new HttpException('Online payment is not set up yet. Please call McRelish to order.', 503);
        $ch = curl_init(rtrim((string) Config::get('flutterwave.base_url', 'https://api.flutterwave.com/v3'), '/') . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_SSL_VERIFYPEER => (bool) Config::get('flutterwave.verify_ssl', true),
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $secret, 'Content-Type: application/json'],
        ];
        $bundle = (string) Config::get('flutterwave.ca_bundle', '');
        if ($bundle !== '') $opts[CURLOPT_CAINFO] = $bundle; // XAMPP on Windows often needs this, see README
        curl_setopt_array($ch, $opts);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data) || $code >= 400) {
            error_log("Flutterwave $method $path failed [$code] $err " . (is_string($raw) ? $raw : ''));
            throw new HttpException('The payment provider could not be reached. Please try again.', 502);
        }
        return $data;
    }

    public function createPaymentLink(string $txRef, int $amount, string $redirectUrl, string $reference, array $customer): string
    {
        $res = $this->call('POST', '/payments', [
            'tx_ref'       => $txRef,
            'amount'       => $amount,
            'currency'     => Config::get('flutterwave.currency', 'NGN'),
            'redirect_url' => $redirectUrl,
            'customer'     => ['email' => $customer['email'], 'phonenumber' => $customer['phone'], 'name' => $customer['name']],
            'customizations' => ['title' => 'McRelish', 'description' => 'Order ' . $reference],
            'meta'         => ['order_reference' => $reference],
        ]);
        $link = $res['data']['link'] ?? null;
        if (!is_string($link) || $link === '') throw new HttpException('No payment link was returned. Please try again.', 502);
        return $link;
    }

    public function verifyTransaction(string $transactionId): array
    {
        $res = $this->call('GET', '/transactions/' . rawurlencode($transactionId) . '/verify');
        return is_array($res['data'] ?? null) ? $res['data'] : [];
    }

    public function isValidWebhook(?string $signature): bool
    {
        $expected = (string) Config::get('flutterwave.webhook_hash', '');
        return $expected !== '' && $signature !== null && hash_equals($expected, $signature);
    }
}
