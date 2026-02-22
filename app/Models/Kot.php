<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kot extends Model
{
    protected $fillable = [
        'order_id',
        'kot_number',
        'status',
        'printed_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
