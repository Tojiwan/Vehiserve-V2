<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Vehicle extends Model
{
    protected $fillable = ['plate', 'name', 'type', 'passenger_capacity', 'status'];

    protected function casts(): array
    {
        return ['passenger_capacity' => 'integer', 'is_demo' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Records created while signed in inherit the creator's demo status; seeder values are explicit.
        static::creating(function (self $vehicle): void {
            if (Auth::hasUser()) {
                $vehicle->is_demo = Auth::user()->is_demo;
            }
        });
    }
}
