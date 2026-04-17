<?php

namespace App\Services\KYC\Drivers;

use App\Models\Kyc;
use App\Services\KYC\Contracts\DocumentVerificationInterface;
use App\Services\KYC\VerificationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SurepassKycDriver implements DocumentVerificationInterface
{
    private string $apiUrl;
    private string $token;

    public function __construct()
    {
        $this->apiUrl = config('kyc.surepass.api_url', 'https://kyc-api.surepass.io/api/v1');
        $this->token  = config('kyc.surepass.token', '');
    }

    /**
     * Auto-verify document using Surepass API.
     * Routes to correct method based on document type.
     */
    public function verify(Kyc $kyc): VerificationResult
    {
        return match ($kyc->document_type) {
            'pan'         => $this->verifyPan($kyc),
            'company_pan' => $this->verifyPan($kyc),
            'gst'         => $this->verifyGst($kyc),
            'aadhaar'     => $this->verifyAadhaar($kyc),
            default       => VerificationResult::rejected('Unsupported document type for auto verification.'),
        };
    }

    // ── PAN Verification ──────────────────────────────────────────────
    private function verifyPan(Kyc $kyc): VerificationResult
    {
        // TODO: Uncomment when Surepass token is available
        // try {
        //     $response = Http::withToken($this->token)
        //         ->post("{$this->apiUrl}/pan/pan-comprehensive", [
        //             'id_number' => $kyc->document_number,
        //         ]);
        //
        //     $data = $response->json();
        //
        //     if ($response->ok() && $data['success']) {
        //         return VerificationResult::verified(
        //             message: 'PAN verified successfully.',
        //             data:    $data['data'],
        //             driver:  $this->getDriverName(),
        //         );
        //     }
        //
        //     return VerificationResult::rejected(
        //         message: $data['message'] ?? 'PAN verification failed.',
        //         data:    $data,
        //         driver:  $this->getDriverName(),
        //     );
        // } catch (\Exception $e) {
        //     Log::error('Surepass PAN verification failed', ['error' => $e->getMessage()]);
        //     return VerificationResult::rejected('PAN verification service unavailable.');
        // }

        // Fallback to manual until Surepass token is configured
        return $this->fallbackToManual($kyc);
    }

    // ── GST Verification ──────────────────────────────────────────────
    private function verifyGst(Kyc $kyc): VerificationResult
    {
        // TODO: Uncomment when Surepass token is available
        // try {
        //     $response = Http::withToken($this->token)
        //         ->post("{$this->apiUrl}/corporate/gstin", [
        //             'id_number' => $kyc->document_number,
        //         ]);
        //
        //     $data = $response->json();
        //
        //     if ($response->ok() && $data['success']) {
        //         return VerificationResult::verified(
        //             message: 'GST verified successfully.',
        //             data:    $data['data'],
        //             driver:  $this->getDriverName(),
        //         );
        //     }
        //
        //     return VerificationResult::rejected(
        //         message: $data['message'] ?? 'GST verification failed.',
        //         data:    $data,
        //         driver:  $this->getDriverName(),
        //     );
        // } catch (\Exception $e) {
        //     Log::error('Surepass GST verification failed', ['error' => $e->getMessage()]);
        //     return VerificationResult::rejected('GST verification service unavailable.');
        // }

        return $this->fallbackToManual($kyc);
    }

    // ── Aadhaar Verification (OTP based) ─────────────────────────────
    private function verifyAadhaar(Kyc $kyc): VerificationResult
    {
        // Aadhaar is OTP-based — needs separate flow
        // Step 1: generateAadhaarOtp() → sends OTP to user's mobile
        // Step 2: verifyAadhaarOtp()   → verifies OTP
        // This requires a 2-step controller flow — handled in KycController

        return $this->fallbackToManual($kyc);
    }

    /**
     * Generate Aadhaar OTP — call this first
     * TODO: Uncomment when Surepass token is available
     */
    public function generateAadhaarOtp(string $aadhaarNumber): array
    {
        // $response = Http::withToken($this->token)
        //     ->post("{$this->apiUrl}/aadhaar-v2/generate-otp", [
        //         'id_number' => $aadhaarNumber,
        //     ]);
        // return $response->json();

        return ['success' => false, 'message' => 'Surepass not configured yet.'];
    }

    /**
     * Verify Aadhaar OTP — call this after user enters OTP
     * TODO: Uncomment when Surepass token is available
     */
    public function verifyAadhaarOtp(string $clientId, string $otp): array
    {
        // $response = Http::withToken($this->token)
        //     ->post("{$this->apiUrl}/aadhaar-v2/submit-otp", [
        //         'client_id' => $clientId,
        //         'otp'       => $otp,
        //     ]);
        // return $response->json();

        return ['success' => false, 'message' => 'Surepass not configured yet.'];
    }

    // ── Fallback ──────────────────────────────────────────────────────
    private function fallbackToManual(Kyc $kyc): VerificationResult
    {
        Log::info('Surepass not configured — falling back to manual KYC', [
            'document_type' => $kyc->document_type,
        ]);

        return VerificationResult::pending(
            message: 'Document submitted. Pending admin verification.',
            driver:  'manual_fallback',
        );
    }

    public function canAutoVerify(): bool
    {
        return ! empty($this->token);
    }

    public function getDriverName(): string
    {
        return 'surepass';
    }

    public function getSupportedDocuments(): array
    {
        return ['aadhaar', 'pan', 'gst', 'company_pan'];
    }
}