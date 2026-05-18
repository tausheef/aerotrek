<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRateSheetJob;
use App\Models\RateUpload;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AdminUserRateController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/admin/rates/special/search?email=
     */
    public function searchUser(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)
            ->where('is_admin', false)
            ->first(['id', 'name', 'email', 'account_type', 'kyc_status']);

        if (! $user) {
            return $this->errorResponse('User not found.', 404);
        }

        return $this->successResponse(['data' => $user]);
    }

    /**
     * POST /api/v1/admin/rates/special/upload
     * Body: file (xlsx) + user_id (UUID)
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'    => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'user_id' => ['required', 'string', 'exists:users,id'],
        ]);

        $file       = $request->file('file');
        $storedPath = $file->store('rate_sheets', 'local');

        $upload = RateUpload::create([
            'filename'      => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'status'        => 'pending',
            'uploaded_by'   => auth()->id(),
            'user_id'       => $request->user_id,
        ]);

        ProcessRateSheetJob::dispatch($upload->id);

        return $this->successResponse(
            ['data' => $upload->only(['id', 'original_name', 'status', 'created_at'])],
            'File uploaded. Processing started in background.',
            202
        );
    }

    /**
     * GET /api/v1/admin/rates/special/user/{userId}
     * Returns last 3 uploads for this user.
     */
    public function userUploads(string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        $uploads = RateUpload::where('user_id', $userId)
            ->latest('created_at')
            ->take(3)
            ->get(['id', 'original_name', 'status', 'processed_rows', 'total_rows', 'error_message', 'activated_at', 'created_at']);

        return $this->successResponse([
            'user'    => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'uploads' => $uploads,
        ]);
    }

    /**
     * POST /api/v1/admin/rates/special/{id}/activate
     */
    public function activate(int $id): JsonResponse
    {
        $upload = RateUpload::whereNotNull('user_id')->findOrFail($id);

        if (! in_array($upload->status, ['superseded', 'active'])) {
            return $this->errorResponse('Only superseded or active uploads can be activated.', 422);
        }

        RateUpload::where('status', 'active')
            ->where('user_id', $upload->user_id)
            ->update(['status' => 'superseded']);

        $upload->update(['status' => 'active', 'activated_at' => now()]);

        Cache::forget("active_rate_upload_id_user_{$upload->user_id}");

        return $this->successResponse(null, 'Special rates activated for this user.');
    }

    /**
     * DELETE /api/v1/admin/rates/special/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $upload = RateUpload::whereNotNull('user_id')->findOrFail($id);

        if ($upload->filename && Storage::disk('local')->exists($upload->filename)) {
            Storage::disk('local')->delete($upload->filename);
        }

        $upload->delete();

        Cache::forget("active_rate_upload_id_user_{$upload->user_id}");

        return $this->successResponse(null, 'Upload deleted successfully.');
    }

    /**
     * GET /api/v1/admin/rates/special/{id}/status
     */
    public function status(int $id): JsonResponse
    {
        $upload = RateUpload::whereNotNull('user_id')->findOrFail($id);

        return $this->successResponse(['data' => [
            'id'             => $upload->id,
            'status'         => $upload->status,
            'processed_rows' => $upload->processed_rows,
            'total_rows'     => $upload->total_rows,
            'error_message'  => $upload->error_message,
            'activated_at'   => $upload->activated_at,
        ]]);
    }
}
