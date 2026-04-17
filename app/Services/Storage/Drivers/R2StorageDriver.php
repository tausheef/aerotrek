<?php

namespace App\Services\Storage\Drivers;

use App\Services\Storage\Contracts\StorageDriverInterface;
use Illuminate\Support\Facades\Storage;

class R2StorageDriver implements StorageDriverInterface
{
    public function upload(string $path, $file): string
    {
        $stored = Storage::disk('r2')->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        return $stored;
    }

    public function url(string $path): string
    {
        return Storage::disk('r2')->url($path);
    }

    public function delete(string $path): bool
    {
        return Storage::disk('r2')->delete($path);
    }

    public function getDriverName(): string
    {
        return 'r2';
    }
}