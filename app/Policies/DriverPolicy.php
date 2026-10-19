<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Driver;
use App\Models\User;

class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->role !== Role::User;
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->role === Role::MotorPool;
    }

    public function update(User $user, Driver $driver): bool
    {
        return $this->create($user) && (! $user->is_demo || $driver->is_demo);
    }
}
