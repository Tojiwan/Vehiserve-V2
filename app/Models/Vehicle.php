<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = ['plate', 'name', 'type', 'passenger_capacity', 'status'];

    protected function casts(): array
    {
        return ['passenger_capacity' => 'integer', 'is_demo' => 'boolean'];
    }
}
