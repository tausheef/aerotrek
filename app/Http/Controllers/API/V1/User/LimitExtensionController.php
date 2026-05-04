<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\Controller;
use App\Models\LimitExtensionRequest;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;

class LimitExtensionController extends Controller
{
    use ApiResponse;

    public function store(): JsonResponse
    {
        $user = JWTAuth::user();

        $alreadyPending = LimitExtensionRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            return $this->errorResponse(
                message: 'You already have a pending limit extension request. Please wait for admin review.',
                statusCode: 409
            );
        }

        LimitExtensionRequest::create([
            'user_id' => $user->id,
            'status'  => 'pending',
        ]);

        return $this->successResponse(
            message: 'Your request has been submitted. Our team will review and extend your limit shortly.',
            statusCode: 201
        );
    }
}
