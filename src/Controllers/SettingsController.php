<?php
declare(strict_types=1);
namespace McRelish\Controllers;

use McRelish\Core\{HttpException, Request, Response, Validate};
use McRelish\Repositories\SettingsRepository;
use McRelish\Services\{Auth, UploadService};

final class SettingsController
{
    public function zones(): void
    {
        $zones = (new SettingsRepository())->zones(true);
        Response::json(['zones' => array_map(fn ($z) => ['id' => $z['id'], 'name' => $z['name'], 'fee' => $z['fee']], $zones)]);
    }

    public function promo(): void
    {
        Response::json((new SettingsRepository())->promo());
    }

    public function adminDelivery(): void
    {
        Auth::requireRole('admin');
        $repo = new SettingsRepository();
        Response::json(['store' => $repo->store(), 'zones' => $repo->zones(false)]);
    }

    public function updateStore(): void
    {
        Auth::requireRole('admin');
        $in = Request::json();
        $patch = [];
        if (array_key_exists('store_open', $in)) $patch['store_open'] = Validate::bool($in['store_open'], 'Invalid store status.');
        if (array_key_exists('note', $in)) $patch['note'] = Validate::optStr($in['note'], 500, 'The note is too long (500 characters at most).');
        Response::json((new SettingsRepository())->updateStore($patch));
    }

    public function addZone(): void
    {
        Auth::requireRole('admin');
        $in = Request::json();
        $name = Validate::str($in['name'] ?? null, 1, 120, 'Enter an area name.');
        $fee = Validate::int($in['fee'] ?? null, 0, 10000000, 'Enter a valid, non-negative fee.');
        Response::json((new SettingsRepository())->addZone($name, $fee), 201);
    }

    public function updateZone(string $id): void
    {
        Auth::requireRole('admin');
        $in = Request::json();
        $patch = [];
        if (array_key_exists('fee', $in)) $patch['fee'] = Validate::int($in['fee'], 0, 10000000, 'Enter a valid, non-negative fee.');
        if (array_key_exists('active', $in)) $patch['active'] = Validate::bool($in['active'], 'Invalid status.');
        Response::json((new SettingsRepository())->updateZone(Validate::uuid($id, 'Delivery area not found.'), $patch));
    }

    public function deleteZone(string $id): void
    {
        Auth::requireRole('admin');
        (new SettingsRepository())->deleteZone(Validate::uuid($id, 'Delivery area not found.'));
        Response::json(['ok' => true]);
    }

    public function updatePromo(): void
    {
        Auth::requireRole('admin');
        $in = Request::json();
        $patch = [];
        if (array_key_exists('label', $in))      $patch['label'] = Validate::optStr($in['label'], 120, 'The badge is too long.');
        if (array_key_exists('title', $in))      $patch['title'] = Validate::optStr($in['title'], 160, 'The title is too long.');
        if (array_key_exists('subtitle', $in))   $patch['subtitle'] = Validate::optStr($in['subtitle'], 400, 'The description is too long.');
        if (array_key_exists('product_id', $in)) $patch['product_id'] = Validate::optStr($in['product_id'], 64, 'Invalid menu item.');
        if (array_key_exists('active', $in))     $patch['active'] = Validate::bool($in['active'], 'Invalid status.');
        if (array_key_exists('image_url', $in))  $patch['image_url'] = Validate::imagePath($in['image_url']);
        Response::json((new SettingsRepository())->updatePromo($patch));
    }

    public function upload(string $kind): void
    {
        Auth::requireRole('admin');
        Response::json(['path' => UploadService::saveImage($kind)], 201);
    }
}
