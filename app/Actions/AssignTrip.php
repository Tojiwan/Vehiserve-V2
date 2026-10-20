<?php

namespace App\Actions;

use App\Enums\BookingState;
use App\Enums\RequestState;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\TripRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignTrip
{
    public function handle(User $actor, int $id, int $vehicleId, int $driverId): TripRequest
    {
        $trip = DB::transaction(function () use ($actor, $id, $vehicleId, $driverId): TripRequest {
            // Lock order request -> vehicle -> driver is shared by every entry point to avoid deadlocks.
            $trip = TripRequest::query()->lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('view', $trip);
            if ($trip->state !== RequestState::ReadyToSchedule) {
                throw ValidationException::withMessages(['assignment' => 'This request is not ready to schedule.']);
            }
            Gate::forUser($actor)->authorize('schedule', $trip);

            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicleId);
            $driver = Driver::query()->lockForUpdate()->findOrFail($driverId);
            if ($actor->is_demo && (! $vehicle->is_demo || ! $driver->is_demo)) {
                throw ValidationException::withMessages(['assignment' => 'Demo staff can only assign demo vehicles and drivers.']);
            }

            $passengers = $trip->passengers()->count();
            $errors = ['assignment' => 'Choose an available vehicle and driver for these times.'];
            if ($vehicle->status !== 'available') {
                $errors['vehicle_id'] = 'This vehicle is not available.';
            } elseif ($vehicle->passenger_capacity < $passengers) {
                $errors['vehicle_id'] = "This vehicle seats {$vehicle->passenger_capacity}, but the request has {$passengers} passengers.";
            } elseif (Booking::query()->overlapping('vehicle_id', $vehicle->id, $trip->departure_at, $trip->return_at)->exists()) {
                $errors['vehicle_id'] = 'This vehicle already has an active booking for these times.';
            }
            if ($driver->status !== 'available') {
                $errors['driver_id'] = 'This driver is not available.';
            } elseif (Booking::query()->overlapping('driver_id', $driver->id, $trip->departure_at, $trip->return_at)->exists()) {
                $errors['driver_id'] = 'This driver already has an active booking for these times.';
            }
            if (count($errors) > 1) {
                throw ValidationException::withMessages($errors);
            }

            Booking::create([
                'trip_request_id' => $trip->id, 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
                'departure_at' => $trip->departure_at, 'return_at' => $trip->return_at, 'state' => BookingState::Active,
            ]);
            $previous = $trip->state;
            $trip->state = RequestState::Scheduled;
            $trip->save();
            $trip->events()->create([
                'actor_id' => $actor->id, 'actor_name' => $actor->name,
                'action' => 'scheduled', 'from_state' => $previous, 'to_state' => RequestState::Scheduled,
                'reason' => "{$vehicle->plate} · {$vehicle->name} with {$driver->name}", 'created_at' => now(),
            ]);

            return $trip;
        });

        DB::afterCommit(fn () => app(NotifyTripChange::class)->handle($trip, $actor, 'scheduled'));

        return $trip;
    }
}
