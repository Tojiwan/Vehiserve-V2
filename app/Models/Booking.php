<?php

namespace App\Models;

use App\Enums\BookingState;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property BookingState $state
 * @property CarbonImmutable $departure_at
 * @property CarbonImmutable $return_at
 */
class Booking extends Model
{
    protected $fillable = ['trip_request_id', 'vehicle_id', 'driver_id', 'departure_at', 'return_at', 'state'];

    protected function casts(): array
    {
        return [
            'state' => BookingState::class,
            'departure_at' => 'immutable_datetime',
            'return_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<TripRequest, $this> */
    public function tripRequest(): BelongsTo
    {
        return $this->belongsTo(TripRequest::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * Half-open overlap: active bookings where existing departure < new return and existing return > new departure.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeOverlapping(Builder $query, string $foreignKey, int $resourceId, CarbonInterface $departure, CarbonInterface $return): void
    {
        $query->where('state', BookingState::Active)->where($foreignKey, $resourceId)
            ->where('departure_at', '<', $return)->where('return_at', '>', $departure);
    }
}
