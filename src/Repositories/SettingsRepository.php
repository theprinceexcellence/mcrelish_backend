<?php
declare(strict_types=1);
namespace McRelish\Repositories;

use McRelish\Core\{Database, HttpException, Time, Uuid};

/** Store open/closed switch, delivery areas and the home-page campaign banner. */
final class SettingsRepository
{
    public function store(): array
    {
        $r = Database::one('SELECT * FROM store_settings WHERE id = 1');
        if (!$r) { Database::run('INSERT INTO store_settings (id, store_open, note) VALUES (1, 1, \'\')'); $r = Database::one('SELECT * FROM store_settings WHERE id = 1'); }
        return ['id' => 1, 'store_open' => (bool) $r['store_open'], 'note' => $r['note'], 'updated_at' => Time::iso($r['updated_at'])];
    }

    public function updateStore(array $patch): array
    {
        $this->store();
        $sets = []; $params = [];
        if (array_key_exists('store_open', $patch)) { $sets[] = 'store_open = ?'; $params[] = (int) $patch['store_open']; }
        if (array_key_exists('note', $patch))       { $sets[] = 'note = ?';       $params[] = $patch['note']; }
        if ($sets) Database::run('UPDATE store_settings SET ' . implode(', ', $sets) . ' WHERE id = 1', $params);
        return $this->store();
    }

    private static function zoneRow(array $r): array
    {
        return ['id' => $r['id'], 'name' => $r['name'], 'fee' => (int) $r['fee'], 'active' => (bool) $r['active'], 'created_at' => Time::iso($r['created_at'])];
    }

    public function zones(bool $activeOnly): array
    {
        $sql = $activeOnly ? 'SELECT * FROM delivery_zones WHERE active = 1 ORDER BY fee, name' : 'SELECT * FROM delivery_zones ORDER BY name';
        return array_map([self::class, 'zoneRow'], Database::all($sql));
    }

    public function zone(string $id): ?array
    {
        $r = Database::one('SELECT * FROM delivery_zones WHERE id = ?', [$id]);
        return $r ? self::zoneRow($r) : null;
    }

    public function addZone(string $name, int $fee): array
    {
        $id = Uuid::v4();
        Database::run('INSERT INTO delivery_zones (id, name, fee, active) VALUES (?, ?, ?, 1)', [$id, $name, $fee]);
        return $this->zone($id) ?? throw new HttpException('Could not add delivery area.', 500);
    }

    public function updateZone(string $id, array $patch): array
    {
        $this->zone($id) ?? throw new HttpException('Delivery area not found.', 404);
        $sets = []; $params = [];
        if (array_key_exists('fee', $patch))    { $sets[] = 'fee = ?';    $params[] = $patch['fee']; }
        if (array_key_exists('active', $patch)) { $sets[] = 'active = ?'; $params[] = (int) $patch['active']; }
        if ($sets) Database::run('UPDATE delivery_zones SET ' . implode(', ', $sets) . ' WHERE id = ?', [...$params, $id]);
        return $this->zone($id);
    }

    public function deleteZone(string $id): void
    {
        if (Database::run('DELETE FROM delivery_zones WHERE id = ?', [$id]) < 1) throw new HttpException('Delivery area not found.', 404);
    }

    public function promo(): array
    {
        $r = Database::one('SELECT * FROM hero_promo WHERE id = 1');
        if (!$r) {
            Database::run("INSERT INTO hero_promo (id, label, title, subtitle, product_id, active, image_url) VALUES (1, 'November Jolly Fest', 'Jolly Fest Feast', 'Special Jollof · Peppered Chicken · 2 Peppered Beef · Plantain · Coleslaw · Water · Zobo', 'jolly-fest', 1, '')");
            $r = Database::one('SELECT * FROM hero_promo WHERE id = 1');
        }
        return ['label' => $r['label'], 'title' => $r['title'], 'subtitle' => $r['subtitle'], 'product_id' => $r['product_id'],
            'active' => (bool) $r['active'], 'image_url' => $r['image_url']];
    }

    public function updatePromo(array $patch): array
    {
        $this->promo();
        $sets = []; $params = [];
        foreach (['label', 'title', 'subtitle', 'product_id', 'image_url'] as $f) {
            if (array_key_exists($f, $patch)) { $sets[] = "$f = ?"; $params[] = $patch[$f]; }
        }
        if (array_key_exists('active', $patch)) { $sets[] = 'active = ?'; $params[] = (int) $patch['active']; }
        if ($sets) Database::run('UPDATE hero_promo SET ' . implode(', ', $sets) . ' WHERE id = 1', $params);
        return $this->promo();
    }
}
