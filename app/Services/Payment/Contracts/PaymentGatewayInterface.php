<?php

namespace App\Services\Payment\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Initiate a payment — returns payment URL + params for frontend.
     */
    public function initiatePayment(array $data): array;

    /**
     * Verify payment after callback from gateway.
     */
    public function verifyPayment(array $callbackData): bool;

    /**
     * Calculate hash/signature for request.
     */
    public function generateHash(array $data): string;

    /**
     * Verify hash from gateway callback.
     */
    public function verifyHash(array $data): bool;

    /**
     * Gateway name.
     */
    public function getGatewayName(): string;
}