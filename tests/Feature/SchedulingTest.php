<?php

use App\Actions\AssignTrip;
use App\Actions\CompleteTrip;
use App\Actions\SubmitTripRequest;
use App\Actions\TransitionTripRequest;
use App\Enums\BookingState;
use App\Enums\RequestState;
use App\Enums\Role;
use App\Filament\Pages\Schedule;
use App\Filament\Resources\Drivers\Pages\ManageDrivers;
use App\Filament\Resources\TripRequests\Pages\ListTripRequests;
use App\Filament\Resources\Vehicles\Pages\ManageVehicles;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\TripRequest;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function readyTrip(?User $requester = null, array $changes = []): TripRequest
{
    $trip = app(SubmitTripRequest::class)->handle($requester ?? User::factory()->create(), array_replace([
        'office' => 'Demo College', 'purpose' => 'Fictional campus workshop', 'destination' => 'Sample Campus',
        'departure_at' => '2026-12-01T08:00', 'return_at' => '2026-12-01T17:00', 'passengers' => ['Alex Example', 'Jamie Sample'],
    ], $changes));
    $trip->state = RequestState::ReadyToSchedule;
    $trip->save();

    return $trip;
}

/** @return array{0: Vehicle, 1: Driver} */
function fleetUnit(array $vehicle = [], array $driver = []): array
{
    return [
        Vehicle::create(array_replace(['plate' => 'TEST-'.str()->random(8), 'name' => 'Demo van', 'type' => 'Van', 'passenger_capacity' => 12, 'status' => 'available'], $vehicle)),
        Driver::create(array_replace(['name' => 'Sample Driver', 'status' => 'available'], $driver)),
    ];
}

function motorPoolStaff(bool $demo = false): User
{
    return User::factory()->create(['role' => Role::MotorPool, 'is_demo' => $demo]);
}

it('keeps fleet demo ownership out of mass assignment', function () {
    [$vehicle, $driver] = fleetUnit();
    foreach ([$vehicle, $driver] as $record) {
        $record->update(['is_demo' => true]);
        expect($record->fresh()->is_demo)->toBeFalse();
    }
});

it('rolls back assignment and completion when audit history cannot be saved', function () {
    [$vehicle, $driver] = fleetUnit();
    $trip = readyTrip();
    $staff = motorPoolStaff();
    $staff->name = str_repeat('x', 256);
    expect(fn () => app(AssignTrip::class)->handle($staff, $trip->id, $vehicle->id, $driver->id))
        ->toThrow(QueryException::class);
    expect(Booking::count())->toBe(0)->and($trip->fresh()->state)->toBe(RequestState::ReadyToSchedule);
    app(AssignTrip::class)->handle($staff->fresh(), $trip->id, $vehicle->id, $driver->id);
    expect(fn () => app(CompleteTrip::class)->handle($staff, $trip->id))
        ->toThrow(QueryException::class);
    expect($trip->fresh()->state)->toBe(RequestState::Scheduled)
        ->and($trip->booking()->sole()->state)->toBe(BookingState::Active)
        ->and($trip->events()->count())->toBe(2);
});

it('handles missing and malformed calendar months on initial load and Livewire updates', function (string $month) {
    $this->actingAs(motorPoolStaff());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->get('/admin/schedule?month='.urlencode($month))->assertOk();
    Livewire::test(Schedule::class)->set('month', $month)->call('nextMonth')->assertHasNoErrors();
})->with(['', 'garbage', '2026-13', '2026-00', '2026-1', '0000-01']);

it('queries calendar boundaries as UTC instants rather than Manila clock values', function () {
    [$vehicle, $driver] = fleetUnit();
    $staff = motorPoolStaff();
    $first = readyTrip(changes: ['destination' => 'First morning', 'departure_at' => '2026-12-01T01:00', 'return_at' => '2026-12-01T02:00']);
    $next = readyTrip(changes: ['destination' => 'Next month morning', 'departure_at' => '2027-01-01T01:00', 'return_at' => '2027-01-01T02:00']);
    foreach ([$first, $next] as $trip) {
        app(AssignTrip::class)->handle($staff, $trip->id, $vehicle->id, $driver->id);
    }
    $this->actingAs($staff)->get('/admin/schedule?month=2026-12')->assertOk()
        ->assertSee('First morning')->assertDontSee('Next month morning');
});

