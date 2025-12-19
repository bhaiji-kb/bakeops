<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
        'transaction_type',
        'reference_type',
        'reference_id',
        'notes',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
