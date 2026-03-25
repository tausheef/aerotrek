<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return a standardized success JSON response.
     */
    protected function successResponse(
        mixed  $data = null,
        string $message = 'Success.',
        int    $statusCode = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if (! is_null($data)) {
            $response = array_merge($response, $data);
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a standardized error JSON response.
     */
    protected function errorResponse(
        string $message = 'Something went wrong.',
        int    $statusCode = 400,
        mixed  $errors = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (! is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}