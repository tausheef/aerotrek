<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Shipment;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Admin dashboard stats.
     * GET /api/v1/admin/dashboard
     */
    public function index(): JsonResponse
    {
        $stats = [
            'total_users'     => User::where('is_admin', false)->count(),
            'kyc_pending'     => User::where('kyc_status', 'pending')->count(),
            'kyc_verified'    => User::where('kyc_status', 'verified')->count(),
        ];

        return $this->successResponse(
            data: ['stats' => $stats],
            message: 'Dashboard data retrieved.'
        );
    }
}