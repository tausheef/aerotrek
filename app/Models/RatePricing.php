<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RatePricing extends Model
{
    protected $fillable = [
        'carrier',
        'shipment_type',
        'zone',
        'weight',
        'price',
        'is_per_kg',
        'tier',
        'route',
    ];

    protected function casts(): array
    {
        return [
            'weight'    => 'float',
            'price'     => 'float',
            'is_per_kg' => 'boolean',
        ];
    }

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
