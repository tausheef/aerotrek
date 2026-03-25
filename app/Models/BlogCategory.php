<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class BlogCategory extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'blog_categories';

    protected $fillable = [
        'name',
        'slug',
    ];
}