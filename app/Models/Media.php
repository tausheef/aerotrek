<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Media extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'media';

    protected $fillable = [
        'file_name',
        'file_path',       // path in Cloudflare R2
        'file_url',        // public URL
        'file_type',       // image | pdf | doc
        'mime_type',       // image/jpeg, application/pdf etc
        'file_size',       // in bytes
        'uploaded_by',     // user _id
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }
}