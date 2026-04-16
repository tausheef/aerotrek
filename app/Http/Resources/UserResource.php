<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => (string) $this->_id,
            'name'         => $this->name,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'account_type' => $this->account_type,   // individual | company
            'company_name' => $this->company_name,
            'wallet_balance' => (float) $this->wallet_balance,
            'kyc_status'   => $this->kyc_status,     // pending | verified | rejected
            'is_admin'     => (bool) $this->is_admin,
            'email_verified' => (bool) $this->email_verified,
            'phone_verified' => (bool) $this->phone_verified,
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}