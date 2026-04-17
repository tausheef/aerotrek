<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'wallet_transactions';

    protected $fillable = [
        'user_id',
        'type',             // credit | debit
        'amount',           // INR amount
        'balance_before',   // wallet balance before transaction
        'balance_after',    // wallet balance after transaction
        'description',      // "Wallet recharge via PayU" | "Shipment booking #AWB123"
        'reference_id',     // PayU transaction ID or shipment AWB
        'payment_gateway',  // payu | manual | system
        'gateway_response', // raw PayU response stored for audit
        'status',           // pending | completed | failed
    ];

    protected function casts(): array
    {
        return [
            'amount'           => 'float',
            'balance_before'   => 'float',
            'balance_after'    => 'float',
            'gateway_response' => 'array',
        ];
    }
}