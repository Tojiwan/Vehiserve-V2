<?php

namespace App\Actions;

use App\Enums\RequestState;
use App\Models\TripRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitTripRequest
{
    /** @param array<string, mixed> $input */
    public function handle(User $actor, array $input): TripRequest
    {
        Gate::forUser($actor)->authorize('create', TripRequest::class);
        $data = Validator::make($input, [
            'office' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:5000'],
            'destination' => ['required', 'string', 'max:255'],
            'departure_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'return_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:departure_at'],
            'passengers' => ['required', 'array', 'min:1', 'max:60'],
            'passengers.*' => ['required', 'string', 'max:120'],
        ])->validate();

        $key = 'trip-submission:'.$actor->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['submission' => 'Too many requests. Please wait one minute before submitting again.']);
        }
        RateLimiter::hit($key, 60);

        $trip = DB::transaction(function () use ($actor, $data): TripRequest {
            $trip = new TripRequest([
                'request_number' => 'VR-'.Str::ulid(),
                'office' => trim($data['office']),
                'purpose' => trim($data['purpose']),
                'destination' => trim($data['destination']),
                'departure_at' => CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['departure_at'], config('vehiserve.display_timezone'))->utc(),
                'return_at' => CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['return_at'], config('vehiserve.display_timezone'))->utc(),
            ]);
            $trip->requester()->associate($actor);
            $trip->state = RequestState::Submitted;
            $trip->save();
            foreach ($data['passengers'] as $name) {
                $trip->passengers()->create(['name' => trim($name)]);
            }
            $trip->events()->create([
                'actor_id' => $actor->id, 'actor_name' => $actor->name,
                'action' => 'submitted', 'from_state' => null,
                'to_state' => RequestState::Submitted, 'created_at' => now(),
            ]);

            return $trip;
        });

        DB::afterCommit(fn () => app(NotifyTripChange::class)->handle($trip, $actor, 'submitted'));

        return $trip;
    }
}
