<?php

namespace App\Http\Controllers\API\V1\Rate;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ExchangeRateController extends Controller
{
    use ApiResponse;

    public function show(string $currency): JsonResponse
    {
        $currency = strtoupper(trim($currency));

        if ($currency === 'INR') {
            return $this->successResponse(['rate' => 1, 'currency' => 'INR']);
        }

        $rate = Cache::remember("exchange_rate_{$currency}_INR", now()->addHours(6), function () use ($currency) {
            $response = Http::timeout(5)->get("https://open.er-api.com/v6/latest/{$currency}");

            if (!$response->successful()) {
                return null;
            }

            return $response->json('rates.INR');
        });

        if ($rate === null) {
            return $this->errorResponse('Exchange rate unavailable for ' . $currency, 503);
        }

        return $this->successResponse(['rate' => $rate, 'currency' => $currency]);
    }
}
