<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'source',
        'status',
        'customer_id',
        'customer_identifier',
        'customer_name',
        'customer_address',
        'notes',
        'advance_paid_amount',
        'advance_payment_mode',
        'sale_id',
        'created_by_user_id',
        'accepted_at',
        'in_kitchen_at',
        'ready_at',
        'dispatched_at',
        'completed_at',
        'invoiced_at',
        'cancelled_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'in_kitchen_at' => 'datetime',
        'ready_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
        'invoiced_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'advance_paid_amount' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function kot()
    {
        return $this->hasOne(Kot::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function channelOrders()
    {
        return $this->hasMany(ChannelOrder::class);
    }
}
