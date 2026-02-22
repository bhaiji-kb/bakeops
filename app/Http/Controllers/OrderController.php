<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Kot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\ActivityLogService;
use App\Services\BusinessSettingsService;
use App\Services\CustomerMasterService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    private const ORDER_SOURCES = [
        'outlet',
        'phone',
        'whatsapp',
        'swiggy',
        'zomato',
        'other',
    ];

    private const ORDER_STATUSES = [
        'new',
        'accepted',
        'in_kitchen',
        'ready',
        'dispatched',
        'completed',
        'invoiced',
        'cancelled',
    ];

    public function index(Request $request)
    {
        $status = strtolower(trim((string) $request->get('status', '')));
        $q = trim((string) $request->get('q', ''));
        $oldItems = collect(old('items', []));
        $oldItemProductIds = $oldItems
            ->pluck('product_id')
            ->filter(fn ($value) => is_numeric($value) && (int) $value > 0)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        $productsById = Product::query()
            ->whereIn('id', $oldItemProductIds)
            ->where('type', 'finished_good')
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $initialByProductId = [];
        foreach ($oldItems as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);
            if ($productId <= 0 || $quantity <= 0 || !$productsById->has($productId)) {
                continue;
            }

            if (!isset($initialByProductId[$productId])) {
                $product = $productsById->get($productId);
                $initialByProductId[$productId] = [
                    'id' => (int) $product->id,
                    'code' => (string) ($product->code ?? ''),
                    'name' => (string) ($product->name ?? ''),
                    'unit' => (string) ($product->unit ?? 'pcs'),
                    'price' => round((float) ($product->price ?? 0), 2),
                    'stock' => round((float) $product->currentStock(), 2),
                    'quantity' => 0,
                ];
            }

            $initialByProductId[$productId]['quantity'] += $quantity;
        }

        $orders = Order::query()
            ->with(['items', 'kot', 'sale'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $qLower = '%' . mb_strtolower($q) . '%';
                $query->where(function ($inner) use ($qLower) {
                    $inner->whereRaw('LOWER(order_number) like ?', [$qLower])
                        ->orWhereRaw('LOWER(COALESCE(customer_name, \'\')) like ?', [$qLower])
                        ->orWhereRaw('LOWER(COALESCE(customer_identifier, \'\')) like ?', [$qLower])
                        ->orWhereRaw('LOWER(source) like ?', [$qLower]);
                });
            })
            ->orderByDesc('id')
            ->limit(250)
            ->get();

        $orderIds = $orders->pluck('id')->filter()->values();
        $orderLogMap = collect();
        if ($orderIds->isNotEmpty()) {
            $orderLogMap = ActivityLog::query()
                ->with('user:id,name')
                ->where('module', 'orders')
                ->where('entity_type', Order::class)
                ->whereIn('entity_id', $orderIds->all())
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->groupBy('entity_id');
        }

        $orderTrailById = [];
        foreach ($orders as $order) {
            $orderTrailById[$order->id] = $this->buildOrderTrail($order, $orderLogMap->get($order->id, collect()));
        }

        $orderSources = self::ORDER_SOURCES;
        $orderStatuses = self::ORDER_STATUSES;
        $initialItems = array_values($initialByProductId);
        $businessSettings = app(BusinessSettingsService::class)->get();

        return view('orders.index', compact(
            'orders',
            'status',
            'q',
            'orderSources',
            'orderStatuses',
            'initialItems',
            'businessSettings',
            'orderTrailById'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'source' => ['required', Rule::in(self::ORDER_SOURCES)],
            'customer_identifier' => ['nullable', 'string', 'max:40'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'customer_address_line1' => ['nullable', 'string', 'max:255'],
            'customer_road' => ['nullable', 'string', 'max:255'],
            'customer_sector' => ['nullable', 'string', 'max:120'],
            'customer_city' => ['nullable', 'string', 'max:120'],
            'customer_pincode' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'customer_preference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'advance_paid_amount' => ['nullable', 'numeric', 'min:0'],
            'advance_payment_mode' => ['nullable', Rule::in(['cash', 'upi', 'card'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        $customerMaster = app(CustomerMasterService::class);
        $source = (string) $validated['source'];
        $customerIdentifier = $customerMaster->normalizeIdentifier((string) ($validated['customer_identifier'] ?? ''));
        $customerName = $customerMaster->normalizeName((string) ($validated['customer_name'] ?? ''));
        $customerProfile = $customerMaster->normalizeProfile([
            'address_line1' => (string) ($validated['customer_address_line1'] ?? ''),
            'road' => (string) ($validated['customer_road'] ?? ''),
            'sector' => (string) ($validated['customer_sector'] ?? ''),
            'city' => (string) ($validated['customer_city'] ?? ''),
            'pincode' => (string) ($validated['customer_pincode'] ?? ''),
            'preference' => (string) ($validated['customer_preference'] ?? ''),
            'address' => (string) ($validated['customer_address'] ?? ''),
        ]);
        $customerAddress = $customerMaster->composeAddress($customerProfile);
        if ($customerAddress === '') {
            $customerAddress = $customerMaster->normalizeAddress((string) ($validated['customer_address'] ?? ''));
        }
        $notes = $customerMaster->normalizeAddress((string) ($validated['notes'] ?? ''));
        if ($notes === '' && ($customerProfile['preference'] ?? '') !== '') {
            $notes = (string) $customerProfile['preference'];
        } elseif (($customerProfile['preference'] ?? '') !== '' && !str_contains(mb_strtolower($notes), mb_strtolower((string) $customerProfile['preference']))) {
            $notes = trim($notes . ' | Preference: ' . (string) $customerProfile['preference']);
        }

        $advancePaidAmount = round((float) ($validated['advance_paid_amount'] ?? 0), 2);
        $advancePaymentMode = trim((string) ($validated['advance_payment_mode'] ?? ''));
        if ($advancePaidAmount > 0 && $advancePaymentMode === '') {
            throw ValidationException::withMessages([
                'advance_payment_mode' => 'Select payment mode when advance paid amount is entered.',
            ]);
        }
        if ($advancePaidAmount <= 0) {
            $advancePaidAmount = 0;
            $advancePaymentMode = '';
        }

        if ($source !== 'outlet') {
            $errors = [];
            if ($customerIdentifier === '') {
                $errors['customer_identifier'] = 'Customer mobile is required for non-outlet orders.';
            }
            if ($customerName === '') {
                $errors['customer_name'] = 'Customer name is required for non-outlet orders.';
            }
            if ($customerAddress === '') {
                $errors['customer_address'] = 'Customer address is required for non-outlet orders.';
            }
            if (!empty($errors)) {
                throw ValidationException::withMessages($errors);
            }
        }

        $normalizedItems = collect($validated['items'])
            ->map(function (array $row) {
                return [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'quantity' => (float) ($row['quantity'] ?? 0),
                ];
            })
            ->filter(fn (array $row) => $row['product_id'] > 0 && $row['quantity'] > 0)
            ->values();

        if ($normalizedItems->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one valid item for order creation.',
            ]);
        }

        $productIds = $normalizedItems->pluck('product_id')->unique()->values();
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('type', 'finished_good')
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($products->count() !== $productIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'Only active finished goods can be added to orders.',
            ]);
        }

        $customer = null;
        if ($customerIdentifier !== '' && ($customerName !== '' || $customerAddress !== '')) {
            $customer = $customerMaster->upsertByIdentifier($customerIdentifier, $customerName, $customerProfile);
        } elseif ($customerIdentifier !== '') {
            $customer = Customer::query()
                ->where(function ($query) use ($customerIdentifier) {
                    $query->where('mobile', $customerIdentifier)
                        ->orWhereRaw('UPPER(COALESCE(identifier, \'\')) = ?', [strtoupper($customerIdentifier)]);
                })
                ->first();
        }

        if ($customer) {
            if ($customerIdentifier === '') {
                $customerIdentifier = (string) ($customer->mobile ?: $customer->identifier ?: '');
            }
            if ($customerName === '') {
                $customerName = (string) ($customer->name ?: '');
            }
            if ($customerAddress === '') {
                $customerAddress = $customerMaster->buildAddressFromCustomer($customer);
            }
        }

        $order = Order::create([
            'order_number' => 'TMP-' . uniqid('ORD', true),
            'source' => $source,
            'status' => 'accepted',
            'customer_id' => $customer?->id,
            'customer_identifier' => $customerIdentifier !== '' ? $customerIdentifier : null,
            'customer_name' => $customerName !== '' ? $customerName : null,
            'customer_address' => $customerAddress !== '' ? $customerAddress : null,
            'notes' => $notes !== '' ? $notes : null,
            'advance_paid_amount' => $advancePaidAmount,
            'advance_payment_mode' => $advancePaymentMode !== '' ? $advancePaymentMode : null,
            'created_by_user_id' => auth()->id() ? (int) auth()->id() : null,
            'accepted_at' => now(),
        ]);

        foreach ($normalizedItems as $row) {
            $product = $products->get((int) $row['product_id']);
            if (!$product) {
                continue;
            }

            $quantity = round((float) $row['quantity'], 3);
            if ($quantity <= 0) {
                continue;
            }

            $unitPrice = round((float) $product->price, 2);
            $lineTotal = round($unitPrice * $quantity, 2);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_code' => (string) ($product->code ?? ''),
                'item_name' => (string) $product->name,
                'unit' => (string) ($product->unit ?? 'pcs'),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ]);
        }

        $order->update([
            'order_number' => sprintf('ORD-%s-%05d', now()->format('Ymd'), (int) $order->id),
        ]);

        $kot = null;
        if ($this->shouldCreateKotOnCreate()) {
            $kot = $this->createKotForOrder($order);
        }

        app(ActivityLogService::class)->log(
            module: 'orders',
            action: 'create',
            entityType: Order::class,
            entityId: (int) $order->id,
            description: $kot ? 'Order created with KOT.' : 'Order created.',
            newValues: [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'source' => $order->source,
                'items_count' => $order->items()->count(),
                'kot_number' => $kot?->kot_number,
                'advance_paid_amount' => $advancePaidAmount,
                'advance_payment_mode' => $advancePaymentMode !== '' ? $advancePaymentMode : null,
            ]
        );

        return redirect()
            ->route('orders.index')
            ->with('success', $kot ? 'Order created and KOT generated.' : 'Order created.');
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::ORDER_STATUSES)],
        ]);

        $nextStatus = (string) $validated['status'];

        if ($order->status === 'invoiced' && $nextStatus !== 'invoiced') {
            return back()->withErrors([
                'order_status' => 'Invoiced order status cannot be changed.',
            ]);
        }

        if ($nextStatus === 'cancelled' && $order->sale_id) {
            return back()->withErrors([
                'order_status' => 'Invoiced order cannot be cancelled.',
            ]);
        }

        if ($nextStatus === 'invoiced' && !$order->sale_id) {
            return back()->withErrors([
                'order_status' => 'Create invoice first before setting status to invoiced.',
            ]);
        }

        $updates = [
            'status' => $nextStatus,
        ];

        $timestampMap = [
            'accepted' => 'accepted_at',
            'in_kitchen' => 'in_kitchen_at',
            'ready' => 'ready_at',
            'dispatched' => 'dispatched_at',
            'completed' => 'completed_at',
            'invoiced' => 'invoiced_at',
            'cancelled' => 'cancelled_at',
        ];
        if (isset($timestampMap[$nextStatus])) {
            $updates[$timestampMap[$nextStatus]] = now();
        }

        $order->update($updates);

        if (in_array($nextStatus, ['in_kitchen', 'ready', 'dispatched', 'completed'], true) && !$order->kot) {
            if ($this->shouldCreateKotOnKitchenFlow()) {
                $order->setRelation('kot', $this->createKotForOrder($order));
            }
        }

        if ($order->kot) {
            $kotStatus = match ($nextStatus) {
                'cancelled' => 'cancelled',
                'dispatched', 'completed', 'invoiced' => 'closed',
                default => 'open',
            };
            $order->kot->update([
                'status' => $kotStatus,
            ]);
        }

        app(ActivityLogService::class)->log(
            module: 'orders',
            action: 'status_update',
            entityType: Order::class,
            entityId: (int) $order->id,
            description: 'Order status updated.',
            newValues: [
                'order_number' => $order->order_number,
                'status' => $nextStatus,
            ]
        );

        return redirect()->route('orders.index')->with('success', 'Order status updated.');
    }

    public function printKot(Order $order)
    {
        if ($this->kotMode() === 'off') {
            return redirect()->route('orders.index')->withErrors([
                'order_kot' => 'KOT is disabled in Admin Settings.',
            ]);
        }

        $order->load(['items', 'customer', 'kot', 'createdBy']);

        if (!$order->kot) {
            $kot = $this->createKotForOrder($order);
            $order->setRelation('kot', $kot);
        }

        $order->kot->update([
            'printed_at' => now(),
        ]);
        $order->refresh()->load(['items', 'customer', 'kot', 'createdBy']);

        return view('orders.kot', compact('order'));
    }

    private function createKotForOrder(Order $order): Kot
    {
        $kot = Kot::create([
            'order_id' => $order->id,
            'kot_number' => 'TMP-KOT-' . uniqid(),
            'status' => 'open',
            'created_by_user_id' => auth()->id() ? (int) auth()->id() : null,
        ]);
        $kot->update([
            'kot_number' => sprintf('KOT-%s-%05d', now()->format('Ymd'), (int) $kot->id),
        ]);

        return $kot;
    }

    private function shouldCreateKotOnCreate(): bool
    {
        return $this->kotMode() === 'always';
    }

    private function shouldCreateKotOnKitchenFlow(): bool
    {
        return in_array($this->kotMode(), ['always', 'conditional'], true);
    }

    private function kotMode(): string
    {
        $mode = strtolower(trim((string) (app(BusinessSettingsService::class)->get()['kot_mode'] ?? 'always')));

        return in_array($mode, ['off', 'conditional', 'always'], true) ? $mode : 'always';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ActivityLog>  $logs
     * @return array<int, array<string, mixed>>
     */
    private function buildOrderTrail(Order $order, \Illuminate\Support\Collection $logs): array
    {
        $events = [];
        $seen = [];

        $this->appendTrailEvent($events, $seen, $order->created_at, 'Received / Created', 'Order created in system.');
        $this->appendTrailEvent($events, $seen, $order->accepted_at, 'Accepted');
        $this->appendTrailEvent($events, $seen, $order->in_kitchen_at, 'Under Preparation');
        $this->appendTrailEvent($events, $seen, $order->ready_at, 'Ready');
        $this->appendTrailEvent($events, $seen, $order->dispatched_at, 'Dispatched');
        $this->appendTrailEvent($events, $seen, $order->completed_at, 'Completed');
        $this->appendTrailEvent($events, $seen, $order->invoiced_at, 'Invoiced');
        $this->appendTrailEvent($events, $seen, $order->cancelled_at, 'Cancelled');

        foreach ($logs as $log) {
            $label = match ((string) $log->action) {
                'create' => 'Received / Created',
                'status_update' => $this->statusLabelFromLog($log),
                default => trim((string) ($log->description ?: ucfirst((string) $log->action))),
            };
            $note = trim((string) ($log->description ?? ''));
            $actor = trim((string) ($log->user?->name ?? 'System'));
            $this->appendTrailEvent($events, $seen, $log->created_at, $label, $note, $actor);
        }

        usort($events, function (array $a, array $b) {
            return strcmp((string) $a['time_sort'], (string) $b['time_sort']);
        });

        return array_map(function (array $event) {
            unset($event['time_sort']);

            return $event;
        }, $events);
    }

    private function statusLabelFromLog(ActivityLog $log): string
    {
        $status = strtolower(trim((string) ($log->new_values['status'] ?? '')));

        return match ($status) {
            'new' => 'New',
            'accepted' => 'Accepted',
            'in_kitchen' => 'Under Preparation',
            'ready' => 'Ready',
            'dispatched' => 'Dispatched',
            'completed' => 'Completed',
            'invoiced' => 'Invoiced',
            'cancelled' => 'Cancelled',
            default => trim((string) ($log->description ?: 'Status Updated')),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<string, bool>  $seen
     */
    private function appendTrailEvent(
        array &$events,
        array &$seen,
        mixed $timestamp,
        string $label,
        ?string $note = null,
        ?string $actor = null
    ): void {
        if (!$timestamp) {
            return;
        }

        $time = $timestamp instanceof \Carbon\CarbonInterface ? $timestamp : \Carbon\Carbon::parse((string) $timestamp);
        $timeSort = $time->copy()->timezone('UTC')->format('Y-m-d H:i:s.u');
        $dedupeKey = $timeSort . '|' . strtolower(trim($label));
        if (isset($seen[$dedupeKey])) {
            return;
        }
        $seen[$dedupeKey] = true;

        $events[] = [
            'label' => trim($label) !== '' ? trim($label) : 'Updated',
            'time_ist' => $time->copy()->timezone('Asia/Kolkata')->format('Y-m-d h:i A') . ' IST',
            'time_sort' => $timeSort,
            'note' => trim((string) ($note ?? '')),
            'actor' => trim((string) ($actor ?? '')),
        ];
    }
}
