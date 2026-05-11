<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRateSheetJob;
use App\Models\RateUpload;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AdminRateController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/admin/rates
     * Returns last 3 uploads (active, superseded, failed, pending).
     */
    public function index(): JsonResponse
    {
        $uploads = RateUpload::latest('created_at')
            ->take(3)
            ->get(['id', 'original_name', 'status', 'processed_rows', 'total_rows', 'error_message', 'activated_at', 'created_at']);

        return $this->successResponse(['data' => $uploads]);
    }

    /**
     * POST /api/v1/admin/rates/upload
     * Accepts an xlsx file, stores it, and dispatches the processing job.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'], // max 20 MB
        ]);

        $file         = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $storedPath   = $file->store('rate_sheets', 'local');

        $upload = RateUpload::create([
            'filename'      => $storedPath,
            'original_name' => $originalName,
            'status'        => 'pending',
            'uploaded_by'   => auth()->id(),
        ]);

        ProcessRateSheetJob::dispatch($upload->id);

        return $this->successResponse(
            ['data' => $upload->only(['id', 'original_name', 'status', 'created_at'])],
            'File uploaded. Processing started in background.',
            202
        );
    }

    /**
     * GET /api/v1/admin/rates/{id}/status
     * Poll a single upload's current status and progress.
     */
    public function status(int $id): JsonResponse
    {
        $upload = RateUpload::findOrFail($id);

        return $this->successResponse(['data' => [
            'id'             => $upload->id,
            'status'         => $upload->status,
            'processed_rows' => $upload->processed_rows,
            'total_rows'     => $upload->total_rows,
            'error_message'  => $upload->error_message,
            'activated_at'   => $upload->activated_at,
        ]]);
    }

    /**
     * DELETE /api/v1/admin/rates/{id}
     * Delete an upload and all its associated rates.
     * Active uploads cannot be deleted — activate another first.
     */
    public function destroy(int $id): JsonResponse
    {
        $upload = RateUpload::findOrFail($id);

        // Delete stored file from disk
        if ($upload->filename && Storage::disk('local')->exists($upload->filename)) {
            Storage::disk('local')->delete($upload->filename);
        }

        // Cascade deletes carrier_rates + shiprocket_rates automatically
        $upload->delete();

        // If the active upload was deleted, bust the rate cache
        if ($upload->status === 'active') {
            Cache::forget('active_rate_upload_id');
        }

        return $this->successResponse(null, 'Upload deleted successfully.');
    }

    /**
     * POST /api/v1/admin/rates/{id}/activate
     * Roll back to a previously superseded upload.
     */
    public function activate(int $id): JsonResponse
    {
        $upload = RateUpload::findOrFail($id);

        if (! in_array($upload->status, ['superseded', 'active'])) {
            return $this->errorResponse('Only superseded or active uploads can be activated.', 422);
        }

        RateUpload::where('status', 'active')->update(['status' => 'superseded']);

        $upload->update([
            'status'       => 'active',
            'activated_at' => now(),
        ]);

        Cache::forget('active_rate_upload_id');

        return $this->successResponse(null, 'Upload activated. Rates are now live.');
    }
}