it('hides real fleet records and driver contact details from demo staff', function () {
    [$realVehicle, $realDriver] = fleetUnit([], ['contact' => 'Private contact']);
    $this->actingAs(motorPoolStaff(demo: true));
    [$demoVehicle, $demoDriver] = fleetUnit();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ManageVehicles::class)->assertCanSeeTableRecords([$demoVehicle])->assertCanNotSeeTableRecords([$realVehicle]);
    Livewire::test(ManageDrivers::class)->assertCanSeeTableRecords([$demoDriver])->assertCanNotSeeTableRecords([$realDriver])->assertDontSee('Private contact');
});

it('rejects vehicle capacity beyond PostgreSQL smallint before saving', function () {
    $this->actingAs(motorPoolStaff());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ManageVehicles::class)->callAction('create', data: [
        'plate' => 'CAP-TEST', 'name' => 'Test bus', 'type' => 'Bus', 'passenger_capacity' => 32768, 'status' => 'available',
    ])->assertHasErrors(['mountedActions.0.data.passenger_capacity']);
    expect(Vehicle::where('plate', 'CAP-TEST')->exists())->toBeFalse();
});

it('continues an approved request through assignment and completion', function () {
    [$vehicle, $driver] = fleetUnit();
    $trip = app(SubmitTripRequest::class)->handle(User::factory()->create(), [
        'office' => 'Demo College', 'purpose' => 'Fictional campus workshop', 'destination' => 'Sample Campus',
        'departure_at' => '2026-12-01T08:00', 'return_at' => '2026-12-01T17:00', 'passengers' => ['Alex Example', 'Jamie Sample'],
    ]);
    foreach ([Role::MotorPool, Role::Dean, Role::VicePresident, Role::President, Role::MotorPool] as $role) {
        $trip = app(TransitionTripRequest::class)->handle(User::factory()->create(['role' => $role]), $trip->id, $trip->state, 'approve');
    }
    expect($trip->state)->toBe(RequestState::ReadyToSchedule)->and($trip->events()->count())->toBe(6);

    $staff = motorPoolStaff();
    $trip = app(AssignTrip::class)->handle($staff, $trip->id, $vehicle->id, $driver->id);
    expect($trip->state)->toBe(RequestState::Scheduled)->and($trip->events()->count())->toBe(7);
    $booking = $trip->booking()->sole();
    expect($booking->state)->toBe(BookingState::Active)
        ->and($booking->departure_at->equalTo($trip->departure_at))->toBeTrue()
        ->and($booking->return_at->equalTo($trip->return_at))->toBeTrue();
    $event = $trip->events()->get()->last();
    expect($event->action)->toBe('scheduled')
        ->and($event->reason)->toContain($vehicle->plate)->and($event->reason)->toContain($driver->name);

    $this->actingAs($trip->requester)->get(route('requests.show', $trip))->assertOk()
        ->assertSee('Assignment')->assertSee($vehicle->plate)->assertSee($driver->name)->assertSee('Vehicle and driver assigned');

    $trip = app(CompleteTrip::class)->handle($staff, $trip->id);
    expect($trip->state)->toBe(RequestState::Completed)
        ->and($trip->booking()->sole()->state)->toBe(BookingState::Completed)
        ->and($trip->events()->count())->toBe(8)
        ->and($trip->events()->get()->last()->action)->toBe('completed');
});

it('rejects scheduling outside the ready stage, by other roles, or twice', function () {
    [$vehicle, $driver] = fleetUnit();
    $actor = motorPoolStaff();
    $trip = readyTrip();
    $trip->state = RequestState::AwaitingVp;
    $trip->save();
    expect(fn () => app(AssignTrip::class)->handle($actor, $trip->id, $vehicle->id, $driver->id))->toThrow(ValidationException::class);

    $trip->state = RequestState::ReadyToSchedule;
    $trip->save();
    expect(fn () => app(AssignTrip::class)->handle(User::factory()->create(['role' => Role::Dean]), $trip->id, $vehicle->id, $driver->id))
        ->toThrow(AuthorizationException::class);

    app(AssignTrip::class)->handle($actor, $trip->id, $vehicle->id, $driver->id);
    expect(fn () => app(AssignTrip::class)->handle($actor, $trip->id, $vehicle->id, $driver->id))->toThrow(ValidationException::class);
    expect(Booking::count())->toBe(1)->and($trip->events()->count())->toBe(2);
});

