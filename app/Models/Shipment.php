<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = [
        'aerotrek_id',
        'booking_type',
        'platform',
        'platform_ref_id',
        'user_id',
        'awb_no',
        'tracking_no',
        'carrier',
        'service_code',
        'service_name',
        'network',
        'status',
        'goods_type',
        'label_url',
        'invoice_url',
        'price',
        'chargeable_weight',
        'sender',
        'receiver',
        'packages',
        'products',
        'invoice_no',
        'invoice_date',
        'invoice_currency',
        'terms_of_sale',
        'csb_type',
        'duty_tax',
        'reason_for_export',
        'notes',
        'rejection_reason',
        'transaction_id',
        'wallet_transaction_id',
        'overseas_response',
        'shiprocket_response',
        'tracking_events',
        'tracking_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'price'               => 'float',
            'sender'              => 'array',
            'receiver'            => 'array',
            'packages'            => 'array',
            'products'            => 'array',
            'overseas_response'    => 'array',
            'shiprocket_response'  => 'array',
            'tracking_events'      => 'array',
            'tracking_updated_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByBookingType($query, string $type)
    {
        return $query->where('booking_type', $type);
    }

    public static function findByIdentifier(string $identifier): ?self
    {
        return static::where('aerotrek_id', $identifier)
            ->orWhere('awb_no', $identifier)
            ->orWhere('platform_ref_id', $identifier)
            ->first();
    }
}
