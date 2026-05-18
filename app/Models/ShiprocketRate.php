<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiprocketRate extends Model
{
    public $timestamps = false;

    protected $fillable = ['upload_id', 'country_code', 'weight', 'service', 'rate', 'courier_company_id'];
}
