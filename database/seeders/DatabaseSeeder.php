<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (Role::cases() as $role) {
            User::firstOrCreate(['email' => $role->value.'@demo.vehiserve.test'], [
                'name' => 'Demo '.$role->label(),
                'password' => 'Vehiserve-demo-2026!',
                'email_verified_at' => now(),
                'role' => $role,
                'is_demo' => true,
            ]);
        }

        foreach ([['DEMO-001', 'Campus Shuttle', 'Bus', 30], ['DEMO-002', 'Service Van', 'Van', 12]] as [$plate, $name, $type, $capacity]) {
            Vehicle::firstOrCreate(['plate' => $plate], [
                'name' => $name, 'type' => $type, 'passenger_capacity' => $capacity,
                'status' => 'available', 'is_demo' => true,
            ]);
        }

        foreach (['Alex Sample', 'Jamie Example'] as $name) {
            Driver::firstOrCreate(['name' => $name, 'is_demo' => true], ['status' => 'available']);
        }
    }
}
