<?php

namespace App\Enums;

use App\Models\TripRequest;

enum TripDocument: string
{
    case VehicleRequest = 'vehicle-request';
    case TravelOrder = 'travel-order';

    public function label(): string
    {
        return match ($this) {
            self::VehicleRequest => 'Vehicle request',
            self::TravelOrder => 'Travel order',
        };
    }

    public function filename(TripRequest $trip): string
    {
        return $trip->request_number.'-'.($this === self::VehicleRequest ? 'vehicle-request' : 'travel-order').'.pdf';
    }
}
