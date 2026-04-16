<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProfileController extends Controller
{
    use ApiResponse;

    // GET /api/v1/user/profile
    public function show(): JsonResponse
    {
        $user = JWTAuth::user();
        return $this->successResponse(
            data: ['user' => new UserResource($user)],
            message: 'Profile retrieved.'
        );
    }

    // PUT /api/v1/user/profile
    public function update(Request $request): JsonResponse
    {
        $user = JWTAuth::user();

        $request->validate([
            'name'         => ['sometimes', 'string', 'min:2', 'max:100'],
            'phone'        => ['sometimes', 'string', 'min:10', 'max:15', 'unique:mongodb.users,phone,' . $user->_id . ',_id'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:150'],
        ]);

        $user->update(array_filter([
            'name'         => $request->name,
            'phone'        => $request->phone,
            'company_name' => $request->company_name,
        ], fn($v) => ! is_null($v)));

        return $this->successResponse(
            data: ['user' => new UserResource($user->fresh())],
            message: 'Profile updated.'
        );
    }
}