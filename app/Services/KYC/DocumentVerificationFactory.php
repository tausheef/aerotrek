<?php

namespace App\Services\KYC;

use App\Services\KYC\Contracts\DocumentVerificationInterface;
use App\Services\KYC\Drivers\ManualKycDriver;
use App\Services\KYC\Drivers\SurepassKycDriver;

class DocumentVerificationFactory
{
    /**
     * Get the correct driver based on config.
     * Change KYC_DRIVER in .env to swap — zero code change.
     */
    public static function make(): DocumentVerificationInterface
    {
        $driver = config('kyc.driver', 'manual');

        return match ($driver) {
            'surepass' => new SurepassKycDriver(),
            'manual'   => new ManualKycDriver(),
            default    => new ManualKycDriver(),
        };
    }

    /**
     * Get driver for specific document type.
     * Future: different providers per document type.
     */
    public static function makeForDocument(string $documentType): DocumentVerificationInterface
    {
        // When Surepass is configured, auto-route per document
        $surepassEnabled = ! empty(config('kyc.surepass.token'));

        if ($surepassEnabled) {
            return new SurepassKycDriver();
        }

        return new ManualKycDriver();
    }
}