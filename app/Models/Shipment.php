<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Shipment extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'shipments';

    protected $fillable = [
        'user_id',
        'awb_no',               // Overseas AWB number
        'tracking_no',          // Carrier tracking number (DHL/UPS/FedEx)
        'carrier',              // DHL | FedEx | UPS | Aramex | SELF
        'service_code',         // DHL_EXPRESS | UPS_SAVER etc
        'service_name',         // Human readable
        'network',              // DHL | FEDEX | UPS | ARAMEX | SELF
        'status',               // pending | booked | in_transit | delivered | failed
        'goods_type',           // Dox | NDox
        'label_url',            // Shipping label URL
        'invoice_url',          // Invoice URL
        'price',                // Amount charged to wallet
        'chargeable_weight',    // Final billed weight
        'sender',               // Sender details array
        'receiver',             // Receiver details array
        'packages',             // Package dimensions array
        'products',             // Product details array
        'invoice_no',
        'invoice_date',
        'duty_tax',             // DDU | DDP
        'reason_for_export',
        'transaction_id',       // DHL OTP transaction ID
        'wallet_transaction_id',// Our wallet deduction reference
        'overseas_response',    // Raw Overseas API response
        'tracking_events',      // Cached tracking events
        'tracking_updated_at',  // Last tracking update
    ];

    protected function casts(): array
    {
        return [
            'price'              => 'float',
            'sender'             => 'array',
            'receiver'           => 'array',
            'packages'           => 'array',
            'products'           => 'array',
            'overseas_response'  => 'array',
            'tracking_events'    => 'array',
            'tracking_updated_at'=> 'datetime',
        ];
    }

    // Scopes
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}