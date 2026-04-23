<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Model implements AuthenticatableContract, AuthorizableContract, JWTSubject
{
    use Authenticatable, Authorizable, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'account_type',     // individual | company
        'company_name',     // required if account_type = company
        'wallet_balance',
        'kyc_status',       // pending | verified | rejected
        'is_admin',
        'email_verified',
        'phone_verified',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean',
            'email_verified'    => 'boolean',
            'phone_verified'    => 'boolean',
            'wallet_balance'    => 'float',
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

    // Helper — check if KYC is verified
    public function isKycVerified(): bool
    {
        return $this->kyc_status === 'verified';
    }

    // Helper — check if company
    public function isCompany(): bool
    {
        return $this->account_type === 'company';
    }
}