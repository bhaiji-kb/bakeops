<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    protected $fillable = [
        'business_name',
        'business_address',
        'business_phone',
        'business_logo_path',
        'gst_enabled',
        'gstin',
        'kot_mode',
    ];

    protected $casts = [
        'gst_enabled' => 'boolean',
    ];
}
