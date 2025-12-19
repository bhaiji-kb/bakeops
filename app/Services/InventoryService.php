<?php

namespace App\Services;

use App\Models\Product;
use App\Models\InventoryTransaction;
use Exception;

class InventoryService
{
    public function addStock(
        Product $product,
        float $quantity,
        string $notes = null
    ): InventoryTransaction {
        return InventoryTransaction::create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'transaction_type' => 'IN',
            'notes' => $notes,
        ]);
    }

    public function deductStock(
        Product $product,
        float $quantity,
        string $referenceType = null,
        int $referenceId = null
    ): InventoryTransaction {
        if ($product->currentStock() < $quantity) {
            throw new Exception("Insufficient stock for {$product->name}");
        }

        return InventoryTransaction::create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'transaction_type' => 'OUT',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    public function recordWastage(
        Product $product,
        float $quantity,
        string $notes = null
    ): InventoryTransaction {
        if ($product->currentStock() < $quantity) {
            throw new Exception("Cannot waste more than available stock");
        }

        return InventoryTransaction::create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'transaction_type' => 'WASTE',
            'notes' => $notes,
        ]);
    }
}
