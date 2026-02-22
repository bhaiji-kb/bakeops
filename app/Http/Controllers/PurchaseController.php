<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use App\Services\ActivityLogService;
use App\Services\PurchaseService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    private const PAYMENT_MODES = ['cash', 'upi', 'card', 'bank'];

    public function index(Request $request)
    {
        $month = $request->get('month', now()->format('Y-m'));
        [$month, $start, $end] = $this->resolveMonthRange($month);

        $rawMaterials = Product::where('type', 'raw_material')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $purchases = Purchase::whereBetween('purchase_date', [$start->toDateString(), $end->toDateString()])
            ->with('supplier')
            ->orderBy('purchase_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $totals = [
            'total_purchase' => $purchases->sum('total_amount'),
            'total_paid' => $purchases->sum('paid_amount'),
            'total_due' => $purchases->sum('due_amount'),
        ];

        $paymentModes = self::PAYMENT_MODES;

        return view('purchases.index', compact('month', 'rawMaterials', 'suppliers', 'purchases', 'totals', 'paymentModes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'bill_number' => 'nullable|string|max:100',
            'purchase_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'initial_paid_amount' => 'nullable|numeric|min:0',
            'initial_payment_mode' => 'nullable|in:' . implode(',', self::PAYMENT_MODES),
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $itemExtraction = $this->extractItems($validated['items']);
        $cleanItems = $itemExtraction['items'];
        if ($itemExtraction['has_partial']) {
            return redirect()->back()->withInput()->withErrors([
                'items' => 'For each selected ingredient, fill both quantity and unit price.',
            ]);
        }

        if (count($cleanItems) === 0) {
            return redirect()->back()->withInput()->withErrors([
                'items' => 'Add at least one ingredient with quantity and unit price.',
            ]);
        }

        $initialPaidAmount = (float) ($validated['initial_paid_amount'] ?? 0);
        if ($initialPaidAmount > 0 && empty($validated['initial_payment_mode'])) {
            return redirect()->back()->withInput()->withErrors([
                'initial_payment_mode' => 'Choose payment mode when initial paid amount is greater than 0.',
            ]);
        }

        $payload = [
            'supplier_id' => (int) $validated['supplier_id'],
            'bill_number' => $validated['bill_number'] ?? null,
            'purchase_date' => $validated['purchase_date'],
            'notes' => $validated['notes'] ?? null,
            'initial_paid_amount' => $initialPaidAmount,
            'initial_payment_mode' => $validated['initial_payment_mode'] ?? null,
            'items' => $cleanItems,
        ];

        try {
            $purchase = (new PurchaseService())->createPurchase($payload);

            app(ActivityLogService::class)->log(
                module: 'purchases',
                action: 'create_bill',
                entityType: Purchase::class,
                entityId: (int) $purchase->id,
                description: 'Purchase bill created.',
                newValues: [
                    'supplier_id' => (int) $purchase->supplier_id,
                    'supplier_name' => $purchase->supplier->name ?? $purchase->supplier_name,
                    'purchase_date' => $purchase->purchase_date?->toDateString(),
                    'total_amount' => (float) $purchase->total_amount,
                    'paid_amount' => (float) $purchase->paid_amount,
                    'due_amount' => (float) $purchase->due_amount,
                    'status' => $purchase->status,
                ]
            );

            return redirect()
                ->route('purchases.show', ['purchase' => $purchase->id])
                ->with('success', 'Purchase bill created and stock inward updated.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->withErrors([
                'purchase' => $e->getMessage(),
            ]);
        }
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'items.product', 'payments']);
        $paymentModes = self::PAYMENT_MODES;

        return view('purchases.show', compact('purchase', 'paymentModes'));
    }

    public function addPayment(Request $request, Purchase $purchase)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'required|in:' . implode(',', self::PAYMENT_MODES),
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $oldDue = (float) $purchase->due_amount;
            $updatedPurchase = (new PurchaseService())->addPayment($purchase, $validated);

            $latestPayment = PurchasePayment::where('purchase_id', $updatedPurchase->id)
                ->orderByDesc('id')
                ->first();

            app(ActivityLogService::class)->log(
                module: 'purchases',
                action: 'add_payment',
                entityType: PurchasePayment::class,
                entityId: $latestPayment ? (int) $latestPayment->id : null,
                description: 'Purchase payment recorded.',
                oldValues: [
                    'due_amount' => $oldDue,
                ],
                newValues: [
                    'purchase_id' => (int) $updatedPurchase->id,
                    'payment_date' => $validated['payment_date'],
                    'payment_mode' => $validated['payment_mode'],
                    'amount' => (float) $validated['amount'],
                    'due_amount' => (float) $updatedPurchase->due_amount,
                    'status' => $updatedPurchase->status,
                ]
            );

            return redirect()
                ->route('purchases.show', ['purchase' => $purchase->id])
                ->with('success', 'Payment recorded successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->withErrors([
                'payment' => $e->getMessage(),
            ]);
        }
    }

    private function extractItems(array $items): array
    {
        $cleanItems = [];
        $hasPartial = false;

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);

            if ($quantity <= 0 && $unitPrice <= 0) {
                continue;
            }

            if ($quantity <= 0 || $unitPrice <= 0) {
                $hasPartial = true;
                continue;
            }

            $cleanItems[] = [
                'product_id' => (int) $item['product_id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ];
        }

        return [
            'items' => $cleanItems,
            'has_partial' => $hasPartial,
        ];
    }

    private function resolveMonthRange(string $month): array
    {
        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $end = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
            return [$month, $start, $end];
        } catch (\Exception $e) {
            $currentMonth = now()->format('Y-m');
            return [$currentMonth, now()->startOfMonth(), now()->endOfMonth()];
        }
    }
}
