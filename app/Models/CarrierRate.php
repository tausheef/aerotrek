<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarrierRate extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'upload_id', 'carrier', 'sub_type', 'zone_key',
        'weight_key', 'rate', 'is_per_kg',
    ];

    protected $casts = [
        'is_per_kg' => 'boolean',
    ];
}
