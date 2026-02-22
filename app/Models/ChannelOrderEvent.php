<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelOrderEvent extends Model
{
    protected $fillable = [
        'channel_order_id',
        'integration_connector_id',
        'channel',
        'external_order_id',
        'external_event_id',
        'idempotency_key',
        'event_type',
        'signature_valid',
        'payload',
        'normalized_payload',
        'process_status',
        'process_error',
        'retry_count',
        'processed_at',
    ];

    protected $casts = [
        'signature_valid' => 'boolean',
        'payload' => 'array',
        'normalized_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(ChannelOrder::class, 'channel_order_id');
    }

    public function connector()
    {
        return $this->belongsTo(IntegrationConnector::class, 'integration_connector_id');
    }
}
