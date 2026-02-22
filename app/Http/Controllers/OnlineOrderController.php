<?php

namespace App\Http\Controllers;

use App\Models\ChannelOrder;
use App\Models\ChannelOrderEvent;
use App\Models\IntegrationConnector;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Services\ActivityLogService;
use App\Services\ChannelIntegrations\ChannelConnectorRegistry;
use App\Services\CustomerMasterService;
use App\Services\OrderAutomationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OnlineOrderController extends Controller
{
    private const STATUS_ORDER = [
        'received',
        'placed',
        'accepted',
        'ready',
        'delivered',
        'rejected',
        'cancelled',
    ];

    public function index(Request $request)
    {
        $channel = strtolower(trim((string) $request->get('channel', '')));
        $status = strtolower(trim((string) $request->get('status', '')));
        $q = trim((string) $request->get('q', ''));
        $activeConnectorChannels = IntegrationConnector::query()
            ->where('is_active', true)
            ->select('code')
            ->orderBy('code')
            ->pluck('code')
            ->map(fn (string $code) => strtolower(trim($code)))
            ->filter(fn (string $code) => $code !== '')
            ->unique()
            ->values();
        $activeConnectorCount = $activeConnectorChannels->count();
        $queueInteractive = $activeConnectorCount > 0;

        $query = ChannelOrder::query()
            ->with(['connector', 'sale', 'order'])
            ->orderByDesc('last_event_at')
            ->orderByDesc('id');

        if ($channel !== '') {
            $query->where('channel', $channel);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $qLowerLike = '%' . mb_strtolower($q) . '%';
                $inner->whereRaw('LOWER(external_order_id) like ?', [$qLowerLike])
                    ->orWhereRaw('LOWER(channel) like ?', [$qLowerLike])
                    ->orWhereRaw('LOWER(COALESCE(customer_name, \'\')) like ?', [$qLowerLike])
                    ->orWhereRaw('LOWER(COALESCE(customer_identifier, \'\')) like ?', [$qLowerLike]);
            });
        }

        $orders = $query->limit(300)->get();
        $channels = $activeConnectorChannels;
        $statuses = self::STATUS_ORDER;
        $newOrderAlerts = $orders
            ->filter(fn (ChannelOrder $order) => in_array(strtolower((string) $order->status), ['received', 'placed'], true))
            ->take(8)
            ->values();
        $newOrderCount = $newOrderAlerts->count();

        return view('pos.online_orders', compact(
            'orders',
            'channels',
            'statuses',
            'channel',
            'status',
            'q',
            'activeConnectorCount',
            'queueInteractive',
            'newOrderAlerts',
            'newOrderCount'
        ));
    }

    public function accept(Request $request, ChannelOrder $channelOrder): RedirectResponse
    {
        return $this->performAction($request, $channelOrder, 'accept');
    }

    public function reject(Request $request, ChannelOrder $channelOrder): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:200',
        ]);

        return $this->performAction($request, $channelOrder, 'reject', (string) $validated['reason']);
    }

    public function ready(Request $request, ChannelOrder $channelOrder): RedirectResponse
    {
        return $this->performAction($request, $channelOrder, 'ready');
    }

    private function performAction(
        Request $request,
        ChannelOrder $channelOrder,
        string $action,
        ?string $reason = null
    ): RedirectResponse {
        $connector = $channelOrder->connector;
        if (!$connector || !$connector->is_active) {
            return back()->withErrors([
                'online_order' => 'Active connector is required for this order.',
            ]);
        }

        $adapter = app(ChannelConnectorRegistry::class)->resolve($connector);
        $result = match ($action) {
            'accept' => $adapter->acceptOrder($channelOrder, $connector),
            'reject' => $adapter->rejectOrder($channelOrder, $connector, (string) $reason),
            'ready' => $adapter->markOrderReady($channelOrder, $connector),
            default => null,
        };

        if ($result === null) {
            return back()->withErrors([
                'online_order' => 'Unsupported action.',
            ]);
        }

        $now = now();
        $nextStatus = match ($action) {
            'accept' => 'accepted',
            'ready' => 'ready',
            'reject' => 'rejected',
            default => $channelOrder->status,
        };

        $timestampUpdates = match ($nextStatus) {
            'accepted' => ['accepted_at' => $now],
            'ready' => ['ready_at' => $now],
            'rejected' => ['cancelled_at' => $now],
            default => [],
        };

        if ($result->success) {
            DB::transaction(function () use ($channelOrder, $connector, $nextStatus, $timestampUpdates, $result, $action, $reason, $now) {
                $channelOrder->update(array_merge([
                    'status' => $nextStatus,
                    'last_event_at' => $now,
                ], $timestampUpdates));

                if ($action === 'accept' && $this->shouldAutoCreateInvoiceOnAccept($connector)) {
                    $this->ensureSaleForAcceptedOrder($channelOrder, $now);
                }
                if ($action === 'accept') {
                    $this->ensureInternalOrderForAcceptedChannelOrder($channelOrder, $now);
                }

                $this->syncSaleStatus($channelOrder, $nextStatus, $timestampUpdates);

                ChannelOrderEvent::create([
                    'channel_order_id' => $channelOrder->id,
                    'integration_connector_id' => $connector->id,
                    'channel' => $channelOrder->channel,
                    'external_order_id' => $channelOrder->external_order_id,
                    'idempotency_key' => strtolower($channelOrder->channel . ':' . $channelOrder->external_order_id . ':outbound:' . $action . ':' . $now->format('YmdHisv')),
                    'event_type' => 'outbound_' . $action,
                    'signature_valid' => true,
                    'payload' => [
                        'action' => $action,
                        'reason' => $reason,
                    ],
                    'normalized_payload' => $result->meta,
                    'process_status' => 'processed',
                    'retry_count' => 0,
                    'processed_at' => $now,
                ]);
            });

            app(ActivityLogService::class)->log(
                module: 'integrations',
                action: 'online_order_' . $action,
                entityType: ChannelOrder::class,
                entityId: (int) $channelOrder->id,
                description: 'Online order action [' . $action . '] completed.',
                newValues: [
                    'channel' => $channelOrder->channel,
                    'external_order_id' => $channelOrder->external_order_id,
                    'status' => $nextStatus,
                    'connector' => $connector->code,
                    'meta' => $result->meta,
                ]
            );

            $channelOrder->refresh();
            $redirect = redirect()
                ->route('pos.online_orders.index')
                ->with('success', ucfirst($action) . ' pushed successfully for order ' . $channelOrder->external_order_id . '.');

            if ($action === 'accept' && $channelOrder->sale_id) {
                $redirect->with('invoice_sale_id', (int) $channelOrder->sale_id);
            }

            return $redirect;
        }

        ChannelOrderEvent::create([
            'channel_order_id' => $channelOrder->id,
            'integration_connector_id' => $connector->id,
            'channel' => $channelOrder->channel,
            'external_order_id' => $channelOrder->external_order_id,
            'idempotency_key' => strtolower($channelOrder->channel . ':' . $channelOrder->external_order_id . ':outbound_fail:' . $action . ':' . $now->format('YmdHisv')),
            'event_type' => 'outbound_' . $action,
            'signature_valid' => true,
            'payload' => [
                'action' => $action,
                'reason' => $reason,
            ],
            'normalized_payload' => $result->meta,
            'process_status' => 'failed',
            'process_error' => $result->message,
            'retry_count' => 0,
            'processed_at' => $now,
        ]);

        app(ActivityLogService::class)->log(
            module: 'integrations',
            action: 'online_order_' . $action . '_failed',
            entityType: ChannelOrder::class,
            entityId: (int) $channelOrder->id,
            description: 'Online order action [' . $action . '] failed.',
            newValues: [
                'channel' => $channelOrder->channel,
                'external_order_id' => $channelOrder->external_order_id,
                'status' => $channelOrder->status,
                'connector' => $connector->code,
                'message' => $result->message,
                'meta' => $result->meta,
            ]
        );

        return back()->withErrors([
            'online_order' => $result->message ?: 'Unable to push action to channel connector.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $timestampUpdates
     */
    private function syncSaleStatus(ChannelOrder $channelOrder, string $nextStatus, array $timestampUpdates): void
    {
        $sale = $channelOrder->sale;
        if (!$sale) {
            $sale = Sale::query()
                ->where('channel', $channelOrder->channel)
                ->where('external_order_id', $channelOrder->external_order_id)
                ->first();

            if ($sale && !$channelOrder->sale_id) {
                $channelOrder->update(['sale_id' => $sale->id]);
            }
        }

        if (!$sale) {
            return;
        }

        $updates = [
            'channel_status' => $nextStatus,
        ];

        if (isset($timestampUpdates['accepted_at'])) {
            $updates['channel_accepted_at'] = $timestampUpdates['accepted_at'];
        }
        if (isset($timestampUpdates['ready_at'])) {
            $updates['channel_ready_at'] = $timestampUpdates['ready_at'];
        }
        if (isset($timestampUpdates['cancelled_at'])) {
            $updates['channel_cancelled_at'] = $timestampUpdates['cancelled_at'];
        }

        $sale->update($updates);
    }

    private function ensureSaleForAcceptedOrder(ChannelOrder $channelOrder, \Illuminate\Support\Carbon $now): void
    {
        if ($channelOrder->sale_id) {
            return;
        }

        $existingSale = Sale::query()
            ->where('channel', $channelOrder->channel)
            ->where('external_order_id', $channelOrder->external_order_id)
            ->first();

        if ($existingSale) {
            $channelOrder->update(['sale_id' => $existingSale->id]);

            return;
        }

        $orderTotal = round((float) ($channelOrder->order_total ?? 0), 2);
        $customerProfile = $this->extractCustomerProfile($channelOrder);
        $customerMaster = app(CustomerMasterService::class);
        $customer = $customerMaster->upsertByIdentifier(
            (string) ($channelOrder->customer_identifier ?? ''),
            (string) ($channelOrder->customer_name ?? ''),
            $customerProfile
        );
        $resolvedIdentifier = $customerMaster->normalizeIdentifier((string) ($channelOrder->customer_identifier ?? ''));
        $resolvedIdentifier = $resolvedIdentifier !== ''
            ? $resolvedIdentifier
            : (string) ($customer?->mobile ?: $customer?->identifier ?: '');
        $resolvedName = $customer?->name ?: trim((string) ($channelOrder->customer_name ?? ''));
        $resolvedAddress = $customerMaster->buildAddressFromCustomer($customer);
        if ($resolvedAddress === '') {
            $resolvedAddress = $customerMaster->composeAddress($customerProfile);
        }

        if ($channelOrder->customer_name !== $resolvedName || (string) ($channelOrder->customer_identifier ?? '') !== $resolvedIdentifier) {
            $channelOrder->update([
                'customer_name' => $resolvedName !== '' ? $resolvedName : null,
                'customer_identifier' => $resolvedIdentifier !== '' ? $resolvedIdentifier : null,
            ]);
        }

        $paymentMode = strtolower((string) $this->extractFieldFromOrderPayload($channelOrder, [
            'payment_mode',
            'payment.mode',
            'payment_type',
        ]));
        if (!in_array($paymentMode, ['cash', 'upi', 'card'], true)) {
            $paymentMode = 'upi';
        }

        $source = in_array($channelOrder->channel, ['swiggy', 'zomato'], true)
            ? $channelOrder->channel
            : 'other';

        $sale = Sale::create([
            'bill_number' => 'TMP-' . Str::uuid()->toString(),
            'customer_id' => $customer?->id,
            'created_by_user_id' => auth()->id() ? (int) auth()->id() : null,
            'customer_identifier' => $resolvedIdentifier !== '' ? $resolvedIdentifier : null,
            'customer_name_snapshot' => $resolvedName !== '' ? $resolvedName : null,
            'customer_address_snapshot' => $resolvedAddress !== '' ? $resolvedAddress : null,
            'sub_total' => $orderTotal,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'round_off' => 0,
            'total_amount' => $orderTotal,
            'payment_mode' => $paymentMode,
            'order_source' => $source,
            'channel' => $channelOrder->channel,
            'external_order_id' => $channelOrder->external_order_id,
            'channel_status' => 'accepted',
            'channel_accepted_at' => $now,
            'order_reference' => $channelOrder->external_order_id,
            'paid_amount' => $orderTotal,
            'balance_amount' => 0,
        ]);

        $sale->update([
            'bill_number' => sprintf('INV-%s-%05d', $now->format('Ymd'), (int) $sale->id),
        ]);

        $channelOrder->update(['sale_id' => $sale->id]);
    }

    private function shouldAutoCreateInvoiceOnAccept(\App\Models\IntegrationConnector $connector): bool
    {
        $settings = is_array($connector->settings) ? $connector->settings : [];

        return (bool) ($settings['auto_create_invoice_on_accept'] ?? false);
    }

    private function ensureInternalOrderForAcceptedChannelOrder(ChannelOrder $channelOrder, \Illuminate\Support\Carbon $now): void
    {
        if (!empty($channelOrder->order_id)) {
            return;
        }

        if (!empty($channelOrder->sale_id)) {
            $existingFromSale = Order::query()
                ->where('sale_id', (int) $channelOrder->sale_id)
                ->first();
            if ($existingFromSale) {
                $channelOrder->update([
                    'order_id' => $existingFromSale->id,
                ]);

                return;
            }
        }

        $sale = null;
        if (!empty($channelOrder->sale_id)) {
            $sale = Sale::query()
                ->with(['items.product', 'customer'])
                ->where('id', (int) $channelOrder->sale_id)
                ->first();
        }

        $source = $this->resolveOrderSourceFromChannel((string) $channelOrder->channel);
        $customerMaster = app(CustomerMasterService::class);
        $customerProfile = $this->extractCustomerProfile($channelOrder);
        $customerAddress = $customerMaster->composeAddress($customerProfile);
        if ($customerAddress === '' && $sale) {
            $customerAddress = trim((string) ($sale->customer_address_snapshot ?? ''));
        }

        $lineItems = $this->extractOrderItemsFromChannelOrder($channelOrder);
        if (empty($lineItems) && $sale) {
            $lineItems = $sale->items->map(function ($saleItem) {
                $product = $saleItem->product;
                return [
                    'product_id' => $product?->id,
                    'product_code' => $product?->code,
                    'item_name' => $product?->name ?: ('Item #' . (int) $saleItem->id),
                    'unit' => $product?->unit ?: 'pcs',
                    'quantity' => (float) $saleItem->quantity,
                    'unit_price' => (float) $saleItem->price,
                    'line_total' => (float) $saleItem->total,
                ];
            })->values()->all();
        }
        if (empty($lineItems)) {
            $fallbackTotal = max(0, round((float) ($channelOrder->order_total ?? 0), 2));
            $lineItems = [[
                'product_id' => null,
                'product_code' => null,
                'item_name' => 'Online Order ' . $channelOrder->external_order_id,
                'unit' => 'order',
                'quantity' => 1,
                'unit_price' => $fallbackTotal,
                'line_total' => $fallbackTotal,
            ]];
        }

        $order = app(OrderAutomationService::class)->createOrderWithKot(
            orderAttributes: [
                'source' => $source,
                'status' => 'accepted',
                'customer_id' => $sale?->customer_id,
                'customer_identifier' => $channelOrder->customer_identifier ?: $sale?->customer_identifier,
                'customer_name' => $channelOrder->customer_name ?: $sale?->customer_name_snapshot,
                'customer_address' => $customerAddress !== '' ? $customerAddress : ($sale?->customer_address_snapshot ?: null),
                'notes' => 'Auto-created from online queue accept. External Order: ' . $channelOrder->external_order_id,
                'sale_id' => $channelOrder->sale_id,
                'created_by_user_id' => auth()->id() ? (int) auth()->id() : null,
                'accepted_at' => $now,
                'fallback_total' => (float) ($channelOrder->order_total ?? 0),
            ],
            lineItems: $lineItems,
            kotAttributes: [
                'status' => 'open',
                'created_by_user_id' => auth()->id() ? (int) auth()->id() : null,
            ],
        );

        $channelOrder->update([
            'order_id' => $order->id,
        ]);
    }

    private function resolveOrderSourceFromChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));

        return in_array($channel, ['swiggy', 'zomato'], true) ? $channel : 'other';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractOrderItemsFromChannelOrder(ChannelOrder $channelOrder): array
    {
        $payload = is_array($channelOrder->normalized_payload) ? $channelOrder->normalized_payload : [];
        if (empty($payload) && is_array($channelOrder->latest_payload)) {
            $payload = $channelOrder->latest_payload;
        }
        if (empty($payload)) {
            return [];
        }

        $rawItems = $this->extractLineItemsFromPayload($payload);
        if (empty($rawItems)) {
            return [];
        }

        $resolved = [];
        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemName = trim((string) ($item['item_name']
                ?? $item['name']
                ?? $item['product_name']
                ?? $item['title']
                ?? ''));
            $productCode = strtoupper(trim((string) ($item['product_code']
                ?? $item['code']
                ?? $item['sku']
                ?? $item['item_code']
                ?? '')));

            $product = $this->resolveProductForChannelItem($item, $productCode, $itemName);
            $quantity = $this->extractNumericFromLine($item, ['quantity', 'qty', 'item_quantity', 'count'], 1);
            if ($quantity <= 0) {
                $quantity = 1;
            }
            $unitPrice = $this->extractNumericFromLine($item, ['unit_price', 'price', 'rate', 'selling_price', 'mrp'], 0);
            if ($unitPrice <= 0 && $product) {
                $unitPrice = round((float) $product->price, 2);
            }
            $lineTotal = $this->extractNumericFromLine($item, ['line_total', 'total', 'amount', 'item_total'], -1);
            if ($lineTotal < 0) {
                $lineTotal = round($quantity * $unitPrice, 2);
            }

            $resolved[] = [
                'product_id' => $product?->id,
                'product_code' => $product?->code ?: ($productCode !== '' ? $productCode : null),
                'item_name' => $itemName !== '' ? $itemName : ($product?->name ?: 'Online Item'),
                'unit' => $product?->unit ?: (trim((string) ($item['unit'] ?? '')) ?: 'pcs'),
                'quantity' => round($quantity, 3),
                'unit_price' => round($unitPrice, 2),
                'line_total' => round($lineTotal, 2),
            ];
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, mixed>
     */
    private function extractLineItemsFromPayload(array $payload): array
    {
        $candidates = [
            'items',
            'order_items',
            'line_items',
            'cart.items',
            'order.items',
            'products',
        ];

        foreach ($candidates as $path) {
            $value = $this->getNestedValue($payload, $path);
            if (!is_array($value) || empty($value)) {
                continue;
            }

            $isList = array_keys($value) === range(0, count($value) - 1);
            if ($isList) {
                return $value;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function resolveProductForChannelItem(array $line, string $productCode, string $itemName): ?Product
    {
        $productId = is_numeric($line['product_id'] ?? null) ? (int) $line['product_id'] : 0;
        if ($productId > 0) {
            $product = Product::query()
                ->where('id', $productId)
                ->where('type', 'finished_good')
                ->where('is_active', true)
                ->first();
            if ($product) {
                return $product;
            }
        }

        if ($productCode !== '') {
            $product = Product::query()
                ->where('type', 'finished_good')
                ->where('is_active', true)
                ->where(function ($query) use ($productCode) {
                    $query->where('code', $productCode)
                        ->orWhere('legacy_code', $productCode);
                })
                ->first();
            if ($product) {
                return $product;
            }
        }

        if ($itemName !== '') {
            return Product::query()
                ->where('type', 'finished_good')
                ->where('is_active', true)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($itemName)])
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<int, string>  $keys
     */
    private function extractNumericFromLine(array $line, array $keys, float $default): float
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $line)) {
                continue;
            }
            $value = $line[$key];
            if (is_numeric($value)) {
                return round((float) $value, 2);
            }
        }

        return $default;
    }

    /**
     * @return array<string, string>
     */
    private function extractCustomerProfile(ChannelOrder $channelOrder): array
    {
        $customerMaster = app(CustomerMasterService::class);

        $address = $this->extractFieldFromOrderPayload($channelOrder, [
            'customer_address',
            'delivery_address',
            'customer.address',
            'customer.location.address',
            'customer.location.full_address',
        ]);
        $addressLine1 = $this->extractFieldFromOrderPayload($channelOrder, [
            'customer.address_line1',
            'customer.apartment_house',
            'customer.house',
        ]);
        $road = $this->extractFieldFromOrderPayload($channelOrder, [
            'customer.road',
            'customer.street',
        ]);
        $sector = $this->extractFieldFromOrderPayload($channelOrder, [
            'customer.sector',
            'customer.area',
        ]);
        $city = $this->extractFieldFromOrderPayload($channelOrder, [
            'customer.city',
            'customer.location.city',
        ]);
        $pincode = $this->extractFieldFromOrderPayload($channelOrder, [
            'customer.pincode',
            'customer.postal_code',
            'customer.zipcode',
        ]);
        $preference = $this->extractFieldFromOrderPayload($channelOrder, [
            'customer.preference',
            'customer.notes',
        ]);

        return $customerMaster->normalizeProfile([
            'address' => $address,
            'address_line1' => $addressLine1,
            'road' => $road,
            'sector' => $sector,
            'city' => $city,
            'pincode' => $pincode,
            'preference' => $preference,
        ]);
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function extractFieldFromOrderPayload(ChannelOrder $channelOrder, array $paths): string
    {
        $payload = $channelOrder->latest_payload;
        if (!is_array($payload)) {
            $payload = $channelOrder->normalized_payload;
        }
        if (!is_array($payload)) {
            return '';
        }

        foreach ($paths as $path) {
            $value = $this->getNestedValue($payload, $path);
            if (is_scalar($value)) {
                $text = trim((string) $value);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function getNestedValue(array $payload, string $path): mixed
    {
        if (!str_contains($path, '.')) {
            return $payload[$path] ?? null;
        }

        $segments = explode('.', $path);
        $value = $payload;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
