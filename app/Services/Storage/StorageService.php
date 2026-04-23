<?php

namespace App\Services\Storage;

use App\Services\Storage\Contracts\StorageDriverInterface;
use App\Services\Storage\Drivers\LocalStorageDriver;
use App\Services\Storage\Drivers\R2StorageDriver;
use Illuminate\Support\Str;

class StorageService
{
    private StorageDriverInterface $driver;

    public function __construct()
    {
        $this->driver = $this->resolveDriver();
    }

    /**
     * Resolve driver from .env — swap local to r2 anytime.
     * STORAGE_DRIVER=local  → LocalStorageDriver
     * STORAGE_DRIVER=r2     → R2StorageDriver
     */
    private function resolveDriver(): StorageDriverInterface
    {
        return match (config('filesystems.storage_driver', 'local')) {
            'r2'    => new R2StorageDriver(),
            'local' => new LocalStorageDriver(),
            default => new LocalStorageDriver(),
        };
    }

    /**
     * Upload KYC document.
     */
    public function uploadKycDocument(string $userId, string $documentType, $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename  = "{$documentType}_" . time() . ".{$extension}";
        $path      = "kyc/{$userId}/{$filename}";

        return $this->driver->upload($path, $file);
    }

    /**
     * Upload profile picture.
     */
    public function uploadProfilePicture(string $userId, $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename  = "avatar_" . time() . ".{$extension}";
        $path      = "profiles/{$userId}/{$filename}";

        return $this->driver->upload($path, $file);
    }

    /**
     * Upload shipping label.
     */
    public function uploadShippingLabel(string $shipmentId, string $base64Pdf): string
    {
        $path    = "labels/{$shipmentId}/label.pdf";
        $decoded = base64_decode($base64Pdf);

        // Write decoded PDF to temp file first
        $tempPath = sys_get_temp_dir() . "/label_{$shipmentId}.pdf";
        file_put_contents($tempPath, $decoded);

        $stored = $this->driver->upload($path, new \Illuminate\Http\File($tempPath));
        unlink($tempPath);

        return $stored;
    }

    /**
     * Upload any media file to a given folder.
     * Returns ['path', 'url', 'file_name'].
     */
    public function uploadMedia($file, string $folder = 'media'): array
    {
        $extension = $file->getClientOriginalExtension();
        $filename  = "{$folder}_" . time() . '_' . Str::random(8) . ".{$extension}";
        $path      = "{$folder}/{$filename}";

        return $this->driver->uploadMedia($path, $file);
    }

    /**
     * Get public URL for any stored file.
     */
    public function url(string $path): string
    {
        return $this->driver->url($path);
    }

    /**
     * Delete a file.
     */
    public function delete(string $path): bool
    {
        return $this->driver->delete($path);
    }

    /**
     * Current driver name.
     */
    public function getDriverName(): string
    {
        return $this->driver->getDriverName();
    }
}