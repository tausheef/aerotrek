<?php

namespace App\Http\Controllers\API\V1\Tracking;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\External\OverseasApiService;
use App\Services\External\ShiprocketApiService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class TrackingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private OverseasApiService   $overseas,
        private ShiprocketApiService $shiprocket,
    ) {}

    /**
     * GET /api/v1/tracking/{identifier}
     * Public endpoint — no token needed.
     * Accepts: ATK ID, AWB no, or platform ref ID.
     */
    public function track(string $identifier): JsonResponse
    {
        $shipment = Shipment::findByIdentifier($identifier);
        $awb      = $shipment?->awb_no ?? $identifier;

        try {
            $tracking = $this->fetchTracking($shipment, $awb);

            if ($shipment) {
                $shipment->update([
                    'tracking_events'    => $tracking['events'],
                    'tracking_updated_at'=> now(),
                    'status'             => $this->mapStatus($tracking['events']),
                ]);
            }

            return $this->successResponse(data: [
                'aerotrek_id' => $shipment?->aerotrek_id,
                'awb_no'      => $tracking['awb_no'],
                'carrier'     => $shipment?->carrier ?? $tracking['forwarder'] ?? null,
                'service'     => $shipment?->service_name,
                'destination' => $tracking['destination'],
                'consignee'   => $tracking['consignee'],
                'ship_date'   => $tracking['ship_date'],
                'status'      => $shipment?->status ?? 'in_transit',
                'events'      => $tracking['events'],
            ]);

        } catch (\Exception $e) {
            if ($shipment) {
                $hasCached = ! empty($shipment->tracking_events);
                return $this->successResponse(
                    data: [
                        'aerotrek_id' => $shipment->aerotrek_id,
                        'awb_no'      => $shipment->awb_no,
                        'carrier'     => $shipment->carrier,
                        'service'     => $shipment->service_name,
                        'status'      => $shipment->status,
                        'events'      => $shipment->tracking_events ?? [],
                        'cached'      => $hasCached,
                        'cached_at'   => $shipment->tracking_updated_at,
                    ],
                    message: $hasCached ? 'Showing cached tracking data.' : null
                );
            }

            return $this->errorResponse(
                'Tracking information not available. Please try again.',
                404
            );
        }
    }

    private function fetchTracking(?Shipment $shipment, string $awb): array
    {
        $platform = $shipment?->platform ?? 'overseas';

        if ($platform === 'shiprocket') {
            return $this->shiprocket->trackShipment($awb);
        }

        // For Overseas shipments or AWBs not found in the DB:
        // try Overseas first; if that fails and the shipment is unknown,
        // fall back to Shiprocket so Shiprocket AWBs still get tracked.
        try {
            return $this->overseas->trackShipment($awb);
        } catch (\Exception $e) {
            if (! $shipment) {
                return $this->shiprocket->trackShipment($awb);
            }
            throw $e;
        }
    }

    private function mapStatus(array $events): string
    {
        if (empty($events)) return 'booked';

        $latestEvent = strtolower($events[0]['description'] ?? '');

        if (str_contains($latestEvent, 'delivered'))        return 'delivered';
        if (str_contains($latestEvent, 'out for delivery')) return 'out_for_delivery';
        if (str_contains($latestEvent, 'transit'))          return 'in_transit';
        if (str_contains($latestEvent, 'picked'))           return 'picked_up';

        return 'in_transit';
    }
}
