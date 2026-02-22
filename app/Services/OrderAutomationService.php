<?php

namespace App\Services;

use App\Models\Kot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderAutomationService
{
    /**
     * @param  array<string, mixed>  $orderAttributes
     * @param  array<int, array<string, mixed>>  $lineItems
     * @param  array<string, mixed>  $kotAttributes
     */
    public function createOrderWithKot(array $orderAttributes, array $lineItems, array $kotAttributes = []): Order
    {
        return DB::transaction(function () use ($orderAttributes, $lineItems, $kotAttributes) {
            $source = strtolower(trim((string) ($orderAttributes['source'] ?? 'outlet')));
            if (!in_array($source, ['outlet', 'phone', 'whatsapp', 'swiggy', 'zomato', 'other'], true)) {
                $source = 'other';
            }

            $status = strtolower(trim((string) ($orderAttributes['status'] ?? 'new')));
            if (!in_array($status, ['new', 'accepted', 'in_kitchen', 'ready', 'dispatched', 'completed', 'invoiced', 'cancelled'], true)) {
                $status = 'new';
            }

            $order = Order::create([
                'order_number' => 'TMP-' . uniqid('ORD', true),
                'source' => $source,
                'status' => $status,
                'customer_id' => $orderAttributes['customer_id'] ?? null,
                'customer_identifier' => $this->nullableString($orderAttributes['customer_identifier'] ?? null),
                'customer_name' => $this->nullableString($orderAttributes['customer_name'] ?? null),
                'customer_address' => $this->nullableString($orderAttributes['customer_address'] ?? null),
                'notes' => $this->nullableString($orderAttributes['notes'] ?? null),
                'sale_id' => $orderAttributes['sale_id'] ?? null,
                'created_by_user_id' => $orderAttributes['created_by_user_id'] ?? null,
                'accepted_at' => $orderAttributes['accepted_at'] ?? null,
                'in_kitchen_at' => $orderAttributes['in_kitchen_at'] ?? null,
                'ready_at' => $orderAttributes['ready_at'] ?? null,
                'dispatched_at' => $orderAttributes['dispatched_at'] ?? null,
                'completed_at' => $orderAttributes['completed_at'] ?? null,
                'invoiced_at' => $orderAttributes['invoiced_at'] ?? null,
                'cancelled_at' => $orderAttributes['cancelled_at'] ?? null,
            ]);

            $productIds = collect($lineItems)
                ->pluck('product_id')
                ->filter(fn ($value) => is_numeric($value) && (int) $value > 0)
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values();

            $productsById = Product::query()
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $createdItems = 0;
            foreach ($lineItems as $lineItem) {
                $quantity = round((float) ($lineItem['quantity'] ?? 0), 3);
                if ($quantity <= 0) {
                    continue;
                }

                $productId = is_numeric($lineItem['product_id'] ?? null) ? (int) $lineItem['product_id'] : null;
                $product = $productId ? $productsById->get($productId) : null;

                $unitPrice = $this->numericOrDefault(
                    $lineItem['unit_price'] ?? null,
                    $product ? (float) $product->price : 0
                );
                $lineTotal = isset($lineItem['line_total']) && is_numeric($lineItem['line_total'])
                    ? round((float) $lineItem['line_total'], 2)
                    : round($unitPrice * $quantity, 2);

                $itemName = $this->nullableString($lineItem['item_name'] ?? null)
                    ?? ($product ? (string) $product->name : null)
                    ?? 'Order Item';
                $productCode = $this->nullableString($lineItem['product_code'] ?? null)
                    ?? ($product?->code ? (string) $product->code : null);
                $unit = $this->nullableString($lineItem['unit'] ?? null)
                    ?? ($product?->unit ? (string) $product->unit : null)
                    ?? 'pcs';

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product?->id,
                    'product_code' => $productCode,
                    'item_name' => $itemName,
                    'unit' => $unit,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);

                $createdItems++;
            }

            if ($createdItems === 0) {
                $fallbackTotal = max(0, round((float) ($orderAttributes['fallback_total'] ?? 0), 2));
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => null,
                    'product_code' => null,
                    'item_name' => 'Order Item',
                    'unit' => 'order',
                    'quantity' => 1,
                    'unit_price' => $fallbackTotal,
                    'line_total' => $fallbackTotal,
                ]);
            }

            $order->update([
                'order_number' => sprintf('ORD-%s-%05d', now()->format('Ymd'), (int) $order->id),
            ]);

            if ($this->shouldCreateKot($orderAttributes, $kotAttributes)) {
                $kot = Kot::create([
                    'order_id' => $order->id,
                    'kot_number' => 'TMP-KOT-' . uniqid(),
                    'status' => $this->nullableString($kotAttributes['status'] ?? null) ?? 'open',
                    'printed_at' => $kotAttributes['printed_at'] ?? null,
                    'created_by_user_id' => $kotAttributes['created_by_user_id'] ?? ($orderAttributes['created_by_user_id'] ?? null),
                ]);
                $kot->update([
                    'kot_number' => sprintf('KOT-%s-%05d', now()->format('Ymd'), (int) $kot->id),
                ]);
            }

            return $order->fresh(['items', 'kot', 'sale']);
        });
    }

    /**
     * @param  array<string, mixed>  $orderAttributes
     * @param  array<string, mixed>  $kotAttributes
     */
    private function shouldCreateKot(array $orderAttributes, array $kotAttributes): bool
    {
        $forceKot = $kotAttributes['force_kot'] ?? null;
        if ($forceKot !== null) {
            return (bool) $forceKot;
        }

        $settings = app(BusinessSettingsService::class)->get();
        $mode = strtolower(trim((string) ($settings['kot_mode'] ?? 'always')));
        if (!in_array($mode, ['off', 'conditional', 'always'], true)) {
            $mode = 'always';
        }
        if ($mode === 'off') {
            return false;
        }
        if ($mode === 'always') {
            return true;
        }

        $status = strtolower(trim((string) ($orderAttributes['status'] ?? 'new')));
        if (in_array($status, ['in_kitchen', 'ready', 'dispatched', 'completed'], true)) {
            return true;
        }

        return (bool) ($orderAttributes['needs_kot'] ?? false);
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function numericOrDefault(mixed $value, float $default): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        return round($default, 2);
    }
}
