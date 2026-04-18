<?php

namespace App\Http\Controllers\API\V1\Tracking;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\External\OverseasApiService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class TrackingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OverseasApiService $overseas
    ) {}

    /**
     * GET /api/v1/tracking/{awb}
     * Public endpoint — no token needed
     * Track any shipment by AWB number
     */
    public function track(string $awb): JsonResponse
    {
        try {
            // Check if we have this shipment in our DB
            $shipment = Shipment::where('awb_no', $awb)->first();

            // Fetch live tracking from Overseas API
            $tracking = $this->overseas->trackShipment($awb);

            // Update cached events in our DB
            if ($shipment) {
                $shipment->update([
                    'tracking_events'    => $tracking['events'],
                    'tracking_updated_at'=> now(),
                    'status'             => $this->mapStatus($tracking['events']),
                ]);
            }

            return $this->successResponse(data: [
                'awb_no'        => $tracking['awb_no'],
                'carrier'       => $shipment?->carrier ?? $tracking['forwarder'],
                'service'       => $shipment?->service_name,
                'destination'   => $tracking['destination'],
                'consignee'     => $tracking['consignee'],
                'forwarder'     => $tracking['forwarder'],
                'forwarding_no' => $tracking['forwarding_no'],
                'ship_date'     => $tracking['ship_date'],
                'status'        => $shipment?->status ?? 'in_transit',
                'events'        => $tracking['events'],
            ]);

        } catch (\Exception $e) {
            // If Overseas API fails, return cached data from our DB
            $shipment = Shipment::where('awb_no', $awb)->first();

            if ($shipment && $shipment->tracking_events) {
                return $this->successResponse(
                    data: [
                        'awb_no'  => $awb,
                        'carrier' => $shipment->carrier,
                        'status'  => $shipment->status,
                        'events'  => $shipment->tracking_events,
                        'cached'  => true,
                        'cached_at' => $shipment->tracking_updated_at,
                    ],
                    message: 'Showing cached tracking data.'
                );
            }

            return $this->errorResponse(
                'Tracking information not available. Please try again.',
                404
            );
        }
    }

    /**
     * Map tracking events to shipment status.
     */
    private function mapStatus(array $events): string
    {
        if (empty($events)) return 'booked';

        $latestEvent = strtolower($events[0]['description'] ?? '');

        if (str_contains($latestEvent, 'delivered')) return 'delivered';
        if (str_contains($latestEvent, 'out for delivery')) return 'out_for_delivery';
        if (str_contains($latestEvent, 'transit')) return 'in_transit';
        if (str_contains($latestEvent, 'picked')) return 'picked_up';

        return 'in_transit';
    }
}