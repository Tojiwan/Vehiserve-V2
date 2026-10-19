<?php

namespace App\Actions;

use App\Enums\BookingState;
use App\Models\Driver;
use App\Models\TripRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;

class CheckAvailability
{
    /** @return array{vehicles: int, drivers: int} */
    public function handle(User $actor, TripRequest $trip): array
    {
        Gate::forUser($actor)->authorize('view', $trip);
        $counts = [];
        foreach (['vehicles' => Vehicle::query(), 'drivers' => Driver::query()] as $table => $query) {
            $foreignKey = $table === 'vehicles' ? 'vehicle_id' : 'driver_id';
            $query->where('status', 'available')->when($actor->is_demo, fn ($query) => $query->where('is_demo', true));
            if ($table === 'vehicles') {
                $query->where('passenger_capacity', '>=', $trip->passengers()->count());
            }
            // Counts resources free for these times; AssignTrip rechecks under locks before booking.
            $query->whereNotExists(function (Builder $bookings) use ($trip, $table, $foreignKey) {
                $bookings->selectRaw('1')->from('bookings')->whereColumn($foreignKey, $table.'.id')
                    ->where('state', BookingState::Active->value)->where('departure_at', '<', $trip->return_at)
                    ->where('return_at', '>', $trip->departure_at);
            });
            $counts[$table] = $query->count();
        }

        return ['vehicles' => $counts['vehicles'], 'drivers' => $counts['drivers']];
    }
}
