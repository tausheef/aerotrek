<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Kyc extends Model
{
    protected $table = 'kycs';

    protected $fillable = [
        'user_id',
        'account_type',
        'document_type',
        'document_number',
        'document_image',
        'status',
        'rejection_reason',
        'verified_by',
        'verified_at',
        'verification_driver',
        'verification_response',
    ];

    protected function casts(): array
    {
        return [
            'verified_at'           => 'datetime',
            'verification_response' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function allowedDocuments(string $accountType): array
    {
        return match ($accountType) {
            'individual' => ['aadhaar', 'pan'],
            'company'    => ['gst', 'company_pan'],
            default      => [],
        };
    }
}
