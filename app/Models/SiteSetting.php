<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class SiteSetting extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'site_settings';

    protected $fillable = [
        'key',    // site_name | logo | contact_email | phone | address | social_links
        'value',
        'type',   // text | image | boolean | json
    ];

    // Helper - get a setting value by key
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    // Helper - set a setting value by key
    public static function set(string $key, mixed $value, string $type = 'text'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }
}