<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kyc;
use App\Services\KYC\KycService;
use App\Services\Storage\StorageService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminKycController extends Controller
{
    use ApiResponse;

    public function __construct(
        private KycService     $kycService,
        private StorageService $storage
    ) {}

    /**
     * GET /api/v1/admin/kyc
     * List all KYC submissions with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Kyc::query();

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by account type
        if ($request->account_type) {
            $query->where('account_type', $request->account_type);
        }

        $kycs = $query->orderBy('created_at', 'desc')->paginate(20);

        return $this->successResponse(data: ['kycs' => $kycs]);
    }

    /**
     * GET /api/v1/admin/kyc/{id}
     * Get single KYC with document URL.
     */
    public function show(string $id): JsonResponse
    {
        $kyc = Kyc::findOrFail($id);

        return $this->successResponse(data: [
            'kyc'          => $kyc,
            'document_url' => $this->storage->url($kyc->document_image),
        ]);
    }

    /**
     * POST /api/v1/admin/kyc/{id}/approve
     * Admin approves KYC.
     */
    public function approve(string $id): JsonResponse
    {
        $kyc   = Kyc::findOrFail($id);
        $admin = JWTAuth::user();

        if ($kyc->status === 'verified') {
            return $this->errorResponse('KYC already verified.', 400);
        }

        $kyc = $this->kycService->approve($kyc, (string) $admin->_id);

        return $this->successResponse(
            data:    ['kyc' => $kyc],
            message: 'KYC approved successfully.'
        );
    }

    /**
     * POST /api/v1/admin/kyc/{id}/reject
     * Admin rejects KYC with reason.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $kyc   = Kyc::findOrFail($id);
        $admin = JWTAuth::user();

        if ($kyc->status === 'verified') {
            return $this->errorResponse('Cannot reject an already verified KYC.', 400);
        }

        $kyc = $this->kycService->reject($kyc, (string) $admin->_id, $request->reason);

        return $this->successResponse(
            data:    ['kyc' => $kyc],
            message: 'KYC rejected.'
        );
    }

    /**
     * GET /api/v1/admin/kyc/stats
     * KYC stats for dashboard.
     */
    public function stats(): JsonResponse
    {
        return $this->successResponse(data: [
            'stats' => [
                'pending'  => Kyc::where('status', 'pending')->count(),
                'verified' => Kyc::where('status', 'verified')->count(),
                'rejected' => Kyc::where('status', 'rejected')->count(),
                'total'    => Kyc::count(),
            ],
        ]);
    }
}