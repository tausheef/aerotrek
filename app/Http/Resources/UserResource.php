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
            'avatar_url'     => $this->resolveAvatarUrl(),
            'shipment_limit' => $this->shipment_limit ?? 500,
            'created_at'     => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Generate a fresh pre-signed URL for the stored avatar path.
     * Stored value is the file path (e.g. "profiles/uuid/avatar_xxx.jpg").
     * Returns null if no avatar is set or generation fails.
     */
    private function resolveAvatarUrl(): ?string
    {
        if (! $this->avatar_url) return null;

        // Legacy records may have stored a full URL — return as-is.
        if (str_starts_with($this->avatar_url, 'http')) {
            return $this->avatar_url;
        }

        // Path stored — generate a 7-day pre-signed URL so the browser can load it.
        try {
            $storage = new \App\Services\Storage\StorageService();
            return $storage->temporaryUrl($this->avatar_url, 60 * 24 * 7);
        } catch (\Throwable) {
            return null;
        }
    }
}