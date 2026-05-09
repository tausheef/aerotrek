<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiprocketRate extends Model
{
    public $timestamps = false;

    protected $fillable = ['country_code', 'weight', 'service', 'rate'];
}
