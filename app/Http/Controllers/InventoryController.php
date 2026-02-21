<?php

namespace App\Http\Controllers;

use App\Models\Product;
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

        return view('inventory.index', compact('products'));
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
        $inventory->addStock($product, (float) $request->quantity, $request->notes);

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
            $inventory->recordWastage($product, (float) $request->quantity, $request->notes);
            return redirect()->route('inventory.index')->with('success', 'Wastage recorded successfully');
        } catch (\Exception $e) {
            return redirect()->route('inventory.index')->with('error', $e->getMessage());
        }
    }
}