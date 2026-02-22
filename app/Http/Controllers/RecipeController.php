<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RecipeItem;
use App\Services\ActivityLogService;
use App\Services\RecipeWorkbookImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecipeController extends Controller
{
    public function index()
    {
        $finishedGoods = Product::query()
            ->where('type', 'finished_good')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $recipeCounts = RecipeItem::query()
            ->selectRaw('finished_product_id, COUNT(*) as ingredient_count')
            ->groupBy('finished_product_id')
            ->pluck('ingredient_count', 'finished_product_id');

        $recipeCosts = RecipeItem::query()
            ->join('products as ingredients', 'ingredients.id', '=', 'recipe_items.ingredient_product_id')
            ->selectRaw('recipe_items.finished_product_id, SUM(recipe_items.quantity * ingredients.price) as estimated_cost')
            ->groupBy('recipe_items.finished_product_id')
            ->pluck('estimated_cost', 'recipe_items.finished_product_id');

        $configuredCount = $recipeCounts->keys()->count();
        $unconfiguredCount = max($finishedGoods->count() - $configuredCount, 0);

        return view('recipes.index', compact(
            'finishedGoods',
            'recipeCounts',
            'recipeCosts',
            'configuredCount',
            'unconfiguredCount'
        ));
    }

    public function edit(Product $product)
    {
        if ($product->type !== 'finished_good') {
            abort(404);
        }

        $rawMaterials = Product::query()
            ->where('type', 'raw_material')
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $existing = RecipeItem::query()
            ->where('finished_product_id', $product->id)
            ->pluck('quantity', 'ingredient_product_id');

        return view('recipes.edit', compact('product', 'rawMaterials', 'existing'));
    }

    public function update(Request $request, Product $product)
    {
        if ($product->type !== 'finished_good') {
            abort(404);
        }

        $validated = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.ingredient_product_id' => 'required|exists:products,id',
            'rows.*.quantity' => 'nullable|numeric|min:0',
        ]);

        $rows = $validated['rows'];
        $items = [];
        foreach ($rows as $row) {
            $quantity = (float) ($row['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $ingredientId = (int) $row['ingredient_product_id'];
            $items[$ingredientId] = [
                'ingredient_product_id' => $ingredientId,
                'quantity' => $quantity,
            ];
        }

        if (count($items) === 0) {
            return back()->withInput()->withErrors([
                'rows' => 'Add at least one ingredient quantity greater than 0.',
            ]);
        }

        $ingredientIds = array_keys($items);
        $rawMaterialCount = Product::query()
            ->whereIn('id', $ingredientIds)
            ->where('type', 'raw_material')
            ->count();

        if ($rawMaterialCount !== count($ingredientIds)) {
            return back()->withInput()->withErrors([
                'rows' => 'Only raw material products can be used as recipe ingredients.',
            ]);
        }

        $oldValues = RecipeItem::query()
            ->where('finished_product_id', $product->id)
            ->orderBy('ingredient_product_id')
            ->get()
            ->map(fn (RecipeItem $item) => [
                'ingredient_product_id' => (int) $item->ingredient_product_id,
                'quantity' => (float) $item->quantity,
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($product, $items): void {
            RecipeItem::query()
                ->where('finished_product_id', $product->id)
                ->delete();

            foreach ($items as $item) {
                RecipeItem::create([
                    'finished_product_id' => $product->id,
                    'ingredient_product_id' => $item['ingredient_product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        });

        app(ActivityLogService::class)->log(
            module: 'recipes',
            action: 'upsert',
            entityType: Product::class,
            entityId: (int) $product->id,
            description: 'Recipe updated for finished product.',
            oldValues: [
                'ingredients' => $oldValues,
            ],
            newValues: [
                'finished_product_id' => (int) $product->id,
                'ingredient_count' => count($items),
                'ingredients' => array_values($items),
            ]
        );

        return redirect()
            ->route('recipes.edit', ['product' => $product->id])
            ->with('success', 'Recipe updated successfully.');
    }

    public function import(Request $request, RecipeWorkbookImportService $importer)
    {
        $validated = $request->validate([
            'source_path' => 'nullable|string|max:1000',
            'excel_file' => 'nullable|file|mimes:xlsx,xls|max:4096',
        ]);

        $sourcePath = trim((string) ($validated['source_path'] ?? ''));
        $upload = $request->file('excel_file');
        if ($sourcePath === '' && !$upload) {
            return back()->withErrors([
                'source_path' => 'Provide file path or upload a workbook.',
            ])->withInput();
        }

        $path = $sourcePath;
        if ($upload) {
            $path = (string) $upload->getRealPath();
        }

        if ($path === '' || !is_file($path)) {
            return back()->withErrors([
                'source_path' => 'Workbook path not found.',
            ])->withInput();
        }

        try {
            $result = $importer->importFromPath($path);
        } catch (Throwable $e) {
            return back()->withErrors([
                'source_path' => 'Import failed: ' . $e->getMessage(),
            ])->withInput();
        }

        app(ActivityLogService::class)->log(
            module: 'recipes',
            action: 'import_workbook',
            description: 'Recipe workbook imported.',
            newValues: [
                'source' => $upload ? 'upload' : 'path',
                'path' => $upload ? $upload->getClientOriginalName() : $path,
                'imported_products' => $result['imported_products'],
                'imported_recipes' => $result['imported_recipes'],
                'imported_recipe_items' => $result['imported_recipe_items'],
                'skipped_sheets' => $result['skipped_sheets'],
                'warnings_count' => count($result['warnings']),
            ]
        );

        $warningPreview = array_slice($result['warnings'], 0, 8);
        $success = sprintf(
            'Import complete: %d products, %d recipes, %d recipe lines.',
            (int) $result['imported_products'],
            (int) $result['imported_recipes'],
            (int) $result['imported_recipe_items']
        );

        return redirect()
            ->route('recipes.index')
            ->with('success', $success)
            ->with('import_warning_preview', $warningPreview)
            ->with('import_warning_count', count($result['warnings']));
    }
}
