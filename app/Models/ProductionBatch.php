<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionBatch extends Model
{
    protected $fillable = [
        'finished_product_id',
        'quantity_produced',
        'total_ingredient_cost',
        'unit_production_cost',
        'produced_at',
        'notes',
        'produced_by',
    ];

    protected $casts = [
        'quantity_produced' => 'decimal:2',
        'total_ingredient_cost' => 'decimal:2',
        'unit_production_cost' => 'decimal:4',
        'produced_at' => 'datetime',
    ];

    public function finishedProduct()
    {
        return $this->belongsTo(Product::class, 'finished_product_id');
    }

    public function producer()
    {
        return $this->belongsTo(User::class, 'produced_by');
    }

    public function items()
    {
        return $this->hasMany(ProductionBatchItem::class);
    }
}
