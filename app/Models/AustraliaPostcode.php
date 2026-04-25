<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AustraliaPostcode extends Model
{
    protected $fillable = [
        'postcode',
        'locality',
        'zone',
    ];

    public static function findZone(string $postcode): string
    {
        $record = static::where('postcode', $postcode)->first();
        return $record?->zone ?? 'Remote';
    }
}
