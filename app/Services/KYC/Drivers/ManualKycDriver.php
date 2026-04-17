<?php

namespace App\Services\KYC\Drivers;

use App\Models\Kyc;
use App\Services\KYC\Contracts\DocumentVerificationInterface;
use App\Services\KYC\VerificationResult;

class ManualKycDriver implements DocumentVerificationInterface
{
    /**
     * Manual driver — just marks as pending for admin review.
     * Admin will approve/reject from admin panel.
     */
    public function verify(Kyc $kyc): VerificationResult
    {
        return VerificationResult::pending(
            message: 'Document submitted. Pending admin verification.',
            driver:  $this->getDriverName(),
        );
    }

    public function canAutoVerify(): bool
    {
        return false; // requires admin action
    }

    public function getDriverName(): string
    {
        return 'manual';
    }

    public function getSupportedDocuments(): array
    {
        return ['aadhaar', 'pan', 'gst', 'company_pan'];
    }
}