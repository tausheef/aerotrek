<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckKycStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = JWTAuth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->kyc_status !== 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'KYC verification required before booking shipments.',
                'kyc_status' => $user->kyc_status,
                'action' => $user->kyc_status === 'pending'
                    ? 'Your KYC is under review. Please wait for approval.'
                    : 'Please submit your KYC documents.',
            ], 403);
        }

        return $next($request);
    }
}