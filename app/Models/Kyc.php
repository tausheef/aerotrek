<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Kyc extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'kyc';

    protected $fillable = [
        'user_id',
        'account_type',     // individual | company
        'document_type',    // aadhaar | pan | gst | company_pan
        'document_number',
        'document_image',   // file path (Cloudflare R2 later)
        'status',           // pending | verified | rejected | auto_verified
        'rejection_reason',
        'verified_by',      // admin user_id (manual) or 'system' (automated)
        'verified_at',
        'verification_driver', // manual | surepass
        'verification_response', // raw API response stored for audit
    ];

    protected function casts(): array
    {
        return [
            'verified_at'            => 'datetime',
            'verification_response'  => 'array',
        ];
    }

    // Document types per account type
    public static function allowedDocuments(string $accountType): array
    {
        return match ($accountType) {
            'individual' => ['aadhaar', 'pan'],
            'company'    => ['gst', 'company_pan'],
            default      => [],
        };
    }
}