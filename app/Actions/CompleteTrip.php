<?php

namespace App\Actions;

use App\Enums\BookingState;
use App\Enums\RequestState;
use App\Models\Booking;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CompleteTrip
{
    public function handle(User $actor, int $id): TripRequest
    {
        $trip = DB::transaction(function () use ($actor, $id): TripRequest {
            $trip = TripRequest::query()->lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('view', $trip);
            if ($trip->state !== RequestState::Scheduled) {
                throw ValidationException::withMessages(['decision' => 'Only a scheduled trip can be completed.']);
            }
            Gate::forUser($actor)->authorize('complete', $trip);

            Booking::query()->where('trip_request_id', $trip->id)->where('state', BookingState::Active)
                ->update(['state' => BookingState::Completed, 'updated_at' => now()]);
            $previous = $trip->state;
            $trip->state = RequestState::Completed;
            $trip->save();
            // created_at is the actual completion time shown in history.
            $trip->events()->create([
                'actor_id' => $actor->id, 'actor_name' => $actor->name,
                'action' => 'completed', 'from_state' => $previous, 'to_state' => RequestState::Completed,
                'created_at' => now(),
            ]);

            return $trip;
        });

        DB::afterCommit(fn () => app(NotifyTripChange::class)->handle($trip, $actor, 'completed'));

        return $trip;
    }
}
