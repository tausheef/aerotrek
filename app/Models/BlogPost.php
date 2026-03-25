<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class BlogPost extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'blog_posts';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image',
        'category_id',
        'author_id',
        'meta_title',
        'meta_description',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    // Scope - only published posts for public
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}