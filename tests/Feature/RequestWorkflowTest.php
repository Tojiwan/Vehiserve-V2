<?php

use App\Actions\CheckAvailability;
use App\Actions\SubmitTripRequest;
use App\Actions\TransitionTripRequest;
use App\Enums\RequestState;
use App\Enums\Role;
use App\Filament\Resources\TripRequests\Pages\ListTripRequests;
use App\Livewire\Requests\CreateRequest;
use App\Livewire\Requests\RequestList;
use App\Livewire\Requests\ShowRequest;
use App\Models\Driver;
use App\Models\RequestEvent;
use App\Models\TripRequest;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function tripInput(array $changes = []): array
{
    return array_replace([
        'office' => 'Demo College', 'purpose' => 'Fictional campus workshop',
        'destination' => 'Sample Campus', 'departure_at' => '2026-12-01T08:00',
        'return_at' => '2026-12-01T17:00', 'passengers' => ['Alex Example', 'Jamie Sample'],
    ], $changes);
}

function submittedTrip(?User $user = null): TripRequest
{
    return app(SubmitTripRequest::class)->handle($user ?? User::factory()->create(), tripInput());
}

function availableFleet(): array
{
    $vehicle = Vehicle::create(['plate' => 'TEST-'.str()->random(8), 'name' => 'Demo van', 'type' => 'Van', 'passenger_capacity' => 12, 'status' => 'available']);
    $driver = Driver::create(['name' => 'Sample Driver', 'status' => 'available']);

    return [$vehicle, $driver];
}

it('submits through Livewire with UTC times passengers and an initial audit event', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $component = Livewire::test(CreateRequest::class);
    foreach (tripInput() as $key => $value) {
        $component->set($key === 'passengers' ? 'passengerNames' : $key, is_array($value) ? implode("\n", $value) : $value);
    }
    $component->call('submit')->assertHasNoErrors();
    $trip = TripRequest::sole();
    $component->assertRedirect(route('requests.show', $trip));
    expect($trip->requester_id)->toBe($owner->id)
        ->and($trip->departure_at->format('Y-m-d H:i'))->toBe('2026-12-01 00:00')
        ->and($trip->passengers()->pluck('name')->all())->toBe(['Alex Example', 'Jamie Sample'])
        ->and($trip->events()->sole()->actor_name)->toBe($owner->name)
        ->and($trip->state)->toBe(RequestState::Submitted);
    $this->get(route('requests.show', $trip))->assertOk()->assertSee('Approval history')->assertSee('8:00 AM');
});

it('rejects invalid trip data without writing records', function (array $changes) {
    expect(fn () => app(SubmitTripRequest::class)->handle(User::factory()->create(), tripInput($changes)))
        ->toThrow(ValidationException::class);
    expect(TripRequest::count())->toBe(0)->and(RequestEvent::count())->toBe(0)->and(DB::table('passengers')->count())->toBe(0);
})->with([
    'missing purpose' => [['purpose' => '']],
    'whitespace office' => [['office' => '  ']],
    'invalid date' => [['departure_at' => '2026-02-30T08:00']],
    'return before departure' => [['return_at' => '2026-12-01T07:00']],
    'equal dates' => [['return_at' => '2026-12-01T08:00']],
    'missing passengers' => [['passengers' => []]],
    'blank passenger' => [['passengers' => ['  ']]],
    'long passenger' => [['passengers' => [str_repeat('x', 121)]]],
    'too many passengers' => [['passengers' => array_fill(0, 61, 'Demo Passenger')]],
]);

it('denies guest and non-requester submission and protects ownership on direct routes', function () {
    $owner = User::factory()->create();
    $trip = submittedTrip($owner);
    $this->get('/requests')->assertRedirect('/login');
    $this->get(route('requests.show', $trip))->assertRedirect('/login');
    $other = User::factory()->create();
    $this->actingAs($other)->get(route('requests.show', $trip))->assertForbidden();
    Livewire::test(RequestList::class)->assertDontSee($trip->request_number);
    foreach (array_filter(Role::cases(), fn ($role) => $role !== Role::User) as $role) {
        $staff = User::factory()->create(['role' => $role]);
        $this->actingAs($staff)->get('/requests/create')->assertForbidden();
    }
    $demo = User::factory()->create(['role' => Role::Dean, 'is_demo' => true]);
    $this->actingAs($demo)->get(route('requests.show', $trip))->assertForbidden();
});

it('advances exactly one stage per authorized approval and records each decision', function () {
    Notification::fake();
    availableFleet();
    $trip = submittedTrip();
    foreach ([Role::MotorPool, Role::Dean, Role::VicePresident, Role::President, Role::MotorPool] as $role) {
        $actor = User::factory()->create(['role' => $role]);
        $before = $trip->state;
        $trip = app(TransitionTripRequest::class)->handle($actor, $trip->id, $before, 'approve', 'Reviewed');
        expect($trip->state)->toBe($before->nextApproval());
        $event = $trip->events()->get()->last();
        expect($event->from_state)->toBe($before)->and($event->actor_id)->toBe($actor->id);
    }
    expect($trip->state)->toBe(RequestState::ReadyToSchedule)->and($trip->events()->count())->toBe(6);
    Notification::assertNothingSent();
});

