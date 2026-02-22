<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogService;
use App\Services\BillingService;
use App\Services\BusinessSettingsService;
use App\Services\CustomerMasterService;
use App\Services\OrderAutomationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Throwable;

class POSController extends Controller
{
    private const ORDER_SOURCES = [
        'outlet',
        'phone',
        'whatsapp',
        'swiggy',
        'zomato',
        'other',
    ];

    public function index()
    {
        $oldItems = collect(old('items', []));
        $productIds = $oldItems
            ->pluck('product_id')
            ->filter(fn ($value) => is_numeric($value) && (int) $value > 0)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        $productsById = Product::query()
            ->whereIn('id', $productIds)
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
                $initialByProductId[$productId] = $this->mapProductForPos($productsById->get($productId));
                $initialByProductId[$productId]['quantity'] = 0;
            }
            $initialByProductId[$productId]['quantity'] += $quantity;
        }

        $initialItems = array_values($initialByProductId);
        $orderSources = self::ORDER_SOURCES;
        $businessSettings = app(BusinessSettingsService::class)->get();

        return view('pos.index', compact('initialItems', 'orderSources', 'businessSettings'));
    }

    public function productByCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20',
        ]);

        $rawCode = (string) $validated['code'];
        $normalizedCode = $this->normalizeLookupCode($rawCode);
        if ($normalizedCode === '') {
            return response()->json([
                'message' => 'Product code is required.',
            ], 422);
        }

        $numericShortcut = $this->normalizeNumericShortcut($rawCode);
        $fgFromNumeric = $numericShortcut !== '' ? 'FG' . str_pad($numericShortcut, 3, '0', STR_PAD_LEFT) : null;

        $product = Product::query()
            ->where('type', 'finished_good')
            ->where('is_active', true)
            ->where(function ($query) use ($normalizedCode, $numericShortcut, $fgFromNumeric) {
                $query->where('code', $normalizedCode)
                    ->orWhere('legacy_code', $normalizedCode);

                if ($numericShortcut !== '') {
                    $query->orWhere('legacy_code', str_pad($numericShortcut, 2, '0', STR_PAD_LEFT));
                }
                if ($fgFromNumeric !== null) {
                    $query->orWhere('code', $fgFromNumeric);
                }
            })
            ->first();

        if (!$product) {
            return response()->json([
                'message' => 'No active finished product found for code ' . $normalizedCode . '.',
            ], 404);
        }

        return response()->json($this->mapProductForPos($product));
    }

    public function productSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|max:100',
        ]);

        $q = trim((string) $validated['q']);
        if ($q === '') {
            return response()->json([]);
        }

        $qLower = mb_strtolower($q);

        $numericShortcut = $this->normalizeNumericShortcut($q);
        $fgFromNumeric = $numericShortcut !== '' ? 'FG' . str_pad($numericShortcut, 3, '0', STR_PAD_LEFT) : null;

        $products = Product::query()
            ->where('type', 'finished_good')
            ->where('is_active', true)
            ->where(function ($query) use ($qLower, $numericShortcut, $fgFromNumeric) {
                $query->whereRaw('LOWER(code) like ?', [$qLower . '%'])
                    ->orWhereRaw('LOWER(COALESCE(legacy_code, \'\')) like ?', [$qLower . '%'])
                    ->orWhereRaw('LOWER(name) like ?', ['%' . $qLower . '%']);

                if ($fgFromNumeric !== null) {
                    $query->orWhereRaw('LOWER(code) = ?', [mb_strtolower($fgFromNumeric)]);
                }
            })
            ->orderBy('code')
            ->limit(8)
            ->get()
            ->map(fn (Product $product) => $this->mapProductForPos($product))
            ->values();

        return response()->json($products);
    }

    public function customerLookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => 'required|string|max:40',
        ]);

        $normalizedIdentifier = $this->normalizeCustomerIdentifier((string) $validated['identifier']);
        if ($normalizedIdentifier === '') {
            return response()->json([
                'message' => 'Identifier is required.',
            ], 422);
        }

        $customer = Customer::query()
            ->where('is_active', true)
            ->where(function ($query) use ($normalizedIdentifier) {
                $query->where('mobile', $normalizedIdentifier)
                    ->orWhereRaw('UPPER(COALESCE(identifier, \'\')) = ?', [strtoupper($normalizedIdentifier)]);
            })
            ->first();

        $salesQuery = Sale::query();
        if ($customer) {
            $salesQuery->where(function ($query) use ($customer, $normalizedIdentifier) {
                $query->where('customer_id', $customer->id)
                    ->orWhere('customer_identifier', $normalizedIdentifier);
            });
        } else {
            $salesQuery->where('customer_identifier', $normalizedIdentifier);
        }

        $salesCount = (clone $salesQuery)->count();
        $totalSpent = (float) (clone $salesQuery)->sum('total_amount');
        $lastSale = (clone $salesQuery)->latest('id')->first([
            'id',
            'bill_number',
            'created_at',
            'total_amount',
            'order_source',
            'customer_name_snapshot',
            'customer_address_snapshot',
        ]);
        $recentSales = (clone $salesQuery)
            ->latest('id')
            ->limit(5)
            ->get(['id', 'bill_number', 'created_at', 'total_amount', 'order_source'])
            ->map(function (Sale $sale) {
                return [
                    'id' => (int) $sale->id,
                    'bill_number' => (string) $sale->bill_number,
                    'date' => $sale->created_at?->format('Y-m-d H:i'),
                    'total_amount' => (float) $sale->total_amount,
                    'order_source' => (string) ($sale->order_source ?: 'outlet'),
                ];
            })
            ->values();

        $favoriteItems = collect();
        if ($salesCount > 0) {
            $saleIds = (clone $salesQuery)
                ->latest('id')
                ->limit(300)
                ->pluck('id');

            if ($saleIds->isNotEmpty()) {
                $favoriteItems = DB::table('sale_items')
                    ->join('products', 'products.id', '=', 'sale_items.product_id')
                    ->whereIn('sale_items.sale_id', $saleIds->all())
                    ->selectRaw('sale_items.product_id, products.code, products.name, SUM(sale_items.quantity) as total_qty, COUNT(DISTINCT sale_items.sale_id) as order_count')
                    ->groupBy('sale_items.product_id', 'products.code', 'products.name')
                    ->orderByDesc('total_qty')
                    ->limit(5)
                    ->get()
                    ->map(function ($row) {
                        return [
                            'product_id' => (int) $row->product_id,
                            'code' => (string) $row->code,
                            'name' => (string) $row->name,
                            'total_qty' => round((float) $row->total_qty, 2),
                            'order_count' => (int) $row->order_count,
                        ];
                    })
                    ->values();
            }
        }
        $customerMaster = app(CustomerMasterService::class);

        return response()->json([
            'found' => $customer !== null || $salesCount > 0,
            'is_repeat_customer' => $salesCount > 0,
            'identifier' => $normalizedIdentifier,
            'customer' => $customer ? [
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'mobile' => $customer->mobile,
                'identifier' => $customer->identifier,
                'email' => $customer->email,
                'address' => $customerMaster->buildAddressFromCustomer($customer),
                'address_line1' => $customer->address_line1,
                'road' => $customer->road,
                'sector' => $customer->sector,
                'city' => $customer->city,
                'pincode' => $customer->pincode,
                'preference' => $customer->preference ?: $customer->notes,
            ] : null,
            'stats' => [
                'sales_count' => $salesCount,
                'total_spent' => round($totalSpent, 2),
                'last_sale' => $lastSale ? [
                    'bill_number' => (string) $lastSale->bill_number,
                    'date' => $lastSale->created_at?->format('Y-m-d H:i'),
                    'total_amount' => (float) $lastSale->total_amount,
                    'order_source' => (string) ($lastSale->order_source ?: 'outlet'),
                    'customer_name' => $lastSale->customer_name_snapshot,
                    'customer_address' => $lastSale->customer_address_snapshot,
                ] : null,
            ],
            'recent_sales' => $recentSales,
            'favorite_items' => $favoriteItems,
        ]);
    }

    public function checkout(Request $request)
    {
        $submitAction = strtolower(trim((string) $request->input('submit_action', 'invoice')));
        if (!in_array($submitAction, ['invoice', 'kot'], true)) {
            $submitAction = 'invoice';
        }

        $orderSyncWarning = null;
        $validated = $request->validate([
            'submit_action' => ['nullable', Rule::in(['invoice', 'kot'])],
            'order_id' => [
                'nullable',
                'integer',
                Rule::exists('orders', 'id'),
            ],
            'items' => 'nullable|array|min:1',
            'items.*.product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where(function ($query) {
                    $query->where('type', 'finished_good')->where('is_active', true);
                }),
            ],
            'items.*.quantity' => 'nullable|numeric|min:0',
            'payment_mode' => [
                Rule::requiredIf($submitAction === 'invoice'),
                'nullable',
                'in:cash,upi,card',
            ],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(function ($query) {
                    $query->where('is_active', true);
                }),
            ],
            'customer_identifier' => [
                'nullable',
                'string',
                'max:40',
                Rule::requiredIf(function () use ($request) {
                    return $this->isDeliveryOrderSource((string) $request->input('order_source', 'outlet'));
                }),
            ],
            'customer_name' => 'nullable|string|max:255',
            'customer_address' => 'nullable|string|max:500',
            'customer_address_line1' => 'nullable|string|max:255',
            'customer_road' => 'nullable|string|max:255',
            'customer_sector' => 'nullable|string|max:120',
            'customer_city' => 'nullable|string|max:120',
            'customer_pincode' => 'nullable|string|regex:/^\d{6}$/',
            'customer_preference' => 'nullable|string|max:255',
            'order_source' => ['nullable', Rule::in(self::ORDER_SOURCES)],
            'order_reference' => 'nullable|string|max:60',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'round_off' => 'nullable|numeric|min:-9999.99|max:9999.99',
            'paid_amount' => 'nullable|numeric|min:0',
        ]);

        $checkoutItems = is_array($validated['items'] ?? null) ? $validated['items'] : [];
        $order = null;
        $fromOrderCheckout = !empty($validated['order_id']);
        $paidAmountForInvoice = $validated['paid_amount'] ?? null;

        if ($fromOrderCheckout && $submitAction !== 'invoice') {
            return redirect()->back()->withInput()->withErrors([
                'submit_action' => 'Order queue checkout supports invoice action only.',
            ]);
        }
        if ($fromOrderCheckout) {
            $order = Order::query()
                ->with('items')
                ->where('id', (int) $validated['order_id'])
                ->first();

            if (!$order) {
                return redirect()->back()->withInput()->withErrors([
                    'order_id' => 'Selected order was not found.',
                ]);
            }
            if ($order->sale_id) {
                return redirect()->back()->withInput()->withErrors([
                    'order_id' => 'Invoice already exists for this order.',
                ]);
            }
            if (!in_array((string) $order->status, ['completed', 'ready', 'dispatched'], true)) {
                return redirect()->back()->withInput()->withErrors([
                    'order_id' => 'Only ready/dispatched/completed orders can be converted to invoice.',
                ]);
            }

            $checkoutItems = $order->items
                ->map(function ($item) {
                    return [
                        'product_id' => (int) ($item->product_id ?? 0),
                        'quantity' => (float) ($item->quantity ?? 0),
                    ];
                })
                ->filter(fn (array $item) => $item['product_id'] > 0 && $item['quantity'] > 0)
                ->values()
                ->all();

            if (empty($checkoutItems)) {
                return redirect()->back()->withInput()->withErrors([
                    'order_id' => 'Order has no valid items to invoice.',
                ]);
            }

            $customerContext = [
                'customer_id' => $order->customer_id ? (int) $order->customer_id : null,
                'customer_identifier' => $order->customer_identifier,
                'customer_name_snapshot' => $order->customer_name,
                'customer_address_snapshot' => $order->customer_address,
                'order_source' => (string) ($order->source ?: 'outlet'),
                'order_reference' => (string) ($order->order_number ?: ''),
                'created_by_user_id' => auth()->id() ? (int) auth()->id() : null,
            ];
            if (($paidAmountForInvoice === null || $paidAmountForInvoice === '') && (float) ($order->advance_paid_amount ?? 0) > 0) {
                $paidAmountForInvoice = (float) $order->advance_paid_amount;
            }
        } else {
            if (empty($checkoutItems)) {
                return redirect()->back()->withInput()->withErrors([
                    'items' => 'Add at least one item for checkout.',
                ]);
            }
            $customerContext = $this->resolveCustomerContext($validated);
            $customerContext['created_by_user_id'] = auth()->id() ? (int) auth()->id() : null;
        }

        if ($submitAction === 'kot') {
            try {
                $this->ensureRequiredDeliveryContext($customerContext);
                $order = $this->createOrderAndKotFromPosDraft(
                    $checkoutItems,
                    $customerContext,
                    'Created from POS: Send to Kitchen.'
                );
            } catch (ValidationException $e) {
                return redirect()->back()->withInput()->withErrors($e->errors());
            } catch (Throwable $e) {
                return redirect()->back()->withInput()->withErrors([
                    'checkout' => $e->getMessage(),
                ]);
            }

            app(ActivityLogService::class)->log(
                module: 'orders',
                action: 'create_from_pos_kot',
                entityType: Order::class,
                entityId: (int) $order->id,
                description: 'Order sent to kitchen from POS.',
                newValues: [
                    'order_number' => $order->order_number,
                    'source' => $order->source,
                    'status' => $order->status,
                    'items_count' => $order->items()->count(),
                ]
            );

            return redirect()
                ->route('orders.index')
                ->with('success', 'Order sent to kitchen successfully.')
                ->with('created_order_id', (int) $order->id);
        }

        $businessSettings = app(BusinessSettingsService::class)->get();
        $gstEnabled = (bool) ($businessSettings['gst_enabled'] ?? false);
        $taxAmount = $gstEnabled ? ($validated['tax_amount'] ?? 0) : 0;

        try {
            $this->ensureRequiredDeliveryContext($customerContext);

            $sale = (new BillingService())->createSale(
                $checkoutItems,
                $validated['payment_mode'],
                [
                    'discount_amount' => $validated['discount_amount'] ?? 0,
                    'tax_amount' => $taxAmount,
                    'round_off' => $validated['round_off'] ?? 0,
                    'paid_amount' => $paidAmountForInvoice,
                ],
                $customerContext
            );

            if ($order) {
                $order->update([
                    'sale_id' => $sale->id,
                    'status' => 'invoiced',
                    'invoiced_at' => now(),
                ]);
                $order->kot()?->update([
                    'status' => 'closed',
                ]);

                app(ActivityLogService::class)->log(
                    module: 'orders',
                    action: 'status_update',
                    entityType: Order::class,
                    entityId: (int) $order->id,
                    description: 'Order invoiced from order queue.',
                    newValues: [
                        'order_number' => $order->order_number,
                        'status' => 'invoiced',
                        'sale_id' => (int) $sale->id,
                        'bill_number' => $sale->bill_number,
                    ]
                );
            } elseif ($this->shouldAutoCreateOperationalOrderFromSale($sale)) {
                try {
                    $order = $this->createOperationalOrderFromSale($sale);
                } catch (Throwable $syncException) {
                    $orderSyncWarning = 'Sale completed, but order sync failed.';
                }
            }
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        } catch (Throwable $e) {
            return redirect()->back()->withInput()->withErrors([
                'checkout' => $e->getMessage(),
            ]);
        }

        app(ActivityLogService::class)->log(
            module: 'pos',
            action: 'checkout',
            entityType: Sale::class,
            entityId: (int) $sale->id,
            description: 'POS sale completed.',
            newValues: [
                'bill_number' => $sale->bill_number,
                'payment_mode' => $sale->payment_mode,
                'customer_id' => $sale->customer_id,
                'created_by_user_id' => $sale->created_by_user_id,
                'customer_identifier' => $sale->customer_identifier,
                'customer_name_snapshot' => $sale->customer_name_snapshot,
                'customer_address_snapshot' => $sale->customer_address_snapshot,
                'order_source' => $sale->order_source,
                'order_reference' => $sale->order_reference,
                'sub_total' => (float) $sale->sub_total,
                'discount_amount' => (float) $sale->discount_amount,
                'tax_amount' => (float) $sale->tax_amount,
                'round_off' => (float) $sale->round_off,
                'total_amount' => (float) $sale->total_amount,
                'paid_amount' => (float) $sale->paid_amount,
                'balance_amount' => (float) $sale->balance_amount,
                'total_cost' => (float) $sale->items->sum('cost_total'),
                'gross_margin' => (float) $sale->total_amount - (float) $sale->items->sum('cost_total'),
                'items_count' => count($checkoutItems),
                'order_id' => $order?->id,
            ]
        );

        if ($fromOrderCheckout) {
            return redirect()
                ->route('orders.index')
                ->with('success', 'Order invoiced successfully.')
                ->with('invoice_sale_id', (int) $sale->id);
        }

        $redirect = redirect()
            ->route('pos.index')
            ->with('success', 'Sale completed')
            ->with('invoice_sale_id', (int) $sale->id);
        if ($orderSyncWarning !== null) {
            $redirect->with('warning', $orderSyncWarning);
        }

        return $redirect;
    }

    public function salesHistory(Request $request)
    {
        $dateFrom = trim((string) $request->get('date_from', ''));
        $dateTo = trim((string) $request->get('date_to', ''));
        $quickRange = strtolower(trim((string) $request->get('quick_range', '')));
        if (!in_array($quickRange, ['today', 'last_7_days', 'this_month', 'custom'], true)) {
            $quickRange = '';
        }
        if ($quickRange === 'today') {
            $dateFrom = now()->toDateString();
            $dateTo = $dateFrom;
        } elseif ($quickRange === 'last_7_days') {
            $dateTo = now()->toDateString();
            $dateFrom = now()->subDays(6)->toDateString();
        } elseif ($quickRange === 'this_month') {
            $dateFrom = now()->startOfMonth()->toDateString();
            $dateTo = now()->toDateString();
        } elseif (($dateFrom !== '' || $dateTo !== '') && $quickRange === '') {
            $quickRange = 'custom';
        }

        $invoice = trim((string) $request->get('invoice', ''));
        $customerIdentifier = trim((string) $request->get('customer_identifier', ''));
        $orderSource = trim((string) $request->get('order_source', ''));
        $orderSources = self::ORDER_SOURCES;

        $query = Sale::query()
            ->with(['items', 'customer'])
            ->orderByDesc('id');

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if ($invoice !== '') {
            $query->where('bill_number', 'like', '%' . $invoice . '%');
        }
        if ($customerIdentifier !== '') {
            $normalizedIdentifier = $this->normalizeCustomerIdentifier($customerIdentifier);
            $query->where(function ($inner) use ($normalizedIdentifier) {
                $qLike = '%' . mb_strtolower($normalizedIdentifier) . '%';
                $inner->whereRaw('LOWER(COALESCE(customer_identifier, \'\')) like ?', [$qLike])
                    ->orWhereRaw('LOWER(COALESCE(customer_name_snapshot, \'\')) like ?', [$qLike])
                    ->orWhereHas('customer', function ($customerQuery) use ($qLike) {
                        $customerQuery
                            ->whereRaw('LOWER(name) like ?', [$qLike])
                            ->orWhereRaw('LOWER(COALESCE(mobile, \'\')) like ?', [$qLike])
                            ->orWhereRaw('LOWER(COALESCE(identifier, \'\')) like ?', [$qLike]);
                    });
            });
        }
        if ($orderSource !== '' && in_array($orderSource, self::ORDER_SOURCES, true)) {
            $query->where('order_source', $orderSource);
        }

        $sales = $query->limit(200)->get();

        return view('pos.sales_history', compact(
            'sales',
            'dateFrom',
            'dateTo',
            'quickRange',
            'invoice',
            'customerIdentifier',
            'orderSource',
            'orderSources'
        ));
    }

    public function invoice(Sale $sale)
    {
        $sale->load(['items.product', 'customer', 'createdBy']);
        $businessSettings = app(BusinessSettingsService::class)->get();

        return view('pos.invoice', compact('sale', 'businessSettings'));
    }

    public function invoicePdf(Sale $sale)
    {
        $sale->load(['items.product', 'customer', 'createdBy']);
        $businessSettings = app(BusinessSettingsService::class)->get();

        $filename = sprintf('invoice-%s.pdf', $sale->bill_number);
        $pdf = Pdf::loadView('pos.invoice_pdf', compact('sale', 'businessSettings'))->setPaper('a4');

        return $pdf->download($filename);
    }

    private function mapProductForPos(Product $product): array
    {
        $shortCode = $this->deriveShortCodeFromPrimary((string) $product->code);
        if ($shortCode === null || trim($shortCode) === '') {
            $shortCode = $product->legacy_code ? (string) $product->legacy_code : null;
        }

        $displayCode = (string) $product->code;
        if ($shortCode !== null && trim($shortCode) !== '') {
            $displayCode .= ' | ' . $shortCode;
        }

        return [
            'id' => (int) $product->id,
            'code' => (string) $product->code,
            'legacy_code' => $shortCode,
            'display_code' => $displayCode,
            'name' => (string) $product->name,
            'price' => (float) $product->price,
            'unit' => (string) $product->unit,
            'stock' => round($product->currentStock(), 2),
        ];
    }

    private function resolveCustomerContext(array $validated): array
    {
        $customerMaster = app(CustomerMasterService::class);
        $identifier = $customerMaster->normalizeIdentifier((string) ($validated['customer_identifier'] ?? ''));
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
        $orderSource = (string) ($validated['order_source'] ?? 'outlet');
        $orderReference = $this->generateOrderReference(
            $orderSource,
            trim((string) ($validated['order_reference'] ?? ''))
        );

        $customer = null;
        if (!empty($validated['customer_id'])) {
            $customer = Customer::query()
                ->where('id', (int) $validated['customer_id'])
                ->where('is_active', true)
                ->first();
        }

        if (!$customer && $identifier !== '') {
            $customer = Customer::query()
                ->where(function ($query) use ($identifier) {
                    $query->where('mobile', $identifier)
                        ->orWhereRaw('UPPER(COALESCE(identifier, \'\')) = ?', [strtoupper($identifier)]);
                })
                ->first();
        }

        if (
            !$customer
            && $identifier !== ''
            && ($customerName !== '' || $customerAddress !== '' || $customerProfile['preference'] !== '')
        ) {
            $customer = $customerMaster->upsertByIdentifier($identifier, $customerName, $customerProfile);
        }

        if ($customer) {
            $customer = $customerMaster->syncProfile($customer, $customerName, $customerProfile);
            if ($identifier === '') {
                $identifier = (string) ($customer->mobile ?: $customer->identifier ?: '');
            }
            $customerName = (string) ($customer->name ?: $customerName);
            $customerAddress = $customerMaster->buildAddressFromCustomer($customer);
        }

        if ($customerAddress === '') {
            $customerAddress = $customerMaster->composeAddress($customerProfile);
        }

        return [
            'customer_id' => $customer ? (int) $customer->id : null,
            'customer_identifier' => $identifier !== '' ? $identifier : null,
            'customer_name_snapshot' => $customerName !== '' ? $customerName : null,
            'customer_address_snapshot' => $customerAddress !== '' ? $customerAddress : null,
            'order_source' => in_array($orderSource, self::ORDER_SOURCES, true) ? $orderSource : 'outlet',
            'channel' => in_array($orderSource, ['swiggy', 'zomato'], true) ? $orderSource : null,
            'external_order_id' => in_array($orderSource, ['swiggy', 'zomato'], true) && $orderReference !== '' ? $orderReference : null,
            'order_reference' => $orderReference !== '' ? $orderReference : null,
        ];
    }

    private function normalizeLookupCode(string $value): string
    {
        $normalized = strtoupper(trim($value));
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^\d{1,3}$/', $normalized)) {
            return str_pad($normalized, 2, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^(FG|RM|PK)(\d{1,3})$/', $normalized, $matches)) {
            return $matches[1] . str_pad($matches[2], 3, '0', STR_PAD_LEFT);
        }

        return $normalized;
    }

    private function normalizeNumericShortcut(string $value): string
    {
        $normalized = strtoupper(trim($value));
        if (preg_match('/^\d{1,3}$/', $normalized)) {
            return ltrim($normalized, '0') === '' ? '0' : ltrim($normalized, '0');
        }

        if (preg_match('/^(FG|RM|PK)(\d{1,3})$/', $normalized, $matches)) {
            return ltrim($matches[2], '0') === '' ? '0' : ltrim($matches[2], '0');
        }

        return '';
    }

    private function deriveShortCodeFromPrimary(string $code): ?string
    {
        if (!preg_match('/^[A-Z]{2}(\d{1,3})$/', strtoupper(trim($code)), $matches)) {
            return null;
        }

        return str_pad((string) ((int) $matches[1]), 3, '0', STR_PAD_LEFT);
    }

    private function normalizeCustomerIdentifier(string $value): string
    {
        return app(CustomerMasterService::class)->normalizeIdentifier($value);
    }

    private function generateOrderReference(string $orderSource, string $providedReference): string
    {
        $providedReference = strtoupper(trim($providedReference));
        if ($providedReference !== '') {
            return $providedReference;
        }

        $prefix = strtoupper(substr($orderSource !== '' ? $orderSource : 'outlet', 0, 3));
        $prefix = preg_replace('/[^A-Z]/', '', $prefix) ?: 'ORD';

        return sprintf(
            '%s-%s-%04d',
            $prefix,
            now()->format('YmdHis'),
            random_int(1000, 9999)
        );
    }

    private function isDeliveryOrderSource(string $orderSource): bool
    {
        return in_array(strtolower(trim($orderSource)), ['phone', 'whatsapp', 'swiggy', 'zomato', 'other'], true);
    }

    private function ensureRequiredDeliveryContext(array $customerContext): void
    {
        if (!$this->isDeliveryOrderSource((string) ($customerContext['order_source'] ?? 'outlet'))) {
            return;
        }

        $errors = [];
        if (trim((string) ($customerContext['customer_identifier'] ?? '')) === '') {
            $errors['customer_identifier'] = 'Customer mobile is required for delivery orders.';
        }
        if (trim((string) ($customerContext['customer_name_snapshot'] ?? '')) === '') {
            $errors['customer_name'] = 'Customer name is required for delivery orders.';
        }
        if (trim((string) ($customerContext['customer_address_snapshot'] ?? '')) === '') {
            $errors['customer_address_line1'] = 'Customer address is required for delivery orders.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $checkoutItems
     * @param  array<string, mixed>  $customerContext
     */
    private function createOrderAndKotFromPosDraft(array $checkoutItems, array $customerContext, string $notes): Order
    {
        $normalizedItems = collect($checkoutItems)
            ->map(function (array $row) {
                return [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'quantity' => round((float) ($row['quantity'] ?? 0), 3),
                ];
            })
            ->filter(fn (array $row) => $row['product_id'] > 0 && $row['quantity'] > 0)
            ->values();

        if ($normalizedItems->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one item before sending order to kitchen.',
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
                'items' => 'Only active finished goods can be added to kitchen orders.',
            ]);
        }

        $lineItems = $normalizedItems->map(function (array $row) use ($products) {
            /** @var Product $product */
            $product = $products->get((int) $row['product_id']);
            $quantity = (float) $row['quantity'];
            $unitPrice = round((float) $product->price, 2);

            return [
                'product_id' => (int) $product->id,
                'product_code' => (string) ($product->code ?? ''),
                'item_name' => (string) $product->name,
                'unit' => (string) ($product->unit ?? 'pcs'),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($unitPrice * $quantity, 2),
            ];
        })->values()->all();

        return app(OrderAutomationService::class)->createOrderWithKot(
            orderAttributes: [
                'source' => (string) ($customerContext['order_source'] ?? 'outlet'),
                'status' => 'in_kitchen',
                'customer_id' => $customerContext['customer_id'] ?? null,
                'customer_identifier' => $customerContext['customer_identifier'] ?? null,
                'customer_name' => $customerContext['customer_name_snapshot'] ?? null,
                'customer_address' => $customerContext['customer_address_snapshot'] ?? null,
                'notes' => trim($notes . ' Ref: ' . (string) ($customerContext['order_reference'] ?? '')),
                'created_by_user_id' => auth()->id() ? (int) auth()->id() : null,
                'accepted_at' => now(),
                'in_kitchen_at' => now(),
            ],
            lineItems: $lineItems,
            kotAttributes: [
                'status' => 'open',
                'created_by_user_id' => auth()->id() ? (int) auth()->id() : null,
            ],
        );
    }

    private function shouldAutoCreateOperationalOrderFromSale(Sale $sale): bool
    {
        $source = strtolower(trim((string) ($sale->order_source ?: 'outlet')));

        return in_array($source, ['outlet', 'other'], true);
    }

    private function createOperationalOrderFromSale(Sale $sale): Order
    {
        $existingOrder = Order::query()
            ->where('sale_id', $sale->id)
            ->first();
        if ($existingOrder) {
            return $existingOrder;
        }

        $sale->loadMissing(['items.product', 'customer']);
        $source = strtolower(trim((string) ($sale->order_source ?: 'outlet')));
        if (!in_array($source, self::ORDER_SOURCES, true)) {
            $source = 'other';
        }

        $orderTimestamp = $sale->created_at ?: now();
        $lineItems = $sale->items
            ->map(function ($saleItem) {
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
            })
            ->values()
            ->all();

        $order = app(OrderAutomationService::class)->createOrderWithKot(
            orderAttributes: [
                'source' => $source,
                'status' => 'invoiced',
                'customer_id' => $sale->customer_id,
                'customer_identifier' => $sale->customer_identifier,
                'customer_name' => $sale->customer_name_snapshot,
                'customer_address' => $sale->customer_address_snapshot,
                'notes' => 'Auto-created from POS invoice ' . $sale->bill_number,
                'sale_id' => $sale->id,
                'created_by_user_id' => $sale->created_by_user_id ?: (auth()->id() ? (int) auth()->id() : null),
                'accepted_at' => $orderTimestamp,
                'in_kitchen_at' => $orderTimestamp,
                'ready_at' => $orderTimestamp,
                'dispatched_at' => $orderTimestamp,
                'completed_at' => $orderTimestamp,
                'invoiced_at' => $orderTimestamp,
                'fallback_total' => (float) $sale->total_amount,
            ],
            lineItems: $lineItems,
            kotAttributes: [
                'status' => 'closed',
                'created_by_user_id' => $sale->created_by_user_id ?: (auth()->id() ? (int) auth()->id() : null),
            ],
        );

        app(ActivityLogService::class)->log(
            module: 'orders',
            action: 'auto_create_from_pos_sale',
            entityType: Order::class,
            entityId: (int) $order->id,
            description: 'Operational order auto-created from POS invoice.',
            newValues: [
                'order_number' => $order->order_number,
                'sale_id' => $sale->id,
                'bill_number' => $sale->bill_number,
                'source' => $order->source,
                'status' => $order->status,
            ]
        );

        return $order;
    }
}
