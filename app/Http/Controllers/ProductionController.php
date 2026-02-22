<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\RecipeItem;
use App\Services\ActivityLogService;
use App\Services\ProductionService;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        $finishedGoods = Product::query()
            ->where('type', 'finished_good')
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $selectedProductId = (int) $request->get('product_id', 0);
        $selectedQuantity = (float) $request->get('quantity', 0);

        $selectedProduct = null;
        $previewRows = collect();
        $previewError = null;
        $previewTotalCost = 0.0;
        $previewUnitCost = 0.0;

        if ($selectedProductId > 0) {
            $selectedProduct = $finishedGoods->firstWhere('id', $selectedProductId);
            if ($selectedProduct && $selectedQuantity > 0) {
                $recipeItems = RecipeItem::query()
                    ->with('ingredient')
                    ->where('finished_product_id', $selectedProduct->id)
                    ->orderBy('id')
                    ->get();

                if ($recipeItems->isEmpty()) {
                    $previewError = 'Recipe is not configured for this product.';
                } else {
                    $previewRows = $recipeItems->map(function (RecipeItem $item) use ($selectedQuantity) {
                        $ingredient = $item->ingredient;
                        $required = round((float) $item->quantity * $selectedQuantity, 2);
                        $ingredientUnitCost = round((float) ($ingredient?->price ?? 0), 4);
                        $requiredCost = round($required * $ingredientUnitCost, 2);
                        $available = $ingredient ? (float) $ingredient->currentStock() : 0;

                        return [
                            'ingredient_name' => $ingredient?->name ?? 'Unknown',
                            'unit' => $ingredient?->unit ?? '',
                            'quantity_per_unit' => (float) $item->quantity,
                            'required' => $required,
                            'ingredient_unit_cost' => $ingredientUnitCost,
                            'required_cost' => $requiredCost,
                            'available' => $available,
                            'is_sufficient' => $available >= $required,
                        ];
                    });

                    $previewTotalCost = (float) $previewRows->sum('required_cost');
                    $previewUnitCost = $selectedQuantity > 0 ? round($previewTotalCost / $selectedQuantity, 4) : 0.0;
                }
            }
        }

        $recentBatches = ProductionBatch::query()
            ->with(['finishedProduct', 'producer', 'items'])
            ->orderByDesc('produced_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('production.index', compact(
            'finishedGoods',
            'selectedProductId',
            'selectedQuantity',
            'selectedProduct',
            'previewRows',
            'previewError',
            'previewTotalCost',
            'previewUnitCost',
            'recentBatches'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'finished_product_id' => 'required|exists:products,id',
            'quantity_produced' => 'required|numeric|min:0.01',
            'produced_at' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $finishedProduct = Product::findOrFail($validated['finished_product_id']);

        try {
            $batch = (new ProductionService())->createBatch(
                $finishedProduct,
                (float) $validated['quantity_produced'],
                $validated['notes'] ?? null,
                $validated['produced_at'] ?? null,
                auth()->id()
            );

            app(ActivityLogService::class)->log(
                module: 'production',
                action: 'create_batch',
                entityType: ProductionBatch::class,
                entityId: (int) $batch->id,
                description: 'Production batch created.',
                newValues: [
                    'finished_product_id' => (int) $batch->finished_product_id,
                    'finished_product_name' => $batch->finishedProduct?->name,
                    'quantity_produced' => (float) $batch->quantity_produced,
                    'total_ingredient_cost' => (float) $batch->total_ingredient_cost,
                    'unit_production_cost' => (float) $batch->unit_production_cost,
                    'produced_at' => $batch->produced_at?->format('Y-m-d H:i:s'),
                    'ingredients_count' => $batch->items->count(),
                ]
            );

            return redirect()
                ->route('production.index', [
                    'product_id' => $batch->finished_product_id,
                    'quantity' => (float) $batch->quantity_produced,
                ])
                ->with('success', 'Production batch created successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->withErrors([
                'production' => $e->getMessage(),
            ]);
        }
    }
}
