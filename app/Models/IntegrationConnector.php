<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationConnector extends Model
{
    public const DRIVER_GENERIC = 'generic';
    public const DRIVER_ZOMATO = 'zomato';
    public const DRIVER_SWIGGY = 'swiggy';

    public const DRIVERS = [
        self::DRIVER_GENERIC,
        self::DRIVER_ZOMATO,
        self::DRIVER_SWIGGY,
    ];

    protected $fillable = [
        'code',
        'name',
        'driver',
        'api_base_url',
        'api_key',
        'api_secret',
        'webhook_secret',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
        'webhook_secret' => 'encrypted',
    ];

    public function orders()
    {
        return $this->hasMany(ChannelOrder::class);
    }

    public function events()
    {
        return $this->hasMany(ChannelOrderEvent::class);
    }
}
