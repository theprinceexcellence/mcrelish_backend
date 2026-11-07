<?php
declare(strict_types=1);
namespace McRelish\Repositories;

use McRelish\Core\{Database, HttpException, Time};

final class MenuRepository
{
    private static function section(array $r): array
    {
        return ['id' => $r['id'], 'name' => $r['name'], 'subtitle' => $r['subtitle'], 'image_path' => $r['image_path'],
            'sort_order' => (int) $r['sort_order'], 'visible' => (bool) $r['visible']];
    }

    private static function item(array $r): array
    {
        return ['id' => $r['id'], 'section_id' => $r['section_id'], 'name' => $r['name'], 'description' => $r['description'],
            'image_path' => $r['image_path'], 'price' => (int) $r['price'], 'available' => (bool) $r['available'],
            'visible' => (bool) $r['visible'], 'sort_order' => (int) $r['sort_order']];
    }

    private static function priceRow(array $r): array
    {
        return ['key' => $r['pkey'], 'product_name' => $r['product_name'], 'label' => $r['label'], 'price' => (int) $r['price'],
            'available' => (bool) $r['available'], 'sort' => (int) $r['sort'], 'low_stock' => (bool) $r['low_stock'],
            'updated_at' => Time::iso($r['updated_at'])];
    }

    /** What customers see: visible sections and items plus the admin-editable price rows. */
    public function publicMenu(): array
    {
        return [
            'sections' => array_map([self::class, 'section'], Database::all('SELECT * FROM menu_sections WHERE visible = 1 ORDER BY sort_order, id')),
            'items' => array_map([self::class, 'item'], Database::all('SELECT i.* FROM menu_items i JOIN menu_sections s ON s.id = i.section_id WHERE i.visible = 1 AND s.visible = 1 ORDER BY i.sort_order, i.id')),
            'prices' => array_map(fn ($r) => ['key' => $r['pkey'], 'price' => (int) $r['price'], 'available' => (bool) $r['available']],
                Database::all('SELECT pkey, price, available FROM menu_prices')),
        ];
    }

    public function adminMenu(): array
    {
        return [
            'sections' => array_map([self::class, 'section'], Database::all('SELECT * FROM menu_sections ORDER BY sort_order, id')),
            'items' => array_map([self::class, 'item'], Database::all('SELECT * FROM menu_items ORDER BY sort_order, id')),
            'prices' => array_map([self::class, 'priceRow'], Database::all('SELECT * FROM menu_prices ORDER BY sort, pkey')),
        ];
    }

    public function upsertSection(array $s): array
    {
        Database::run(
            'INSERT INTO menu_sections (id, name, subtitle, image_path, sort_order, visible) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), subtitle = VALUES(subtitle), image_path = VALUES(image_path), sort_order = VALUES(sort_order), visible = VALUES(visible)',
            [$s['id'], $s['name'], $s['subtitle'], $s['image_path'], $s['sort_order'], (int) $s['visible']]
        );
        return self::section(Database::one('SELECT * FROM menu_sections WHERE id = ?', [$s['id']]));
    }

    public function sectionExists(string $id): bool
    {
        return Database::one('SELECT 1 AS x FROM menu_sections WHERE id = ?', [$id]) !== null;
    }

    public function upsertItem(array $i): array
    {
        Database::transaction(function () use ($i) {
            Database::run(
                'INSERT INTO menu_items (id, section_id, name, description, image_path, price, available, visible, sort_order, config) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)
                 ON DUPLICATE KEY UPDATE section_id = VALUES(section_id), name = VALUES(name), description = VALUES(description), image_path = VALUES(image_path),
                   price = VALUES(price), available = VALUES(available), visible = VALUES(visible), sort_order = VALUES(sort_order)',
                [$i['id'], $i['section_id'], $i['name'], $i['description'], $i['image_path'], $i['price'], (int) $i['available'], (int) $i['visible'], $i['sort_order']]
            );
            // Keep the item's own price row in step (it carries the low-stock flag).
            Database::run(
                'INSERT INTO menu_prices (pkey, product_name, label, price, available, sort) VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE product_name = VALUES(product_name), label = VALUES(label), price = VALUES(price), available = VALUES(available), sort = VALUES(sort)',
                ['p:' . $i['id'], $i['name'], $i['name'], $i['price'], (int) ($i['available'] && $i['visible']), $i['sort_order']]
            );
        });
        return self::item(Database::one('SELECT * FROM menu_items WHERE id = ?', [$i['id']]));
    }

