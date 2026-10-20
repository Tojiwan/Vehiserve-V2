<?php

namespace App\Actions;

use App\Enums\BookingState;
use App\Enums\RequestState;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransitionTripRequest
{
    public function handle(User $actor, int $id, RequestState $expectedState, string $action, ?string $reason = null): TripRequest
    {
        $trip = DB::transaction(function () use ($actor, $id, $expectedState, $action, $reason): TripRequest {
            $trip = TripRequest::query()->lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('view', $trip);
            if ($trip->state !== $expectedState) {
                throw ValidationException::withMessages(['decision' => 'This request has changed. Reload it and review the latest decision before continuing.']);
            }

            $data = Validator::make(['action' => $action, 'reason' => $reason === null ? null : trim($reason)], [
                'action' => ['required', Rule::in(['approve', 'reject', 'cancel'])],
                'reason' => [Rule::requiredIf(in_array($action, ['reject', 'cancel'], true)), 'nullable', 'string', 'max:2000'],
            ])->validate();
            Gate::forUser($actor)->authorize($action === 'cancel' ? 'cancel' : 'review', $trip);

            if ($action === 'approve' && $trip->state === RequestState::Submitted) {
                $availability = app(CheckAvailability::class)->handle($actor, $trip);
                if ($availability['vehicles'] === 0 || $availability['drivers'] === 0) {
                    throw ValidationException::withMessages(['decision' => 'No suitable vehicle or driver is currently available. Reject with a transport-unavailable reason or check again later.']);
                }
            }

            $next = match ($action) {
                'approve' => $trip->state->nextApproval(),
                'reject' => RequestState::Rejected,
                'cancel' => RequestState::Cancelled,
                default => throw ValidationException::withMessages(['action' => 'Choose a valid decision.']),
            };
            if ($next === null) {
                throw ValidationException::withMessages(['decision' => 'This request cannot advance from its current stage.']);
            }

            if ($action === 'cancel' && $trip->state === RequestState::Scheduled) {
                DB::table('bookings')->where('trip_request_id', $trip->id)->where('state', BookingState::Active->value)
                    ->update(['state' => BookingState::Cancelled->value, 'updated_at' => now()]);
            }
            $previous = $trip->state;
            $trip->state = $next;
            $trip->save();
            $trip->events()->create([
                'actor_id' => $actor->id, 'actor_name' => $actor->name,
                'action' => $action, 'from_state' => $previous, 'to_state' => $next,
                'reason' => $data['reason'], 'created_at' => now(),
            ]);

            return $trip;
        });

        DB::afterCommit(fn () => app(NotifyTripChange::class)->handle($trip, $actor, $action, $expectedState, $reason === null ? null : trim($reason)));

        return $trip;
    }
}
