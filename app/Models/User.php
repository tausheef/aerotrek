<?php

namespace App\Models;

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Traits\HasWallet;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Model implements AuthenticatableContract, AuthorizableContract, JWTSubject, Wallet
{
    use Authenticatable, Authorizable, Notifiable, HasWallet, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'account_type',
        'company_name',
        'kyc_status',
        'is_admin',
        'email_verified',
        'phone_verified',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public int $decimalPlaces = 2;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean',
            'email_verified'    => 'boolean',
            'phone_verified'    => 'boolean',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function isKycVerified(): bool
    {
        return $this->kyc_status === 'verified';
    }

    public function isCompany(): bool
    {
        return $this->account_type === 'company';
    }
}
