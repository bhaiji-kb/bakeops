<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'mobile',
        'identifier',
        'email',
        'address',
        'address_line1',
        'road',
        'sector',
        'city',
        'pincode',
        'preference',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function formattedAddress(): string
    {
        $parts = [
            (string) ($this->address_line1 ?? ''),
            (string) ($this->road ?? ''),
            (string) ($this->sector ?? ''),
            (string) ($this->city ?? ''),
        ];
        $parts = array_values(array_filter(array_map('trim', $parts), fn (string $value) => $value !== ''));
        $address = implode(', ', $parts);
        $pincode = trim((string) ($this->pincode ?? ''));

        if ($pincode !== '') {
            $address = trim($address . ($address !== '' ? ' - ' : '') . $pincode);
        }

        return $address;
    }
}
