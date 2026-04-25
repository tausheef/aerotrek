<?php

namespace App\Http\Controllers\API\V1\KYC;

use App\Http\Controllers\Controller;
use App\Models\Kyc;
use App\Services\KYC\KycService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class KycController extends Controller
{
    use ApiResponse;

    public function __construct(
        private KycService $kycService
    ) {}

    // GET /api/v1/kyc
    public function status(): JsonResponse
    {
        $user = JWTAuth::user();

        $kyc = Kyc::where('user_id', $user->id)
            ->latest()
            ->first();

        // Default to individual if account_type not set (old users)
        $accountType = $user->account_type ?? 'individual';

        return $this->successResponse(data: [
            'kyc_status'        => $user->kyc_status,
            'account_type'      => $accountType,
            'kyc'               => $kyc,
            'allowed_documents' => Kyc::allowedDocuments($accountType),
        ]);
    }

    // POST /api/v1/kyc/submit
    public function submit(Request $request): JsonResponse
    {
        $user = JWTAuth::user();

        // Default to individual if account_type not set (old users)
        $accountType = $user->account_type ?? 'individual';

        // Update user account_type if missing
        if (! $user->account_type) {
            $user->update(['account_type' => 'individual']);
        }

        $allowedDocs = Kyc::allowedDocuments($accountType);

        $request->validate([
            'document_type'   => ['required', 'string', 'in:' . implode(',', $allowedDocs)],
            'document_number' => ['required', 'string', 'min:5', 'max:30'],
            'document_image'  => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        // Block if already verified
        if ($user->kyc_status === 'verified') {
            return $this->errorResponse('Your KYC is already verified.', 400);
        }

        $kyc = $this->kycService->submit(
            user:         $user,
            data:         $request->only('document_type', 'document_number'),
            documentFile: $request->file('document_image')
        );

        return $this->successResponse(
            data:       ['kyc' => $kyc],
            message:    $kyc->status === 'verified'
                ? 'KYC verified successfully!'
                : 'KYC submitted. Pending admin verification.',
            statusCode: 201
        );
    }
}