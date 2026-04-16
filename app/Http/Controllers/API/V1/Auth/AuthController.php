<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    use ApiResponse;

    // POST /api/v1/auth/register
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name'           => $request->name,
            'email'          => $request->email,
            'phone'          => $request->phone,
            'password'       => $request->password,
            'account_type'   => $request->account_type,
            'company_name'   => $request->company_name ?? null,
            'wallet_balance' => 0.0,
            'kyc_status'     => 'pending',
            'is_admin'       => false,
            'email_verified' => false,
            'phone_verified' => false,
        ]);

        $token = JWTAuth::fromUser($user);

        return $this->successResponse(
            data: [
                'user'         => new UserResource($user),
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => config('jwt.ttl') * 60,
            ],
            message: 'Registration successful.',
            statusCode: 201
        );
    }

    // POST /api/v1/auth/login
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            if (! $token = JWTAuth::attempt($request->only('email', 'password'))) {
                return $this->errorResponse('Invalid email or password.', 401);
            }
        } catch (JWTException $e) {
            return $this->errorResponse('Could not create token. Please try again.', 500);
        }

        return $this->successResponse(
            data: [
                'user'         => new UserResource(JWTAuth::user()),
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => config('jwt.ttl') * 60,
            ],
            message: 'Login successful.'
        );
    }

    // POST /api/v1/auth/logout
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (JWTException $e) {
            return $this->errorResponse('Failed to logout.', 500);
        }

        return $this->successResponse(message: 'Logged out successfully.');
    }

    // POST /api/v1/auth/refresh
    public function refresh(): JsonResponse
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
        } catch (JWTException $e) {
            return $this->errorResponse('Token cannot be refreshed. Please login again.', 401);
        }

        return $this->successResponse(
            data: [
                'access_token' => $newToken,
                'token_type'   => 'bearer',
                'expires_in'   => config('jwt.ttl') * 60,
            ],
            message: 'Token refreshed.'
        );
    }

    // GET /api/v1/auth/me
    public function me(): JsonResponse
    {
        return $this->successResponse(
            data: ['user' => new UserResource(JWTAuth::user())],
            message: 'Profile retrieved.'
        );
    }
}