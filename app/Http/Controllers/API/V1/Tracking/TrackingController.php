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
     * GET /api/v1/tracking/{identifier}
     * Public endpoint — no token needed.
     * Accepts: ATK ID (ATK-20260423-000047), AWB no, or platform ref ID.
     */
    public function track(string $identifier): JsonResponse
    {
        // Resolve shipment from DB using any of the three identifiers
        $shipment = Shipment::findByIdentifier($identifier);

        // Need AWB to call the carrier tracking API
        $awb = $shipment?->awb_no ?? $identifier;

        try {
            $tracking = $this->overseas->trackShipment($awb);

            if ($shipment) {
                $shipment->update([
                    'tracking_events'    => $tracking['events'],
                    'tracking_updated_at'=> now(),
                    'status'             => $this->mapStatus($tracking['events']),
                ]);
            }

            return $this->successResponse(data: [
                'aerotrek_id'   => $shipment?->aerotrek_id,
                'platform'      => $shipment?->platform,
                'platform_ref_id' => $shipment?->platform_ref_id,
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
            // Overseas API failed — return cached data if available
            if ($shipment && $shipment->tracking_events) {
                return $this->successResponse(
                    data: [
                        'aerotrek_id'   => $shipment->aerotrek_id,
                        'platform'      => $shipment->platform,
                        'platform_ref_id' => $shipment->platform_ref_id,
                        'awb_no'        => $shipment->awb_no,
                        'carrier'       => $shipment->carrier,
                        'status'        => $shipment->status,
                        'events'        => $shipment->tracking_events,
                        'cached'        => true,
                        'cached_at'     => $shipment->tracking_updated_at,
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