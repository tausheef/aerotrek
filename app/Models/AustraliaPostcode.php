<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class AustraliaPostcode extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'australia_postcodes';

    protected $fillable = [
        'postcode',
        'locality',
        'zone',     // Metro | Capital | Regional | Remote
    ];

    public static function findZone(string $postcode): string
    {
        $record = static::where('postcode', $postcode)->first();
        return $record?->zone ?? 'Remote'; // default to Remote if not found
    }
}