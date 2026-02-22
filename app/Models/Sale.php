<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'bill_number',
        'customer_id',
        'created_by_user_id',
        'customer_identifier',
        'customer_name_snapshot',
        'customer_address_snapshot',
        'sub_total',
        'discount_amount',
        'tax_amount',
        'round_off',
        'total_amount',
        'payment_mode',
        'order_source',
        'channel',
        'external_order_id',
        'channel_status',
        'channel_accepted_at',
        'channel_ready_at',
        'channel_delivered_at',
        'channel_cancelled_at',
        'order_reference',
        'paid_amount',
        'balance_amount',
    ];

    protected $casts = [
        'sub_total' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'round_off' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'channel_accepted_at' => 'datetime',
        'channel_ready_at' => 'datetime',
        'channel_delivered_at' => 'datetime',
        'channel_cancelled_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function channelOrder()
    {
        return $this->hasOne(ChannelOrder::class);
    }
}