    /** @param string[] $ids new order, first = 0 */
    public function reorder(string $kind, array $ids): void
    {
        Database::transaction(function () use ($kind, $ids) {
            foreach (array_values($ids) as $rank => $id) {
                if ($kind === 'section') {
                    Database::run('UPDATE menu_sections SET sort_order = ? WHERE id = ?', [$rank, $id]);
                } else {
                    Database::run('UPDATE menu_items SET sort_order = ? WHERE id = ?', [$rank, $id]);
                    Database::run('UPDATE menu_prices SET sort = ? WHERE pkey = ?', [$rank, 'p:' . $id]);
                }
            }
        });
    }

    public function updatePrice(string $key, array $patch): array
    {
        $row = Database::one('SELECT * FROM menu_prices WHERE pkey = ?', [$key]) ?? throw new HttpException('That menu price was not found.', 404);
        // An item's own price and availability are edited on the item itself; here only the stock warning applies.
        if (str_starts_with($key, 'p:')) $patch = array_intersect_key($patch, ['low_stock' => 1]);
        $sets = [];
        $params = [];
        foreach (['price' => 'price', 'available' => 'available', 'low_stock' => 'low_stock'] as $field => $col) {
            if (array_key_exists($field, $patch)) { $sets[] = "$col = ?"; $params[] = is_bool($patch[$field]) ? (int) $patch[$field] : $patch[$field]; }
        }
        if ($sets) Database::run('UPDATE menu_prices SET ' . implode(', ', $sets) . ' WHERE pkey = ?', [...$params, $key]);
        return self::priceRow(Database::one('SELECT * FROM menu_prices WHERE pkey = ?', [$key]) ?? $row);
    }

    /**
     * Every item customers can order, with admin prices applied to its meals and extras.
     * @return array<string, array>
     */
    public function orderableProducts(): array
    {
        $prices = [];
        foreach (Database::all('SELECT pkey, price, available FROM menu_prices') as $r) {
            $prices[$r['pkey']] = ['price' => (int) $r['price'], 'available' => (bool) $r['available']];
        }
        $out = [];
        $rows = Database::all('SELECT i.* FROM menu_items i JOIN menu_sections s ON s.id = i.section_id WHERE i.visible = 1 AND s.visible = 1');
        foreach ($rows as $r) {
            $cfg = json_decode((string) ($r['config'] ?? ''), true);
            $cfg = is_array($cfg) ? $cfg : [];
            $id = $r['id'];
            $meals = null;
            if (isset($cfg['meals']) && is_array($cfg['meals'])) {
                $meals = [];
                foreach ($cfg['meals'] as $m) {
                    $row = $prices["m:$id:{$m['id']}"] ?? null;
                    if ($row && !$row['available']) continue;
                    $meals[] = ['id' => $m['id'], 'label' => $m['label'], 'price' => $row ? $row['price'] : (int) $m['price']];
                }
            }
            $extras = [];
            foreach ($cfg['extras'] ?? [] as $e) {
                $row = $prices["e:$id:{$e['id']}"] ?? null;
                if ($row && !$row['available']) continue;
                $extra = ['id' => $e['id'], 'label' => $e['label'], 'price' => $row ? $row['price'] : (int) $e['price']];
                if (isset($e['max'])) $extra['max'] = (int) $e['max'];
                if (isset($e['soloPrice'])) $extra['soloPrice'] = ($prices["s:$id:{$e['id']}"]['price'] ?? (int) $e['soloPrice']);
                $extras[] = $extra;
            }
            $out[$id] = [
                'id' => $id, 'name' => $r['name'], 'price' => (int) $r['price'], 'available' => (bool) $r['available'],
                'meals' => $meals, 'extras' => $extras, 'options' => is_array($cfg['options'] ?? null) ? $cfg['options'] : [],
            ];
        }
        return $out;
    }
}
