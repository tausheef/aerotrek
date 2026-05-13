<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Shipment\AerotrekInvoiceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminShipmentController extends Controller
{
    use ApiResponse;

    public function __construct(private AerotrekInvoiceService $invoiceService) {}

    /**
     * GET /api/v1/admin/shipments/manual
     * List manual bookings with optional status filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Shipment::byBookingType('manual')
            ->with('user:id,name,email,phone')
            ->orderBy('created_at', 'desc');

        if ($request->status) {
            $query->byStatus($request->status);
        }

        $shipments = $query->paginate(20);

        $stats = [
            'pending_acceptance' => Shipment::byBookingType('manual')->byStatus('pending_acceptance')->count(),
            'accepted'           => Shipment::byBookingType('manual')->byStatus('accepted')->count(),
            'rejected'           => Shipment::byBookingType('manual')->byStatus('rejected')->count(),
            'booked_today'       => Shipment::byBookingType('manual')->byStatus('booked')
                                        ->whereDate('updated_at', today())->count(),
        ];

        return $this->successResponse(data: [
            'shipments' => $shipments,
            'stats'     => $stats,
        ]);
    }

    /**
     * POST /api/v1/admin/shipments/{id}/accept
     * Staff accepts a pending manual booking request.
     */
    public function accept(string $id): JsonResponse
    {
        $shipment = Shipment::findOrFail($id);

        if ($shipment->status !== 'pending_acceptance') {
            return $this->errorResponse('Only pending shipments can be accepted.', 400);
        }

        $shipment->update(['status' => 'accepted']);

        return $this->successResponse(
            data:    ['shipment' => $shipment->fresh()],
            message: 'Shipment accepted. Please book it on the carrier and update the booking info.'
        );
    }

    /**
     * POST /api/v1/admin/shipments/{id}/reject
     * Staff rejects a pending manual booking with a reason.
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $shipment = Shipment::findOrFail($id);

        if (! in_array($shipment->status, ['pending_acceptance', 'accepted'])) {
            return $this->errorResponse('This shipment cannot be rejected at its current stage.', 400);
        }

        $shipment->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        return $this->successResponse(
            data:    ['shipment' => $shipment->fresh()],
            message: 'Shipment rejected.'
        );
    }

    /**
     * PUT /api/v1/admin/shipments/{id}/update-booking
     * Staff has manually booked on carrier. Update AWB, platform, label, etc.
     */
    public function updateBooking(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'awb_no'          => ['required', 'string', 'max:100'],
            'carrier'         => ['required', 'string', 'max:100'],
            'service_name'    => ['required', 'string', 'max:100'],
            'platform'        => ['required', 'in:overseas,shiprocket'],
            'platform_ref_id' => ['nullable', 'string', 'max:100'],
            'label_url'       => ['nullable', 'url', 'max:500'],
        ]);

        $shipment = Shipment::findOrFail($id);

        if ($shipment->status !== 'accepted') {
            return $this->errorResponse('Booking info can only be updated for accepted shipments.', 400);
        }

        $shipment->update([
            'awb_no'          => $request->awb_no,
            'tracking_no'     => $request->awb_no,
            'carrier'         => $request->carrier,
            'service_name'    => $request->service_name,
            'platform'        => $request->platform,
            'platform_ref_id' => $request->platform_ref_id,
            'label_url'       => $request->label_url,
            'status'          => 'booked',
            'tracking_events' => [
                [
                    'timestamp'   => now()->toISOString(),
                    'status'      => 'booked',
                    'description' => 'Shipment booked successfully.',
                    'location'    => null,
                ],
            ],
            'tracking_updated_at' => now(),
        ]);

        $shipment->refresh();

        // Generate AeroTrek invoice PDF now that AWB is known
        $invoiceUrl = $this->invoiceService->generate($shipment);
        if ($invoiceUrl) {
            $shipment->update(['invoice_url' => $invoiceUrl]);
            $shipment->refresh();
        }

        return $this->successResponse(
            data:    ['shipment' => $shipment],
            message: 'Booking info updated. Status set to booked.'
        );
    }

    /**
     * POST /api/v1/admin/shipments/{id}/add-tracking-event
     * Push a new tracking event and update shipment status.
     */
    public function addTrackingEvent(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status'      => ['required', 'in:booked,picked_up,in_transit,out_for_delivery,delivered,failed'],
            'description' => ['required', 'string', 'max:500'],
            'location'    => ['nullable', 'string', 'max:200'],
        ]);

        $shipment = Shipment::findOrFail($id);

        $allowedStatuses = ['booked', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed'];
        if (! in_array($shipment->status, $allowedStatuses)) {
            return $this->errorResponse('Tracking events can only be added after shipment is booked.', 400);
        }

        $events   = $shipment->tracking_events ?? [];
        $events[] = [
            'timestamp'   => now()->toISOString(),
            'status'      => $request->status,
            'description' => $request->description,
            'location'    => $request->location,
        ];

        $shipment->update([
            'status'              => $request->status,
            'tracking_events'     => $events,
            'tracking_updated_at' => now(),
        ]);

        return $this->successResponse(
            data:    ['shipment' => $shipment->fresh()],
            message: 'Tracking event added.'
        );
    }

    /**
     * GET /api/v1/admin/shipments
     * List all shipments (auto + manual) for admin overview.
     */
    public function all(Request $request): JsonResponse
    {
        $query = Shipment::with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($request->status) {
            $query->byStatus($request->status);
        }

        if ($request->booking_type) {
            $query->byBookingType($request->booking_type);
        }

        return $this->successResponse(data: [
            'shipments' => $query->paginate(20),
        ]);
    }
}
