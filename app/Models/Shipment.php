<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Shipment extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'shipments';

    protected $fillable = [
        'aerotrek_id',          // ATK-YYYYMMDD-XXXXXX — Aerotrek's own unique ID
        'platform',             // overseas | shiprocket | delhivery (which platform booked)
        'platform_ref_id',      // Platform's own shipment/order ID (Overseas AWB, Shiprocket order ID)
        'user_id',
        'awb_no',               // Carrier AWB number (DHL/FedEx/UPS actual AWB)
        'tracking_no',          // Carrier tracking number
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
        'overseas_response',    // Raw platform API response
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

    /**
     * Find shipment by any identifier — ATK ID, AWB, or platform ref ID.
     */
    public static function findByIdentifier(string $identifier): ?self
    {
        return static::where('aerotrek_id', $identifier)
            ->orWhere('awb_no', $identifier)
            ->orWhere('platform_ref_id', $identifier)
            ->first();
    }
}