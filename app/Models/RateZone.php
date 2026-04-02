<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class RateZone extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'rate_zones';

    protected $fillable = [
        'carrier',      // DHL | FedEx | Aramex | UPS | UPS-DUTY-FREE | SELF-UK | SELF-EUROPE | SELF-DUBAI | SELF-AUSTRALIA | SELF-NZ | SELF-CANADA
        'zone',         // Zone 1, Zone A, UAE-PPX, Metro, EU-Zone-1 etc
        'countries',    // array of country names this zone covers
    ];

    protected function casts(): array
    {
        return [
            'countries' => 'array',
        ];
    }

    /**
     * Find zone for a carrier + country combination
     */
    public static function findZone(string $carrier, string $country): ?string
    {
        $zone = static::where('carrier', $carrier)
            ->where('countries', $country)
            ->first();

        return $zone?->zone;
    }
}