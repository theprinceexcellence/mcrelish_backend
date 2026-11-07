<?php
declare(strict_types=1);
namespace McRelish\Controllers;

use McRelish\Core\{HttpException, Request, Response, Validate};
use McRelish\Repositories\MenuRepository;
use McRelish\Services\Auth;

final class MenuController
{
    public function index(): void
    {
        Response::json((new MenuRepository())->publicMenu());
    }

    public function adminIndex(): void
    {
        Auth::requireRole('admin', 'staff');
        Response::json((new MenuRepository())->adminMenu());
    }

    public function saveSection(): void
    {
        Auth::requireRole('admin');
        $in = Request::json();
        $section = (new MenuRepository())->upsertSection([
            'id' => Validate::id($in['id'] ?? null),
            'name' => Validate::str($in['name'] ?? null, 1, 100, 'Enter a name (up to 100 characters).'),
            'subtitle' => Validate::optStr($in['subtitle'] ?? '', 300, 'The section text is too long.'),
            'image_path' => Validate::imagePath($in['image_path'] ?? ''),
            'sort_order' => Validate::int($in['sort_order'] ?? 0, 0, 100000, 'Invalid order.'),
            'visible' => Validate::bool($in['visible'] ?? true, 'Invalid visibility.'),
        ]);
        Response::json($section);
    }

    public function saveItem(): void
    {
        Auth::requireRole('admin');
        $in = Request::json();
        $repo = new MenuRepository();
        $sectionId = Validate::id($in['section_id'] ?? null);
        if (!$repo->sectionExists($sectionId)) throw new HttpException('Choose a section for this item.', 422);
        $item = $repo->upsertItem([
            'id' => Validate::id($in['id'] ?? null),
            'section_id' => $sectionId,
            'name' => Validate::str($in['name'] ?? null, 1, 100, 'Enter a name (up to 100 characters).'),
            'description' => Validate::optStr($in['description'] ?? '', 1000, 'The description is too long.'),
            'image_path' => Validate::imagePath($in['image_path'] ?? ''),
            'price' => Validate::int($in['price'] ?? null, 0, 100000000, 'Enter a whole, non-negative price in naira.'),
            'sort_order' => Validate::int($in['sort_order'] ?? 0, 0, 100000, 'Invalid order.'),
            'visible' => Validate::bool($in['visible'] ?? true, 'Invalid visibility.'),
            'available' => Validate::bool($in['available'] ?? true, 'Invalid availability.'),
        ]);
        Response::json($item);
    }

    public function reorder(): void
    {
        Auth::requireRole('admin');
        $in = Request::json();
        $kind = $in['kind'] ?? '';
        if (!in_array($kind, ['section', 'item'], true) || !is_array($in['ids'] ?? null) || count($in['ids']) > 500) throw new HttpException('Could not reorder the menu.', 422);
        (new MenuRepository())->reorder($kind, array_map(fn ($id) => Validate::id($id), $in['ids']));
        Response::json(['ok' => true]);
    }

    public function updatePrice(): void
    {
        Auth::requireRole('admin');
        $in = Request::json();
        $patch = [];
        if (array_key_exists('price', $in)) $patch['price'] = Validate::int($in['price'], 0, 100000000, 'Enter a valid, non-negative price');
        if (array_key_exists('available', $in)) $patch['available'] = Validate::bool($in['available'], 'Invalid availability.');
        if (array_key_exists('low_stock', $in)) $patch['low_stock'] = Validate::bool($in['low_stock'], 'Invalid stock flag.');
        $key = is_string($in['key'] ?? null) && strlen($in['key']) <= 160 ? $in['key'] : throw new HttpException('Invalid menu price.', 422);
        Response::json((new MenuRepository())->updatePrice($key, $patch));
    }
}
