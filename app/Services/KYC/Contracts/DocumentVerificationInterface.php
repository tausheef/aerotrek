<?php

namespace App\Services\KYC\Contracts;

use App\Models\Kyc;
use App\Services\KYC\VerificationResult;

interface DocumentVerificationInterface
{
    /**
     * Verify a document.
     * Both manual and automated drivers implement this.
     */
    public function verify(Kyc $kyc): VerificationResult;

    /**
     * Whether this driver can verify automatically without admin.
     */
    public function canAutoVerify(): bool;

    /**
     * Driver identifier name.
     */
    public function getDriverName(): string;

    /**
     * Document types this driver supports.
     */
    public function getSupportedDocuments(): array;
}