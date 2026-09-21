<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = ['brand_name', 'legal_name', 'logo_path', 'email', 'phone', 'address', 'currency'];
}
