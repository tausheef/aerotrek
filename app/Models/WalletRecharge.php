<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletRecharge extends Model
{
    protected $fillable = [
        'user_id',
        'txn_id',
        'amount',
        'status',
        'gateway_response',
    ];

    protected function casts(): array
    {
        return [
            'amount'           => 'float',
            'gateway_response' => 'array',
        ];
    }
}
