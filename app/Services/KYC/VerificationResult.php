<?php

namespace App\Services\KYC;

class VerificationResult
{
    public function __construct(
        public readonly bool   $success,
        public readonly string $status,    // verified | rejected | pending
        public readonly string $message,
        public readonly array  $data = [], // raw response data
        public readonly string $driver = 'manual',
    ) {}

    public static function verified(string $message = 'Document verified.', array $data = [], string $driver = 'manual'): self
    {
        return new self(
            success: true,
            status:  'verified',
            message: $message,
            data:    $data,
            driver:  $driver,
        );
    }

    public static function rejected(string $message = 'Document rejected.', array $data = [], string $driver = 'manual'): self
    {
        return new self(
            success: false,
            status:  'rejected',
            message: $message,
            data:    $data,
            driver:  $driver,
        );
    }

    public static function pending(string $message = 'Verification pending.', string $driver = 'manual'): self
    {
        return new self(
            success: true,
            status:  'pending',
            message: $message,
            driver:  $driver,
        );
    }
}