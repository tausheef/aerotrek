<?php

namespace App\Services\KYC;

use App\Models\Kyc;
use App\Models\User;
use App\Services\KYC\Contracts\DocumentVerificationInterface;
use App\Services\KYC\Drivers\ManualKycDriver;
use App\Services\KYC\Drivers\SurepassKycDriver;
use App\Services\Storage\StorageService;

class KycService
{
    public function __construct(
        private StorageService $storage
    ) {}

    /**
     * Submit KYC — called when user submits documents.
     */
    public function submit(User $user, array $data, $documentFile): Kyc
    {
        // Delete existing pending KYC if any
        Kyc::where('user_id', $user->_id)
            ->where('status', 'pending')
            ->delete();

        // Upload document image
        $imagePath = $this->storage->uploadKycDocument(
            userId:       (string) $user->_id,
            documentType: $data['document_type'],
            file:         $documentFile
        );

        // Create KYC record
        $kyc = Kyc::create([
            'user_id'         => (string) $user->_id,
            'account_type'    => $user->account_type,
            'document_type'   => $data['document_type'],
            'document_number' => strtoupper($data['document_number']),
            'document_image'  => $imagePath,
            'status'          => 'pending',
            'verification_driver' => 'manual',
        ]);

        // Get correct driver and verify
        $driver = $this->resolveDriver($data['document_type']);
        $result = $driver->verify($kyc);

        // Update KYC with verification result
        $kyc->update([
            'status'               => $result->status,
            'verification_driver'  => $result->driver,
            'verification_response'=> $result->data,
        ]);

        // If auto-verified instantly — update user kyc_status
        if ($result->status === 'verified') {
            $user->update(['kyc_status' => 'verified']);
        }

        return $kyc->fresh();
    }

    /**
     * Admin approves KYC.
     */
    public function approve(Kyc $kyc, string $adminId): Kyc
    {
        $kyc->update([
            'status'      => 'verified',
            'verified_by' => $adminId,
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        // Update user kyc_status
        User::where('_id', $kyc->user_id)
            ->update(['kyc_status' => 'verified']);

        return $kyc->fresh();
    }

    /**
     * Admin rejects KYC.
     */
    public function reject(Kyc $kyc, string $adminId, string $reason): Kyc
    {
        $kyc->update([
            'status'           => 'rejected',
            'verified_by'      => $adminId,
            'verified_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        // Update user kyc_status
        User::where('_id', $kyc->user_id)
            ->update(['kyc_status' => 'rejected']);

        return $kyc->fresh();
    }

    /**
     * Resolve driver based on config.
     * Change KYC_DRIVER in .env to swap — zero code change.
     *
     * STORAGE_DRIVER=manual   → ManualKycDriver (now)
     * STORAGE_DRIVER=surepass → SurepassKycDriver (later)
     */
    private function resolveDriver(string $documentType): DocumentVerificationInterface
    {
        $driver       = config('kyc.driver', 'manual');
        $surepassReady = ! empty(config('kyc.surepass.token'));

        // If surepass is configured and driver is set to surepass
        if ($driver === 'surepass' && $surepassReady) {
            return new SurepassKycDriver();
        }

        return new ManualKycDriver();
    }
}