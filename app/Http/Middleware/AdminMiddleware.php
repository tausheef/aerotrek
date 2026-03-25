<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            if (! $user->is_admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Admin privileges required.',
                ], 403);
            }

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization token missing or invalid.',
            ], 401);
        }

        return $next($request);
    }
}