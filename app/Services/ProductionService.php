<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchItem;
use App\Models\RecipeItem;
use Exception;
use Illuminate\Support\Facades\DB;

class ProductionService
{
    public function createBatch(
        Product $finishedProduct,
        float $quantityProduced,
        ?string $notes = null,
        ?string $producedAt = null,
        ?int $producedBy = null
    ): ProductionBatch {
        if ($finishedProduct->type !== 'finished_good') {
            throw new Exception('Only finished goods can be produced.');
        }

        if ($quantityProduced <= 0) {
            throw new Exception('Produced quantity must be greater than 0.');
        }

        $recipeItems = RecipeItem::query()
            ->with('ingredient')
            ->where('finished_product_id', $finishedProduct->id)
            ->orderBy('id')
            ->get();

        if ($recipeItems->isEmpty()) {
            throw new Exception('Recipe is not configured for this finished product.');
        }

        $consumptions = [];
        foreach ($recipeItems as $item) {
            $ingredient = $item->ingredient;
            if (!$ingredient || $ingredient->type !== 'raw_material') {
                throw new Exception('Recipe contains invalid ingredient setup.');
            }

            $quantityPerUnit = (float) $item->quantity;
            $quantityUsed = round($quantityPerUnit * $quantityProduced, 2);
            if ($quantityUsed <= 0) {
                continue;
            }

            $available = (float) $ingredient->currentStock();
            if ($available < $quantityUsed) {
                throw new Exception(
                    "Insufficient stock for ingredient {$ingredient->name}. Required: {$quantityUsed}, Available: {$available}."
                );
            }

            $consumptions[] = [
                'ingredient' => $ingredient,
                'quantity_per_unit' => $quantityPerUnit,
                'quantity_used' => $quantityUsed,
            ];
        }

        if (count($consumptions) === 0) {
            throw new Exception('Recipe quantities are invalid.');
        }

        return DB::transaction(function () use ($finishedProduct, $quantityProduced, $notes, $producedAt, $producedBy, $consumptions) {
            $existingFinishedStock = (float) $finishedProduct->currentStock();
            $existingFinishedUnitCost = (float) $finishedProduct->unit_cost;

            $totalIngredientCost = 0.0;
            foreach ($consumptions as &$consumption) {
                $ingredientUnitCost = round((float) $consumption['ingredient']->price, 4);
                $totalCost = round($consumption['quantity_used'] * $ingredientUnitCost, 2);

                $consumption['ingredient_unit_cost'] = $ingredientUnitCost;
                $consumption['total_cost'] = $totalCost;
                $totalIngredientCost += $totalCost;
            }
            unset($consumption);

            $totalIngredientCost = round($totalIngredientCost, 2);
            $unitProductionCost = round($totalIngredientCost / $quantityProduced, 4);

            $batch = ProductionBatch::create([
                'finished_product_id' => $finishedProduct->id,
                'quantity_produced' => $quantityProduced,
                'total_ingredient_cost' => $totalIngredientCost,
                'unit_production_cost' => $unitProductionCost,
                'produced_at' => $producedAt ?? now(),
                'notes' => $notes,
                'produced_by' => $producedBy,
            ]);

            $inventory = new InventoryService();

            foreach ($consumptions as $consumption) {
                $ingredient = $consumption['ingredient'];

                ProductionBatchItem::create([
                    'production_batch_id' => $batch->id,
                    'ingredient_product_id' => $ingredient->id,
                    'quantity_per_unit' => $consumption['quantity_per_unit'],
                    'quantity_used' => $consumption['quantity_used'],
                    'ingredient_unit_cost' => $consumption['ingredient_unit_cost'],
                    'total_cost' => $consumption['total_cost'],
                ]);

                $inventory->deductStock(
                    $ingredient,
                    $consumption['quantity_used'],
                    'production_batch',
                    $batch->id
                );
            }

            $inventory->addStock(
                $finishedProduct,
                $quantityProduced,
                'Production batch inward',
                'production_batch',
                $batch->id
            );

            $totalFinishedStock = $existingFinishedStock + $quantityProduced;
            $newFinishedUnitCost = $unitProductionCost;
            if ($totalFinishedStock > 0) {
                $newFinishedUnitCost = round(
                    (($existingFinishedStock * $existingFinishedUnitCost) + ($quantityProduced * $unitProductionCost)) / $totalFinishedStock,
                    4
                );
            }

            $finishedProduct->update([
                'unit_cost' => $newFinishedUnitCost,
            ]);

            return $batch->fresh(['finishedProduct', 'items.ingredient', 'producer']);
        });
    }
}
