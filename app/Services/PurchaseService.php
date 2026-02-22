<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePayment;
use App\Models\Supplier;
use Exception;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function createPurchase(array $payload): Purchase
    {
        return DB::transaction(function () use ($payload) {
            $supplier = Supplier::findOrFail($payload['supplier_id']);

            $purchase = Purchase::create([
                'supplier_id' => $supplier->id,
                // Keep legacy text column in sync to avoid breaking existing data/views.
                'supplier_name' => $supplier->name,
                'bill_number' => $payload['bill_number'] ?? null,
                'purchase_date' => $payload['purchase_date'],
                'notes' => $payload['notes'] ?? null,
                'total_amount' => 0,
                'paid_amount' => 0,
                'due_amount' => 0,
                'status' => 'unpaid',
            ]);

            $inventory = new InventoryService();
            $totalAmount = 0;

            foreach ($payload['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                if ($product->type !== 'raw_material') {
                    throw new Exception("Only ingredients/raw materials can be purchased.");
                }

                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $lineTotal = round($quantity * $unitPrice, 2);
                $currentStock = (float) $product->currentStock();
                $currentUnitPrice = (float) $product->price;

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $lineTotal,
                ]);

                $inventory->addStock(
                    $product,
                    $quantity,
                    'Purchase inward',
                    'purchase',
                    $purchase->id
                );

                $newStock = $currentStock + $quantity;
                $newAverageCost = $unitPrice;
                if ($newStock > 0) {
                    $newAverageCost = round((($currentStock * $currentUnitPrice) + ($quantity * $unitPrice)) / $newStock, 2);
                }

                // Keep raw material cost close to purchase reality for production costing.
                $product->update([
                    'price' => $newAverageCost,
                ]);

                $totalAmount += $lineTotal;
            }

            $totalAmount = round($totalAmount, 2);
            $initialPaidAmount = round((float) ($payload['initial_paid_amount'] ?? 0), 2);
            if ($initialPaidAmount > $totalAmount) {
                throw new Exception('Initial paid amount cannot exceed total purchase amount.');
            }

            $dueAmount = round($totalAmount - $initialPaidAmount, 2);
            $status = $this->resolveStatus($dueAmount, $totalAmount);

            $purchase->update([
                'total_amount' => $totalAmount,
                'paid_amount' => $initialPaidAmount,
                'due_amount' => $dueAmount,
                'status' => $status,
            ]);

            if ($initialPaidAmount > 0) {
                PurchasePayment::create([
                    'purchase_id' => $purchase->id,
                    'payment_date' => $payload['purchase_date'],
                    'amount' => $initialPaidAmount,
                    'payment_mode' => $payload['initial_payment_mode'],
                    'notes' => 'Initial payment',
                ]);
            }

            return $purchase->fresh(['supplier', 'items.product', 'payments']);
        });
    }

    public function addPayment(Purchase $purchase, array $payload): Purchase
    {
        return DB::transaction(function () use ($purchase, $payload) {
            $purchase->refresh();
            $currentDue = (float) $purchase->due_amount;
            $amount = round((float) $payload['amount'], 2);

            if ($currentDue <= 0) {
                throw new Exception('This purchase has no pending due amount.');
            }

            if ($amount > $currentDue) {
                throw new Exception('Payment amount cannot exceed pending due amount.');
            }

            PurchasePayment::create([
                'purchase_id' => $purchase->id,
                'payment_date' => $payload['payment_date'],
                'amount' => $amount,
                'payment_mode' => $payload['payment_mode'],
                'notes' => $payload['notes'] ?? null,
            ]);

            $paidAmount = round((float) $purchase->paid_amount + $amount, 2);
            $dueAmount = round((float) $purchase->total_amount - $paidAmount, 2);
            $status = $this->resolveStatus($dueAmount, (float) $purchase->total_amount);

            $purchase->update([
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'status' => $status,
            ]);

            return $purchase->fresh(['supplier', 'items.product', 'payments']);
        });
    }

    private function resolveStatus(float $dueAmount, float $totalAmount): string
    {
        if ($dueAmount <= 0) {
            return 'paid';
        }

        if ($dueAmount < $totalAmount) {
            return 'partial';
        }

        return 'unpaid';
    }
}
