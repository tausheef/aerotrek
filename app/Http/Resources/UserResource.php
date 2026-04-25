<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'account_type'   => $this->account_type,
            'company_name'   => $this->company_name,
            'wallet_balance' => (float) ($this->wallet?->balanceFloat ?? 0),
            'kyc_status'     => $this->kyc_status,
            'is_admin'       => (bool) $this->is_admin,
            'email_verified' => (bool) $this->email_verified,
            'phone_verified' => (bool) $this->phone_verified,
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }
}