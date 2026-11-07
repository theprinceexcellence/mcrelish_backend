<?php
declare(strict_types=1);
namespace McRelish\Controllers;

use McRelish\Core\{Config, Response};

final class ConfigController
{
    /** Only publishable values. Secret keys never leave the server. */
    public function publicConfig(): void
    {
        Response::json([
            'flutterwave_public_key' => (string) Config::get('flutterwave.public_key', ''),
            'currency'               => Config::get('flutterwave.currency', 'NGN'),
            'google_client_id'       => (string) Config::get('google.client_id', ''),
        ]);
    }
}
