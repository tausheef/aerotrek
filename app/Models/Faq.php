<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Faq extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'faqs';

    protected $fillable = [
        'question',
        'answer',
        'category',   // shipping | payment | tracking | general
        'order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'order'        => 'integer',
        ];
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}