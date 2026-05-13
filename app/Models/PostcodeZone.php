<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostcodeZone extends Model
{
    public $timestamps = false;

    protected $fillable = ['upload_id', 'carrier', 'postcode', 'zone_key'];
}
