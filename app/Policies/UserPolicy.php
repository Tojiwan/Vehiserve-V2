<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserPolicy
{
    public function updateProfile(User $user): bool
    {
        return ! $user->is_demo;
    }

    public function manageAccounts(User $user): bool
    {
        return ! $user->is_demo && $user->hasVerifiedEmail() && $user->role === Role::SuperAdmin;
    }

    public function deleteAccount(User $user): bool
    {
        return ! $user->is_demo
            && ! DB::table('trip_requests')->where('requester_id', $user->id)->exists()
            && ! DB::table('request_events')->where('actor_id', $user->id)->exists()
            && ! DB::table('attachments')->where('uploader_id', $user->id)->exists();
    }
}
