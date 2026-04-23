<?php

namespace App\Services\Storage\Drivers;

use App\Services\Storage\Contracts\StorageDriverInterface;
use Illuminate\Support\Facades\Storage;

class LocalStorageDriver implements StorageDriverInterface
{
    public function upload(string $path, $file): string
    {
        // Store in storage/app/public/{path}
        $stored = Storage::disk('public')->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        return $stored;
    }

    public function url(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    public function delete(string $path): bool
    {
        return Storage::disk('public')->delete($path);
    }

    public function uploadMedia(string $path, $file): array
    {
        $stored = $this->upload($path, $file);

        return [
            'path'      => $stored,
            'url'       => $this->url($stored),
            'file_name' => basename($stored),
        ];
    }

    public function getDriverName(): string
    {
        return 'local';
    }
}