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
            'country'       => ['required', 'string'],
            'actual_weight' => ['required', 'numeric', 'min:0.1'],
            'length'        => ['nullable', 'numeric', 'min:1'],
            'breadth'       => ['nullable', 'numeric', 'min:1'],
            'height'        => ['nullable', 'numeric', 'min:1'],
            'shipment_type' => ['required', 'in:Document,Non-Document'],
            'postcode'      => ['nullable', 'string'],
            'package_count' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $this->rateService->calculate(
            country:       $request->country,
            actualWeight:  (float) $request->actual_weight,
            length:        $request->length ? (float) $request->length : null,
            breadth:       $request->breadth ? (float) $request->breadth : null,
            height:        $request->height ? (float) $request->height : null,
            shipmentType:  $request->shipment_type,
            postcode:      $request->postcode,
            packageCount:  (int) ($request->package_count ?? 1),
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