it('rejects every wrong approval role and terminal approval stage', function (Role $role) {
    $actor = User::factory()->create(['role' => $role]);
    $trip = submittedTrip();
    foreach (RequestState::cases() as $state) {
        if ($state->reviewer() === $role) {
            continue;
        }
        $trip->state = $state;
        $trip->save();
        expect(fn () => app(TransitionTripRequest::class)->handle($actor, $trip->id, $state, 'approve'))
            ->toThrow(AuthorizationException::class);
        expect($trip->fresh()->state)->toBe($state)->and($trip->events()->count())->toBe(1);
    }
})->with(Role::cases());

it('requires rejection reasons and makes rejection terminal at every review stage', function (RequestState $state) {
    $trip = submittedTrip();
    $trip->state = $state;
    $trip->save();
    $actor = User::factory()->create(['role' => $state->reviewer()]);
    $action = app(TransitionTripRequest::class);
    expect(fn () => $action->handle($actor, $trip->id, $state, 'reject', '  '))->toThrow(ValidationException::class);
    $action->handle($actor, $trip->id, $state, 'reject', 'Transport unavailable for this trip.');
    expect($trip->fresh()->state)->toBe(RequestState::Rejected)
        ->and($trip->events()->get()->last()->reason)->toBe('Transport unavailable for this trip.');
    expect(fn () => $action->handle($actor, $trip->id, RequestState::Rejected, 'approve'))->toThrow(AuthorizationException::class);
})->with(array_values(array_filter(RequestState::cases(), fn ($state) => $state->reviewer() !== null)));

it('applies cancellation rules and requires a reason', function (RequestState $state) {
    $owner = User::factory()->create();
    $trip = submittedTrip($owner);
    $trip->state = $state;
    $trip->save();
    $action = app(TransitionTripRequest::class);
    if (in_array($state, [RequestState::Scheduled, RequestState::Completed, RequestState::Rejected, RequestState::Cancelled], true)) {
        expect(fn () => $action->handle($owner, $trip->id, $state, 'cancel', 'Plans changed'))->toThrow(AuthorizationException::class);
    } else {
        expect(fn () => $action->handle($owner, $trip->id, $state, 'cancel'))->toThrow(ValidationException::class);
        $action->handle($owner, $trip->id, $state, 'cancel', 'Plans changed');
        expect($trip->fresh()->state)->toBe(RequestState::Cancelled)->and($trip->events()->count())->toBe(2);
    }
})->with(RequestState::cases());

it('checks capacity operational status overlap adjacency and cancelled bookings without reserving', function () {
    [$vehicle, $driver] = availableFleet();
    $actor = User::factory()->create(['role' => Role::MotorPool]);
    $trip = submittedTrip();
    $check = app(CheckAvailability::class);
    expect($check->handle($actor, $trip))->toBe(['vehicles' => 1, 'drivers' => 1]);
    $vehicle->update(['passenger_capacity' => 1]);
    expect($check->handle($actor, $trip)['vehicles'])->toBe(0);
    $vehicle->update(['passenger_capacity' => 12, 'status' => 'maintenance']);
    $driver->update(['status' => 'unavailable']);
    expect($check->handle($actor, $trip))->toBe(['vehicles' => 0, 'drivers' => 0]);
    expect(fn () => app(TransitionTripRequest::class)->handle($actor, $trip->id, $trip->state, 'approve'))->toThrow(ValidationException::class);
    $vehicle->update(['status' => 'available']);
    $driver->update(['status' => 'available']);
    $booked = submittedTrip();
    $booking = DB::table('bookings')->insertGetId([
        'trip_request_id' => $booked->id, 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
        'departure_at' => $trip->departure_at, 'return_at' => $trip->return_at, 'state' => 'active',
    ]);
    expect($check->handle($actor, $trip))->toBe(['vehicles' => 0, 'drivers' => 0]);
    DB::table('bookings')->where('id', $booking)->update(['departure_at' => $trip->return_at, 'return_at' => $trip->return_at->addHour()]);
    expect($check->handle($actor, $trip))->toBe(['vehicles' => 1, 'drivers' => 1]);
    DB::table('bookings')->where('id', $booking)->update(['departure_at' => $trip->departure_at, 'state' => 'cancelled']);
    app(TransitionTripRequest::class)->handle($actor, $trip->id, $trip->state, 'approve');
    expect(DB::table('bookings')->count())->toBe(1);
});

