<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\Email\OtpService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class EmailVerificationController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OtpService $otpService) {}

    // POST /api/v1/auth/email/verify
    public function verify(Request $request): JsonResponse
    {
        $request->validate(['otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/']]);

        $user = JWTAuth::user();

        if ($user->email_verified) {
            return $this->successResponse(
                data: ['user' => new UserResource($user)],
                message: 'Email is already verified.'
            );
        }

        if (!$this->otpService->verify($user->email, $request->otp)) {
            return $this->errorResponse('Invalid or expired verification code. Please try again.', 422);
        }

        $user->update(['email_verified' => true]);

        return $this->successResponse(
            data: ['user' => new UserResource($user->fresh())],
            message: 'Email verified successfully. Welcome to AeroTrek!'
        );
    }

    // POST /api/v1/auth/email/resend
    public function resend(): JsonResponse
    {
        $user = JWTAuth::user();

        if ($user->email_verified) {
            return $this->errorResponse('Email is already verified.', 400);
        }

        $result = $this->otpService->resendOtp($user->email, $user->name);

        if (!$result['sent']) {
            return $this->errorResponse(
                "Please wait {$result['wait']} seconds before requesting a new code.",
                429
            );
        }

        return $this->successResponse(
            message: 'Verification code sent to ' . $user->email . '. Check your inbox.'
        );
    }
}
