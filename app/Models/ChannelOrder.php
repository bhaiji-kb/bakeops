<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelOrder extends Model
{
    protected $fillable = [
        'integration_connector_id',
        'sale_id',
        'order_id',
        'channel',
        'external_order_id',
        'customer_name',
        'customer_identifier',
        'order_total',
        'status',
        'accepted_at',
        'ready_at',
        'delivered_at',
        'cancelled_at',
        'last_event_at',
        'latest_payload',
        'normalized_payload',
        'notes',
    ];

    protected $casts = [
        'order_total' => 'decimal:2',
        'accepted_at' => 'datetime',
        'ready_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_event_at' => 'datetime',
        'latest_payload' => 'array',
        'normalized_payload' => 'array',
    ];

    public function connector()
    {
        return $this->belongsTo(IntegrationConnector::class, 'integration_connector_id');
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function events()
    {
        return $this->hasMany(ChannelOrderEvent::class);
    }
}
