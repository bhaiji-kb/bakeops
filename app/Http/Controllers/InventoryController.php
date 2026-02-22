<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Services\ActivityLogService;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        $products = Product::where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $totalStockValue = 0.0;
        $lowStockCount = 0;
        foreach ($products as $product) {
            $stock = (float) $product->currentStock();
            $totalStockValue += $stock * (float) $product->price;
            if ($stock <= (float) $product->reorder_level) {
                $lowStockCount++;
            }
        }

        $recentTransactions = InventoryTransaction::query()
            ->with('product:id,name,unit')
            ->latest()
            ->limit(25)
            ->get();

        $summary = [
            'active_products' => $products->count(),
            'low_stock' => $lowStockCount,
            'total_stock_value' => round($totalStockValue, 2),
            'recent_movements' => $recentTransactions->count(),
        ];

        return view('inventory.index', compact('products', 'summary', 'recentTransactions'));
    }

    public function addStock(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:255',
        ]);

        $product = Product::findOrFail($request->product_id);
        $inventory = new InventoryService();
        $transaction = $inventory->addStock($product, (float) $request->quantity, $request->notes);

        app(ActivityLogService::class)->log(
            module: 'inventory',
            action: 'add_stock',
            entityType: InventoryTransaction::class,
            entityId: (int) $transaction->id,
            description: 'Stock added.',
            newValues: [
                'product_id' => (int) $product->id,
                'product_name' => $product->name,
                'quantity' => (float) $request->quantity,
                'notes' => $request->notes,
            ]
        );

        return redirect()->route('inventory.index')->with('success', 'Stock added successfully');
    }

    public function recordWastage(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:255',
        ]);

        $product = Product::findOrFail($request->product_id);
        $inventory = new InventoryService();

        try {
            $transaction = $inventory->recordWastage($product, (float) $request->quantity, $request->notes);

            app(ActivityLogService::class)->log(
                module: 'inventory',
                action: 'record_wastage',
                entityType: InventoryTransaction::class,
                entityId: (int) $transaction->id,
                description: 'Wastage recorded.',
                newValues: [
                    'product_id' => (int) $product->id,
                    'product_name' => $product->name,
                    'quantity' => (float) $request->quantity,
                    'notes' => $request->notes,
                ]
            );

            return redirect()->route('inventory.index')->with('success', 'Wastage recorded successfully');
        } catch (\Exception $e) {
            return redirect()->route('inventory.index')->with('error', $e->getMessage());
        }
    }
}
