<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LimitExtensionRequest;
use App\Models\Shipment;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        // ── Users ──────────────────────────────────────────────────
        $totalUsers     = User::where('is_admin', false)->count();
        $newUsersToday  = User::where('is_admin', false)->whereDate('created_at', today())->count();
        $kycPending     = User::where('kyc_status', 'pending')->where('is_admin', false)->count();
        $kycVerified    = User::where('kyc_status', 'verified')->where('is_admin', false)->count();

        // ── Shipments ──────────────────────────────────────────────
        $totalShipments    = Shipment::count();
        $shipmentsToday    = Shipment::whereDate('created_at', today())->count();
        $autoShipments     = Shipment::where('booking_type', 'auto')->count();
        $manualShipments   = Shipment::where('booking_type', 'manual')->count();
        $pendingManual     = Shipment::where('status', 'pending_acceptance')->count();

        // ── Revenue (auto bookings with price) ─────────────────────
        $totalRevenue   = Shipment::where('booking_type', 'auto')->whereNotNull('price')->sum('price');
        $revenueToday   = Shipment::where('booking_type', 'auto')
                            ->whereNotNull('price')
                            ->whereDate('created_at', today())
                            ->sum('price');

        // ── Limit requests ─────────────────────────────────────────
        $limitPending = LimitExtensionRequest::where('status', 'pending')->count();

        // ── Status breakdown ───────────────────────────────────────
        $statusBreakdown = Shipment::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // ── Last 7 days bookings (daily count) ─────────────────────
        $last7Days = collect(range(6, 0))->map(function ($daysAgo) {
            $date  = now()->subDays($daysAgo)->toDateString();
            $count = Shipment::whereDate('created_at', $date)->count();
            return ['date' => $date, 'count' => $count, 'label' => now()->subDays($daysAgo)->format('D')];
        });

        // ── Recent shipments ───────────────────────────────────────
        $recentShipments = Shipment::with('user:id,name,email')
            ->latest()
            ->take(8)
            ->get(['id', 'aerotrek_id', 'booking_type', 'status', 'carrier', 'service_name', 'price', 'created_at', 'user_id']);

        return $this->successResponse(data: [
            'stats' => [
                'total_users'       => $totalUsers,
                'new_users_today'   => $newUsersToday,
                'kyc_pending'       => $kycPending,
                'kyc_verified'      => $kycVerified,
                'total_shipments'   => $totalShipments,
                'shipments_today'   => $shipmentsToday,
                'auto_shipments'    => $autoShipments,
                'manual_shipments'  => $manualShipments,
                'pending_manual'    => $pendingManual,
                'total_revenue'     => (float) $totalRevenue,
                'revenue_today'     => (float) $revenueToday,
                'limit_pending'     => $limitPending,
            ],
            'status_breakdown' => $statusBreakdown,
            'last7days'        => $last7Days,
            'recent_shipments' => $recentShipments,
        ]);
    }
}