it('rejects assignment when the vehicle or driver cannot serve the trip', function (string $vehicleStatus, int $capacity, string $driverStatus, string $error) {
    [$vehicle, $driver] = fleetUnit(['status' => $vehicleStatus, 'passenger_capacity' => $capacity], ['status' => $driverStatus]);
    $trip = readyTrip();
    try {
        app(AssignTrip::class)->handle(motorPoolStaff(), $trip->id, $vehicle->id, $driver->id);
        $this->fail('Expected assignment to be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($error);
    }
    expect($trip->fresh()->state)->toBe(RequestState::ReadyToSchedule)->and(Booking::count())->toBe(0);
})->with([
    'vehicle unavailable' => ['unavailable', 12, 'available', 'vehicle_id'],
    'vehicle in maintenance' => ['maintenance', 12, 'available', 'vehicle_id'],
    'insufficient capacity' => ['available', 1, 'available', 'vehicle_id'],
    'driver unavailable' => ['available', 12, 'unavailable', 'driver_id'],
]);

it('rejects overlapping active bookings for the same vehicle or driver', function (string $resource) {
    [$vehicle, $driver] = fleetUnit();
    [$otherVehicle, $otherDriver] = fleetUnit();
    $trip = readyTrip();
    Booking::create([
        'trip_request_id' => readyTrip()->id,
        'vehicle_id' => $resource === 'vehicle' ? $vehicle->id : $otherVehicle->id,
        'driver_id' => $resource === 'driver' ? $driver->id : $otherDriver->id,
        'departure_at' => $trip->departure_at->addHour(), 'return_at' => $trip->return_at->subHour(),
        'state' => BookingState::Active,
    ]);
    try {
        app(AssignTrip::class)->handle(motorPoolStaff(), $trip->id, $vehicle->id, $driver->id);
        $this->fail('Expected the overlap to be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($resource.'_id');
    }
    expect($trip->fresh()->state)->toBe(RequestState::ReadyToSchedule)->and(Booking::count())->toBe(1);
})->with(['vehicle', 'driver']);

it('allows adjacent bookings and ignores cancelled or completed ones', function (BookingState $state, int $startOffset, int $endOffset) {
    [$vehicle, $driver] = fleetUnit();
    $trip = readyTrip();
    Booking::create([
        'trip_request_id' => readyTrip()->id, 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
        'departure_at' => $trip->departure_at->addHours($startOffset), 'return_at' => $trip->departure_at->addHours($endOffset),
        'state' => $state,
    ]);
    app(AssignTrip::class)->handle(motorPoolStaff(), $trip->id, $vehicle->id, $driver->id);
    expect($trip->fresh()->state)->toBe(RequestState::Scheduled)->and($trip->booking()->sole()->state)->toBe(BookingState::Active);
})->with([
    'adjacent active booking' => [BookingState::Active, -2, 0],
    'cancelled booking in the same window' => [BookingState::Cancelled, 0, 9],
    'completed booking in the same window' => [BookingState::Completed, 0, 9],
]);

it('keeps demo staff inside the demo fleet and away from real requests', function () {
    [$realVehicle, $realDriver] = fleetUnit();
    $demoStaff = motorPoolStaff(demo: true);
    $this->actingAs($demoStaff);
    [$demoVehicle, $demoDriver] = fleetUnit();
    expect($demoVehicle->is_demo)->toBeTrue()->and($demoDriver->is_demo)->toBeTrue();

    $demoTrip = readyTrip(User::factory()->create(['is_demo' => true]));
    expect(fn () => app(AssignTrip::class)->handle($demoStaff, $demoTrip->id, $realVehicle->id, $realDriver->id))
        ->toThrow(ValidationException::class);
    expect(fn () => app(AssignTrip::class)->handle($demoStaff, readyTrip()->id, $demoVehicle->id, $demoDriver->id))
        ->toThrow(AuthorizationException::class);

    $trip = app(AssignTrip::class)->handle($demoStaff, $demoTrip->id, $demoVehicle->id, $demoDriver->id);
    expect($trip->state)->toBe(RequestState::Scheduled)->and($trip->booking()->sole()->vehicle_id)->toBe($demoVehicle->id);
});

it('completes only scheduled trips and only as motor pool staff', function () {
    [$vehicle, $driver] = fleetUnit();
    $actor = motorPoolStaff();
    $trip = readyTrip();
    expect(fn () => app(CompleteTrip::class)->handle($actor, $trip->id))->toThrow(ValidationException::class);

    app(AssignTrip::class)->handle($actor, $trip->id, $vehicle->id, $driver->id);
    expect(fn () => app(CompleteTrip::class)->handle(User::factory()->create(['role' => Role::Dean]), $trip->id))
        ->toThrow(AuthorizationException::class);

    app(CompleteTrip::class)->handle($actor, $trip->id);
    expect(fn () => app(CompleteTrip::class)->handle($actor, $trip->id))->toThrow(ValidationException::class);
    expect($trip->fresh()->state)->toBe(RequestState::Completed);
});

it('schedules and completes from the Filament queue', function () {
    [$vehicle, $driver] = fleetUnit();
    $trip = readyTrip();
    $this->actingAs(motorPoolStaff());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListTripRequests::class)
        ->assertCanSeeTableRecords([$trip])
        ->callAction(TestAction::make('schedule')->table($trip), data: ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id])
        ->assertHasNoErrors();
    expect($trip->fresh()->state)->toBe(RequestState::Scheduled);

    Livewire::test(ListTripRequests::class)
        ->callAction(TestAction::make('complete')->table($trip))
        ->assertHasNoErrors();
    expect($trip->fresh()->state)->toBe(RequestState::Completed)
        ->and($trip->booking()->sole()->state)->toBe(BookingState::Completed);
});

