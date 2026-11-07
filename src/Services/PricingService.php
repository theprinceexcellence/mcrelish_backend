<?php
declare(strict_types=1);
namespace McRelish\Services;

use McRelish\Core\HttpException;
use McRelish\Repositories\MenuRepository;

/**
 * Recomputes every order line from the database (item price + meal/extra/option rules + admin prices).
 * Client prices are never trusted. This is a faithful port of the website's own pricing rules.
 */
final class PricingService
{
    private const NO_MEAL = 'none';

    public function __construct(private MenuRepository $menu = new MenuRepository()) {}

    /** @return array{items: array<int, array>, subtotal: int} */
    public function priceOrder(array $lines): array
    {
        $catalog = $this->menu->orderableProducts();
        $items = [];
        $subtotal = 0;
        foreach ($lines as $line) {
            $line = $this->cleanLine($line);
            $product = $catalog[$line['productId']] ?? throw new HttpException('An item in your cart is no longer on the menu.', 422);
            if (!$product['available']) throw new HttpException("{$product['name']} is not available right now.", 422);

            $options = [];
            foreach ($product['options'] as $group) {
                $choice = null;
                foreach ($group['choices'] as $c) if ($c['id'] === ($line['options'][$group['id']] ?? null)) { $choice = $c; break; }
                $choice ??= $group['choices'][0] ?? null;
                if (!$choice) throw new HttpException("Please review your choices for {$product['name']}.", 422);
                $options[$group['id']] = $choice['id'];
            }
            if ($product['meals'] !== null) {
                $meal = $this->find($product['meals'], $line['options']['meal'] ?? '');
                if (!$meal) throw new HttpException("Please choose a protein again for {$product['name']}.", 422);
                $options['meal'] = $meal['id'];
            }
            $extras = [];
            foreach ($line['extras'] as $id => $qty) {
                if ($qty <= 0) continue;
                $extra = $this->find($product['extras'] ?? [], (string) $id);
                if (!$extra) throw new HttpException("An add-on for {$product['name']} is no longer available.", 422);
                $extras[(string) $id] = isset($extra['max']) ? min((int) $extra['max'], $qty) : $qty;
            }
            if ($product['meals'] !== null && ($options['meal'] ?? '') === self::NO_MEAL && !array_filter($extras, fn ($q) => $q > 0)) {
                throw new HttpException("Please review your choices for {$product['name']}.", 422);
            }

            $unit = $this->unitPrice($product, $options, $extras);
            $items[] = [
                'productId' => $product['id'], 'name' => $product['name'], 'quantity' => $line['quantity'],
                'unitPrice' => $unit, 'price' => $unit, 'lineTotal' => $unit * $line['quantity'],
                'details' => $this->describe($product, $options, $extras),
                'notes' => $line['notes'], 'options' => (object) $options, 'extras' => (object) $extras,
            ];
            $subtotal += $unit * $line['quantity'];
        }
        return ['items' => $items, 'subtotal' => $subtotal];
    }

    private function cleanLine(mixed $line): array
    {
        if (!is_array($line)) throw new HttpException('Your order could not be read.', 422);
        $pid = $line['productId'] ?? null;
        $qty = $line['quantity'] ?? null;
        if (!is_string($pid) || $pid === '' || strlen($pid) > 80) throw new HttpException('Your order could not be read.', 422);
        if (!is_int($qty) || $qty < 1 || $qty > 50) throw new HttpException('Each item quantity must be between 1 and 50.', 422);
        $options = [];
        foreach (is_array($line['options'] ?? null) ? $line['options'] : [] as $k => $v) {
            if (!is_string($v) || strlen($v) > 80) throw new HttpException('Your order could not be read.', 422);
            $options[(string) $k] = $v;
        }
        $extras = [];
        foreach (is_array($line['extras'] ?? null) ? $line['extras'] : [] as $k => $v) {
            if (!is_int($v) || $v < 0 || $v > 20) throw new HttpException('Your order could not be read.', 422);
            $extras[(string) $k] = $v;
        }
        $notes = $line['notes'] ?? '';
        if (!is_string($notes) || mb_strlen($notes) > 300) throw new HttpException('Item notes are too long.', 422);
        return ['productId' => $pid, 'quantity' => $qty, 'options' => $options, 'extras' => $extras, 'notes' => trim($notes)];
    }

    private function find(array $list, string $id): ?array
    {
        foreach ($list as $row) if ($row['id'] === $id) return $row;
        return null;
    }

    private function unitPrice(array $product, array $options, array $extras): int
    {
        if ($product['meals'] === null) return $product['price'];
        $mealId = $options['meal'] ?? $product['meals'][0]['id'];
        $meal = $this->find($product['meals'], $mealId);
        $chosen = [];
        foreach ($product['extras'] ?? [] as $e) {
            $qty = max(0, (int) floor($extras[$e['id']] ?? 0));
            if ($qty > 0) $chosen[] = [$e, $qty];
        }
        $extrasTotal = array_sum(array_map(fn ($x) => $x[0]['price'] * $x[1], $chosen));
        if ($mealId === self::NO_MEAL || !$meal) {
            $units = array_sum(array_column($chosen, 1));
            if ($units === 1 && !empty($chosen[0][0]['soloPrice'])) return (int) $chosen[0][0]['soloPrice'];
            return (int) $extrasTotal;
        }
        return (int) ($meal['price'] + $extrasTotal);
    }

    /** @return string[] */
    private function describe(array $product, array $options, array $extras): array
    {
        $lines = [];
        if ($product['meals'] !== null) {
            $meal = $this->find($product['meals'], $options['meal'] ?? '');
            if ($meal) $lines[] = $meal['id'] === self::NO_MEAL ? $meal['label'] : "{$meal['label']} (" . self::naira($meal['price']) . ')';
            foreach ($product['extras'] ?? [] as $e) {
                $qty = $extras[$e['id']] ?? 0;
                if ($qty > 0) $lines[] = "{$qty} × {$e['label']} (" . self::naira($e['price'] * $qty) . ')';
            }
        }
        foreach ($product['options'] as $group) {
            foreach ($group['choices'] as $c) {
                if ($c['id'] === ($options[$group['id']] ?? null)) { $lines[] = "{$group['label']}: {$c['label']}"; break; }
            }
        }
        return $lines;
    }

    private static function naira(int|float $n): string { return '₦' . number_format((float) $n); }
}
