<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateZone extends Model
{
    protected $fillable = [
        'carrier',
        'zone',
        'countries',
    ];

    protected function casts(): array
    {
        return [
            'countries' => 'array',
        ];
    }

    public static function findZone(string $carrier, string $country): ?string
    {
        $zone = static::where('carrier', $carrier)
            ->whereJsonContains('countries', $country)
            ->first();

        return $zone?->zone;
    }
}
