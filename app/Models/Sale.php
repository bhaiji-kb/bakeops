<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'bill_number',
        'total_amount',
        'payment_mode',
    ];

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }
}
