<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->role !== Role::User;
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->role === Role::MotorPool;
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->create($user) && (! $user->is_demo || $vehicle->is_demo);
    }
}
