<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Media;
use App\Services\Storage\StorageService;
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

    // POST /api/v1/user/profile/avatar
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if (! $request->hasFile('avatar')) {
            $user = JWTAuth::user();
            return $this->successResponse(data: ['avatar_url' => $user->avatar_url], message: 'No file uploaded, current avatar returned.');
        }

        $user    = JWTAuth::user();
        $file    = $request->file('avatar');
        $storage = new StorageService();

        $path = $storage->uploadProfilePicture((string) $user->_id, $file);
        $url  = $storage->url($path);

        $user->update(['avatar_url' => $url]);

        Media::create([
            'file_name'   => basename($path),
            'file_path'   => $path,
            'file_url'    => $url,
            'file_type'   => 'image',
            'mime_type'   => $file->getMimeType(),
            'file_size'   => $file->getSize(),
            'uploaded_by' => (string) $user->_id,
        ]);

        return $this->successResponse(data: ['avatar_url' => $url], message: 'Avatar updated.');
    }
}