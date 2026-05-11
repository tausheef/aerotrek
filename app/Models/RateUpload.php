<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RateUpload extends Model
{
    protected $fillable = [
        'filename', 'original_name', 'status',
        'processed_rows', 'total_rows', 'error_message',
        'uploaded_by', 'activated_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
    ];

    public function carrierRates(): HasMany
    {
        return $this->hasMany(CarrierRate::class, 'upload_id');
    }

    public function shiprocketRates(): HasMany
    {
        return $this->hasMany(ShiprocketRate::class, 'upload_id');
    }

    public static function getActive(): ?self
    {
        return static::where('status', 'active')
            ->latest('activated_at')
            ->first();
    }
}
