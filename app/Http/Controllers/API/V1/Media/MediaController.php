<?php

namespace App\Http\Controllers\API\V1\Media;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\Storage\StorageService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class MediaController extends Controller
{
    use ApiResponse;

    public function __construct(private StorageService $storage) {}

    // POST /api/v1/admin/media/upload
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'   => 'required|file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx|max:5120',
            'folder' => 'nullable|string|max:50',
        ]);

        $file     = $request->file('file');
        $folder   = $request->input('folder', 'cms');
        $uploaded = $this->storage->uploadMedia($file, $folder);

        $mimeType = $file->getMimeType();
        $fileType = str_starts_with($mimeType, 'image/') ? 'image'
            : ($mimeType === 'application/pdf' ? 'pdf' : 'doc');

        $media = Media::create([
            'file_name'   => $uploaded['file_name'],
            'file_path'   => $uploaded['path'],
            'file_url'    => $uploaded['url'],
            'file_type'   => $fileType,
            'mime_type'   => $mimeType,
            'file_size'   => $file->getSize(),
            'uploaded_by' => JWTAuth::user()->id,
        ]);

        return $this->successResponse(
            data: ['media' => $media->only(['id', 'file_name', 'file_url', 'file_type', 'mime_type', 'file_size'])],
            message: 'File uploaded.',
            statusCode: 201
        );
    }

    // GET /api/v1/admin/media
    public function index(Request $request): JsonResponse
    {
        $query = Media::orderBy('created_at', 'desc');

        if ($request->filled('type') && in_array($request->type, ['image', 'pdf', 'doc'])) {
            $query->where('file_type', $request->type);
        }

        $paginated = $query->paginate(20);

        return $this->successResponse(data: [
            'media' => [
                'data'         => $paginated->items(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    // DELETE /api/v1/admin/media/{id}
    public function destroy(string $id): JsonResponse
    {
        $media = Media::findOrFail($id);
        $this->storage->delete($media->file_path);
        $media->delete();

        return $this->successResponse(message: 'Media deleted.');
    }
}
