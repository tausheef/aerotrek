<?php

namespace App\Services\Storage\Contracts;

interface StorageDriverInterface
{
    /**
     * Upload a file and return the path/URL.
     */
    public function upload(string $path, $file): string;

    /**
     * Get public URL for a stored file.
     */
    public function url(string $path): string;

    /**
     * Delete a file.
     */
    public function delete(string $path): bool;

    /**
     * Upload a file and return path, url, and file_name.
     */
    public function uploadMedia(string $path, $file): array;

    /**
     * Driver name.
     */
    public function getDriverName(): string;
}