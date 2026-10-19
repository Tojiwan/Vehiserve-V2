<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Driver extends Model
{
    protected $fillable = ['name', 'contact', 'license_number', 'status'];

    protected function casts(): array
    {
        return ['is_demo' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Records created while signed in inherit the creator's demo status; seeder values are explicit.
        static::creating(function (self $driver): void {
            if (Auth::hasUser()) {
                $driver->is_demo = Auth::user()->is_demo;
            }
        });
    }
}
