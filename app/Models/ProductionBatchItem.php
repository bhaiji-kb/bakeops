<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionBatchItem extends Model
{
    protected $fillable = [
        'production_batch_id',
        'ingredient_product_id',
        'quantity_per_unit',
        'quantity_used',
        'ingredient_unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'quantity_per_unit' => 'decimal:2',
        'quantity_used' => 'decimal:2',
        'ingredient_unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
    ];

    public function productionBatch()
    {
        return $this->belongsTo(ProductionBatch::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Product::class, 'ingredient_product_id');
    }
}
