<?php

namespace App\Policies;

use App\Enums\RequestState;
use App\Enums\Role;
use App\Models\TripRequest;
use App\Models\User;

class TripRequestPolicy
{
    public function view(User $user, TripRequest $tripRequest): bool
    {
        return (! $user->is_demo || $tripRequest->requester->is_demo)
            && ($user->role !== Role::User || $tripRequest->requester_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->role === Role::User;
    }

    public function review(User $user, TripRequest $tripRequest): bool
    {
        return $user->hasVerifiedEmail()
            && $this->view($user, $tripRequest)
            && $tripRequest->state->reviewer() === $user->role;
    }

    public function cancel(User $user, TripRequest $tripRequest): bool
    {
        if (! $user->hasVerifiedEmail() || ! $this->view($user, $tripRequest)) {
            return false;
        }

        if ($tripRequest->state === RequestState::Scheduled) {
            return $user->role === Role::MotorPool;
        }

        return $tripRequest->requester_id === $user->id
            && in_array($tripRequest->state, [
                RequestState::Submitted, RequestState::AwaitingDean, RequestState::AwaitingVp,
                RequestState::AwaitingPresident, RequestState::AwaitingMotorPool, RequestState::ReadyToSchedule,
            ], true);
    }

    public function schedule(User $user, TripRequest $tripRequest): bool
    {
        return $user->hasVerifiedEmail() && $user->role === Role::MotorPool
            && $this->view($user, $tripRequest) && $tripRequest->state === RequestState::ReadyToSchedule;
    }

    public function complete(User $user, TripRequest $tripRequest): bool
    {
        return $user->hasVerifiedEmail() && $user->role === Role::MotorPool
            && $this->view($user, $tripRequest) && $tripRequest->state === RequestState::Scheduled;
    }
}
