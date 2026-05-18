<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'full_name',
        'company_name',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'pincode',
        'country',
        'phone',
        'is_default',
        'shiprocket_pickup_id',
        'shiprocket_pickup_name',
        'pickup_verified',
        'pickup_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_default'         => 'boolean',
            'pickup_verified'    => 'boolean',
            'pickup_verified_at' => 'datetime',
        ];
    }
}
