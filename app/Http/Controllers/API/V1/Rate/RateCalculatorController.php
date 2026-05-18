<?php

namespace App\Http\Controllers\API\V1\Rate;

use App\Http\Controllers\Controller;
use App\Services\External\ShiprocketApiService;
use App\Services\Rate\RateCalculationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RateCalculatorController extends Controller
{
    use ApiResponse;

    public function __construct(
        private RateCalculationService $rateService,
        private ShiprocketApiService   $shiprocket,
    ) {}

    /**
     * Calculate shipping rates for all available carriers.
     *
     * POST /api/v1/rates/calculate
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'country_code'  => ['required', 'string', 'size:2'],
            'country'       => ['nullable', 'string'],
            'actual_weight' => ['required', 'numeric', 'min:0.05'],
            'length'        => ['nullable', 'numeric', 'min:1'],
            'breadth'       => ['nullable', 'numeric', 'min:1'],
            'height'        => ['nullable', 'numeric', 'min:1'],
            'shipment_type' => ['required', 'in:Document,Non-Document'],
            'postcode'      => ['nullable', 'string'],
            'package_count' => ['nullable', 'integer', 'min:1'],
            'service_type'   => ['nullable', 'in:standard,ecommerce,india_post'],
            'origin_pincode' => [
                Rule::when(
                    in_array($request->service_type, ['ecommerce', 'india_post']),
                    ['required', 'string', 'max:20'],
                    ['nullable', 'string', 'max:20']
                ),
            ],
        ]);

        $result = $this->rateService->calculate(
            countryCode:   strtoupper($request->country_code),
            country:       $request->country ?? $request->country_code,
            actualWeight:  (float) $request->actual_weight,
            length:        $request->length  ? (float) $request->length  : null,
            breadth:       $request->breadth ? (float) $request->breadth : null,
            height:        $request->height  ? (float) $request->height  : null,
            shipmentType:  $request->shipment_type,
            postcode:      $request->postcode,
            packageCount:  (int) ($request->package_count ?? 1),
            serviceType:   $request->service_type ?? 'standard',
            userId:        auth()->id(),
        );

        if (empty($result['rates'])) {
            return $this->errorResponse(
                'No carriers available for this destination. Please contact support.',
                404
            );
        }

        // For Shiprocket routes (ecommerce / india_post) — fetch the live list of
        // serviceable couriers from Shiprocket and overwrite each rate's
        // courier_company_id with the actual serviceable one. Rates that don't
        // match any live courier are marked available=false so the UI can hide them.
        //
        // Why: the rate sheet in our DB stores precomputed courier_company_ids that
        // were valid against the configured pickup pincode. The user's actual
        // pickup pincode can differ, and Shiprocket assigns different IDs per route.
        // null return = check skipped (pincode missing or API error); we leave rates
        // untouched in that case so nothing is wrongly blocked.
        if (in_array($request->service_type ?? 'standard', ['ecommerce', 'india_post'])) {
            $pincode      = $request->origin_pincode;
            $liveCouriers = $this->shiprocket->getAvailableCouriers(
                $result['chargeable_weight'],
                strtoupper($request->country_code),
                $pincode
            );

            if ($liveCouriers !== null) {
                $result['rates'] = array_map(function ($rate) use ($liveCouriers) {
                    $rateName = strtolower(trim($rate['service_name']));

                    // EXACT match only. Substring/fuzzy matching is dangerous here
                    // because "SRX Economy" silently matches "SRX Economy Pro" —
                    // Shiprocket would then book Pro and bill us Pro's cost, while
                    // we already charged the user our Economy rate. Money leak.
                    foreach ($liveCouriers as $live) {
                        if ($live['name'] === $rateName) {
                            $rate['courier_company_id'] = $live['id'];   // live, serviceable ID
                            $rate['shiprocket_cost']    = $live['rate']; // what SR charges us (info only)
                            $rate['available']          = true;
                            return $rate;
                        }
                    }

                    \Log::info('Shiprocket rate has no exact match in live couriers', [
                        'db_service'  => $rate['service_name'],
                        'live_names'  => array_map(fn($c) => $c['name'], $liveCouriers),
                    ]);
                    $rate['available'] = false;
                    return $rate;
                }, $result['rates']);

                // Filter out unavailable rates entirely — frontend should only see
                // services the user can actually book.
                $result['rates'] = array_values(array_filter(
                    $result['rates'],
                    fn($r) => ($r['available'] ?? true) !== false
                ));
            }
        }

        return $this->successResponse(
            data: $result,
            message: count($result['rates']) . ' carrier(s) available.'
        );
    }
}
