<?php

namespace App\Http\Controllers\API\V1\Rate;

use App\Http\Controllers\Controller;
use App\Services\Rate\RateCalculationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RateCalculatorController extends Controller
{
    use ApiResponse;

    public function __construct(
        private RateCalculationService $rateService
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
            'service_type'  => ['nullable', 'in:standard,ecommerce'],
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
        );

        if (empty($result['rates'])) {
            return $this->errorResponse(
                'No carriers available for this destination. Please contact support.',
                404
            );
        }

        return $this->successResponse(
            data: $result,
            message: count($result['rates']) . ' carrier(s) available.'
        );
    }
}