it('cancels a scheduled booking and releases its reservation atomically', function () {
    [$vehicle, $driver] = availableFleet();
    $trip = submittedTrip();
    $trip->state = RequestState::Scheduled;
    $trip->save();
    DB::table('bookings')->insert([
        'trip_request_id' => $trip->id, 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id,
        'departure_at' => $trip->departure_at, 'return_at' => $trip->return_at, 'state' => 'active',
    ]);
    app(TransitionTripRequest::class)->handle(User::factory()->create(['role' => Role::MotorPool]), $trip->id, $trip->state, 'cancel', 'Vehicle needs repair');
    expect($trip->fresh()->state)->toBe(RequestState::Cancelled)
        ->and(DB::table('bookings')->sole()->state)->toBe('cancelled');
});

it('rejects stale Livewire decisions and keeps the first audit event', function () {
    $trip = submittedTrip();
    $trip->state = RequestState::AwaitingDean;
    $trip->save();
    $dean = User::factory()->create(['role' => Role::Dean]);
    $this->actingAs($dean);
    $stale = Livewire::test(ShowRequest::class, ['trip' => $trip]);
    app(TransitionTripRequest::class)->handle($dean, $trip->id, $trip->state, 'approve');
    $stale->set('reason', 'Old tab')->call('decide', 'reject')->assertHasErrors('decision');
    expect($trip->fresh()->state)->toBe(RequestState::AwaitingVp)->and($trip->events()->count())->toBe(2);
    $stale->call('reloadRequest')->assertHasNoErrors()->assertSet('expectedState', 'awaiting_vp');
});

it('blocks direct unauthorized Livewire decisions', function () {
    $trip = submittedTrip();
    $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin]));
    Livewire::test(ShowRequest::class, ['trip' => $trip])->call('decide', 'approve')->assertForbidden();
    expect($trip->events()->count())->toBe(1);
});

it('rolls back the state change when history cannot be saved', function () {
    $trip = submittedTrip();
    $trip->state = RequestState::AwaitingDean;
    $trip->save();
    $actor = User::factory()->create(['role' => Role::Dean]);
    $actor->name = str_repeat('x', 256);
    expect(fn () => app(TransitionTripRequest::class)->handle($actor, $trip->id, $trip->state, 'approve'))->toThrow(QueryException::class);
    expect($trip->fresh()->state)->toBe(RequestState::AwaitingDean)->and($trip->events()->count())->toBe(1);
});

it('locks the request identity and expected state against Livewire tampering', function (string $property, mixed $value) {
    $trip = submittedTrip();
    $this->actingAs($trip->requester);
    expect(fn () => Livewire::test(ShowRequest::class, ['trip' => $trip])->set($property, $value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([['tripId', 9999], ['expectedState', 'awaiting_dean']]);

it('limits submission bursts without partially saving the sixth request', function () {
    $owner = User::factory()->create();
    foreach (range(1, 5) as $attempt) {
        submittedTrip($owner);
    }
    expect(fn () => submittedTrip($owner))->toThrow(ValidationException::class);
    expect(TripRequest::count())->toBe(5)->and(RequestEvent::count())->toBe(5);
});

it('excludes real requests from demo staff queues even after clearing filters', function () {
    $real = submittedTrip();
    $demo = submittedTrip(User::factory()->create(['is_demo' => true]));
    $this->actingAs(User::factory()->create(['role' => Role::Admin, 'is_demo' => true]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ListTripRequests::class)->assertCanSeeTableRecords([$demo])->assertCanNotSeeTableRecords([$real]);
});

it('keeps event names historical and events immutable', function () {
    $owner = User::factory()->create(['name' => 'Original Name']);
    $trip = submittedTrip($owner);
    $owner->update(['name' => 'Changed Name']);
    $event = $trip->events()->sole();
    expect($event->actor_name)->toBe('Original Name');
    expect(fn () => $event->update(['reason' => 'rewritten']))->toThrow(LogicException::class);
    expect(fn () => $event->delete())->toThrow(LogicException::class);
});

it('shows only the relevant stage in reviewer queues and keeps admin read-only', function (Role $role) {
    $actor = User::factory()->create(['role' => $role]);
    $awaiting = submittedTrip();
    $awaiting->state = match ($role) {
        Role::Dean => RequestState::AwaitingDean,
        Role::VicePresident => RequestState::AwaitingVp,
        Role::President => RequestState::AwaitingPresident,
        default => RequestState::Submitted,
    };
    $awaiting->save();
    $finished = submittedTrip();
    $finished->state = RequestState::ReadyToSchedule;
    $finished->save();
    $this->actingAs($actor);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $queue = Livewire::test(ListTripRequests::class)->assertCanSeeTableRecords([$awaiting]);
    if (in_array($role, [Role::Admin, Role::SuperAdmin, Role::MotorPool], true)) {
        $queue->assertCanSeeTableRecords([$finished]);
    } else {
        $queue->assertCanNotSeeTableRecords([$finished]);
    }
    $this->get(route('requests.show', $awaiting))->assertOk();
})->with([Role::MotorPool, Role::Dean, Role::VicePresident, Role::President, Role::Admin, Role::SuperAdmin]);
