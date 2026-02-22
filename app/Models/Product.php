<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'code',
        'legacy_code',
        'price',
        'unit_cost',
        'type',
        'unit',
        'reorder_level',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'reorder_level' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function currentStock(): float
    {
        $in = $this->inventoryTransactions()
            ->where('transaction_type', 'IN')
            ->sum('quantity');

        $out = $this->inventoryTransactions()
            ->whereIn('transaction_type', ['OUT', 'WASTE'])
            ->sum('quantity');

        return $in - $out;
    }

    public function inventoryTransactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class, 'finished_product_id');
    }

    public function productionBatches()
    {
        return $this->hasMany(ProductionBatch::class, 'finished_product_id');
    }

}
