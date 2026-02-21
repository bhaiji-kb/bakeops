<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function createSale(array $items, string $paymentMode): Sale
    {
        return DB::transaction(function () use ($items, $paymentMode) {

            $billNumber = 'BILL-' . now()->format('YmdHis');

            $sale = Sale::create([
                'bill_number' => $billNumber,
                'total_amount' => 0,
                'payment_mode' => $paymentMode,
            ]);

            $inventory = new InventoryService();
            $totalAmount = 0;

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);

                $lineTotal = $item['quantity'] * $product->price;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'total' => $lineTotal,
                ]);

                $inventory->deductStock(
                    $product,
                    $item['quantity'],
                    'sale',
                    $sale->id
                );

                $totalAmount += $lineTotal;
            }

            $sale->update(['total_amount' => $totalAmount]);

            return $sale;
        });
    }
}
