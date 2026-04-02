<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class RatePricing extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'rate_pricing';

    protected $fillable = [
        'carrier',          // DHL | FedEx | Aramex | UPS etc
        'zone',             // Zone 1 | Zone A | UAE-PPX | Metro etc
        'shipment_type',    // Document | Non-Document | Envelope | Pak | C2C | Per-KG-30Plus
        'weight',           // 0.5 | 1 | 1.5 ... 30
        'price',            // INR price
        'is_per_kg',        // true = this is a per-kg rate (for weight > slab)
        'tier',             // above_10 | below_10 (for SELF services)
        'route',            // YVR | YYZ (for Canada)
    ];

    protected function casts(): array
    {
        return [
            'weight'    => 'float',
            'price'     => 'float',
            'is_per_kg' => 'boolean',
        ];
    }

    /**
     * Find the price for given parameters
     */
    public static function findPrice(
        string $carrier,
        string $zone,
        string $shipmentType,
        float  $weight,
        ?string $tier = null
    ): ?float {
        $query = static::where('carrier', $carrier)
            ->where('zone', $zone)
            ->where('shipment_type', $shipmentType)
            ->where('weight', $weight);

        if ($tier) {
            $query->where('tier', $tier);
        }

        return $query->first()?->price;
    }
}