it('rejects a Filament schedule submission when another staff member booked the resources first', function () {
    [$vehicle, $driver] = fleetUnit();
    $trip = readyTrip();
    $this->actingAs(motorPoolStaff());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $component = Livewire::test(ListTripRequests::class)->mountAction(TestAction::make('schedule')->table($trip));

    // The modal still lists these resources as free; a concurrent booking wins the race first.
    Booking::create([
        'trip_request_id' => readyTrip()->id, 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
        'departure_at' => $trip->departure_at, 'return_at' => $trip->return_at, 'state' => BookingState::Active,
    ]);
    $component->setActionData(['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id])
        ->callMountedAction()
        ->assertHasErrors(['mountedActions.0.data.vehicle_id']);
    expect($trip->fresh()->state)->toBe(RequestState::ReadyToSchedule)->and(Booking::count())->toBe(1);
});

it('renders the schedule calendar and list for staff and filters demo bookings', function () {
    [$vehicle, $driver] = fleetUnit();
    $trip = readyTrip();
    app(AssignTrip::class)->handle(motorPoolStaff(), $trip->id, $vehicle->id, $driver->id);
    $month = $trip->departure_at->setTimezone(config('vehiserve.display_timezone'))->format('Y-m');

    $this->actingAs(User::factory()->create(['role' => Role::MotorPool]));
    $this->get('/admin/schedule?month='.$month)->assertOk()
        ->assertSee($vehicle->plate)->assertSee($driver->name)->assertSee($trip->destination);

    $this->actingAs(motorPoolStaff(demo: true))->get('/admin/schedule?month='.$month)->assertOk()
        ->assertDontSee($vehicle->plate);

    [$demoVehicle, $demoDriver] = fleetUnit(['is_demo' => true], ['is_demo' => true]);
    $demoTrip = readyTrip(User::factory()->create(['is_demo' => true]));
    app(AssignTrip::class)->handle(motorPoolStaff(demo: true), $demoTrip->id, $demoVehicle->id, $demoDriver->id);

    $this->actingAs(motorPoolStaff(demo: true))->get('/admin/schedule?month='.$month)->assertOk()
        ->assertSee($demoVehicle->plate)->assertSee($demoDriver->name)->assertDontSee($vehicle->plate);

    $this->actingAs(User::factory()->create())->get('/admin/schedule')->assertForbidden();
});
