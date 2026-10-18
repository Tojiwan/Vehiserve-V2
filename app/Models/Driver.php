<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = ['name', 'contact', 'license_number', 'status'];

    protected function casts(): array
    {
        return ['is_demo' => 'boolean'];
    }
}
