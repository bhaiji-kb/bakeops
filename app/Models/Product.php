<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'price',
        'type',
        'is_active',
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

}
