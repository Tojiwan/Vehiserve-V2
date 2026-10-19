<?php

use App\Enums\RequestState;
use App\Enums\Role;
use App\Models\Driver;
use App\Models\TripRequest;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

function foundationTrip(User $requester, RequestState $state = RequestState::Submitted): TripRequest
{
    $trip = new TripRequest([
        'request_number' => 'TEST-'.str()->uuid(), 'office' => 'Demo office',
        'purpose' => 'Fictional campus visit', 'destination' => 'Demo campus',
        'departure_at' => '2026-11-01 00:00:00', 'return_at' => '2026-11-01 02:00:00',
    ]);
    $trip->requester_id = $requester->id;
    $trip->state = $state;
    $trip->save();

    return $trip;
}

it('uses PostgreSQL and seeds fictional accounts idempotently', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
    $this->seed();
    $this->seed();
    expect(User::count())->toBe(7)
        ->and(User::where('is_demo', true)->count())->toBe(7)
        ->and(Vehicle::count())->toBe(2)
        ->and(Driver::count())->toBe(2);
    foreach (User::all() as $user) {
        expect(Hash::check('Vehiserve-demo-2026!', $user->password))->toBeTrue();
    }
});

it('blocks public registration', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['email' => 'visitor@example.test'])->assertNotFound();
});

it('shares login with the panel and enforces each role', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
    $this->get('/dashboard')->assertOk()->assertSee($role->label());
    $this->get('/admin')->assertStatus($role === Role::User ? 403 : 200);
})->with(Role::cases());

it('blocks unverified staff from the panel', function () {
    $user = User::factory()->unverified()->create(['role' => Role::MotorPool]);
    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('limits request visibility to ownership and demo records', function () {
    $owner = User::factory()->create(['is_demo' => true]);
    $other = User::factory()->create();
    $trip = foundationTrip($owner);
    $realTrip = foundationTrip($other);
    $staff = User::factory()->create(['role' => Role::MotorPool, 'is_demo' => true]);
    expect($owner->can('view', $trip))->toBeTrue()
        ->and($other->can('view', $trip))->toBeFalse()
        ->and($staff->can('view', $realTrip))->toBeFalse()
        ->and(TripRequest::visibleTo($owner)->pluck('id')->all())->toBe([$trip->id])
        ->and(TripRequest::visibleTo($staff)->pluck('id')->all())->toBe([$trip->id]);
});

it('only permits the assigned role at each review stage without super admin bypass', function (Role $role) {
    $user = User::factory()->create(['role' => $role]);
    $owner = User::factory()->create();
    foreach (RequestState::cases() as $state) {
        $trip = foundationTrip($owner, $state);
        expect($user->can('review', $trip))->toBe($state->reviewer() === $role);
    }
    expect($user->can('manageAccounts', User::class))->toBe($role === Role::SuperAdmin);
})->with(Role::cases());

it('applies cancellation scheduling and completion permissions', function () {
    $owner = User::factory()->create();
    $staff = User::factory()->create(['role' => Role::MotorPool]);
    foreach (RequestState::cases() as $state) {
        $trip = foundationTrip($owner, $state);
        expect($owner->can('cancel', $trip))->toBe(! in_array($state, [RequestState::Scheduled, RequestState::Completed, RequestState::Rejected, RequestState::Cancelled], true))
            ->and($staff->can('cancel', $trip))->toBe($state === RequestState::Scheduled)
            ->and($staff->can('schedule', $trip))->toBe($state === RequestState::ReadyToSchedule)
            ->and($staff->can('complete', $trip))->toBe($state === RequestState::Scheduled);
    }
});

it('restricts fleet changes to motor pool staff and demo-owned resources', function (Role $role) {
    $user = User::factory()->create(['role' => $role, 'is_demo' => true]);
    foreach ([new Vehicle, new Driver] as $resource) {
        $resource->is_demo = true;
        expect($user->can('update', $resource))->toBe($role === Role::MotorPool);
        $resource->is_demo = false;
        expect($user->can('update', $resource))->toBeFalse();
    }
})->with(Role::cases());

it('rejects demo credential and deletion actions through Livewire', function () {
    $user = User::factory()->create(['is_demo' => true, 'role' => Role::SuperAdmin]);
    $this->actingAs($user);
    Livewire::test('pages::settings.profile')->set('email', 'changed@example.test')->call('updateProfileInformation')->assertForbidden();
    Livewire::test('pages::settings.profile')->call('resendVerificationNotification')->assertForbidden();
    Livewire::test('pages::settings.security')->set('current_password', 'password')
        ->set('password', 'new-password')->set('password_confirmation', 'new-password')->call('updatePassword')->assertForbidden();
    Livewire::test('pages::settings.delete-user-modal')->set('password', 'password')->call('deleteUser')->assertForbidden();
    expect($user->can('manageAccounts', User::class))->toBeFalse()
        ->and($user->fresh()->email)->toBe($user->email)
        ->and(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('does not send demo reset mail and rejects even a valid reset token', function () {
    Notification::fake();
    $user = User::factory()->create(['is_demo' => true]);
    $this->post('/forgot-password', ['email' => $user->email]);
    Notification::assertNothingSent();
    $this->post('/reset-password', [
        'email' => $user->email, 'token' => Password::createToken($user),
        'password' => 'new-password', 'password_confirmation' => 'new-password',
    ])->assertForbidden();
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('preserves account history when deletion is attempted', function () {
    $user = User::factory()->create();
    foundationTrip($user);
    $this->actingAs($user);
    Livewire::test('pages::settings.delete-user-modal')->set('password', 'password')->call('deleteUser')->assertForbidden();
    expect($user->fresh())->not->toBeNull();
});

it('keeps role and demo flags out of mass assignment', function () {
    $user = User::factory()->create();
    $user->fill(['role' => Role::SuperAdmin, 'is_demo' => true])->save();
    expect($user->fresh()->role)->toBe(Role::User)->and($user->fresh()->is_demo)->toBeFalse();
});

it('stores UTC trip instants and displays Manila time', function () {
    $trip = foundationTrip(User::factory()->create());
    $trip->departure_at = CarbonImmutable::createFromFormat('!Y-m-d H:i', '2026-11-01 08:00', config('vehiserve.display_timezone'))->utc();
    $trip->save();
    expect(config('app.timezone'))->toBe('UTC')
        ->and($trip->fresh()->departure_at->format('H:i'))->toBe('00:00')
        ->and($trip->fresh()->departure_at->setTimezone(config('vehiserve.display_timezone'))->format('H:i'))->toBe('08:00');
});

it('enforces date ordering in PostgreSQL', function () {
    $trip = foundationTrip(User::factory()->create());
    $trip->return_at = $trip->departure_at;
    expect(fn () => $trip->save())->toThrow(QueryException::class);
});

it('enforces positive passenger capacity in PostgreSQL', function () {
    expect(fn () => Vehicle::create(['plate' => 'INVALID', 'name' => 'Demo', 'type' => 'Van', 'passenger_capacity' => 0]))->toThrow(QueryException::class);
});
