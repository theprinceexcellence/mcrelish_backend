<?php
declare(strict_types=1);
namespace McRelish\Controllers;

use McRelish\Core\{HttpException, Request, Response};
use McRelish\Services\{FlutterwaveService, PaymentService};

final class PaymentController
{
    public function webhook(): void
    {
        $flw = new FlutterwaveService();
        if (!$flw->isValidWebhook(Request::header('verif-hash'))) throw new HttpException('Invalid signature', 401);
        $payload = json_decode(Request::raw(), true);
        $id = (string) (is_array($payload) ? ($payload['data']['id'] ?? '') : '');
        // Never trust the webhook body alone: re-verify with Flutterwave before marking paid.
        if (preg_match('/^\d{1,20}$/', $id)) PaymentService::settle($id);
        Response::json(['ok' => true]);
    }
}
