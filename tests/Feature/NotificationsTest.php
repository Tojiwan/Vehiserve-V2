<?php

use App\Actions\AssignTrip;
use App\Actions\CompleteTrip;
use App\Actions\SubmitTripRequest;
use App\Actions\TransitionTripRequest;
use App\Enums\RequestState;
use App\Enums\Role;
use App\Models\Driver;
use App\Models\TripRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\TripRequestUpdated;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;

function notifyRequester(bool $demo = false): User
{
    return User::factory()->create(['role' => Role::User, 'is_demo' => $demo]);
}

function notifyStaff(Role $role, bool $demo = false): User
{
    return User::factory()->create(['role' => $role, 'is_demo' => $demo]);
}

function notifyTrip(User $requester): TripRequest
{
    return app(SubmitTripRequest::class)->handle($requester, [
        'office' => 'Demo College', 'purpose' => 'Fictional campus workshop', 'destination' => 'Sample Campus',
        'departure_at' => '2026-12-01T08:00', 'return_at' => '2026-12-01T17:00', 'passengers' => ['Alex Example'],
    ]);
}

/** @return array{0: Vehicle, 1: Driver} */
function notifyFleet(bool $demo = false): array
{
    $vehicle = Vehicle::create(['plate' => 'NOTIF-'.str()->random(8), 'name' => 'Demo van', 'type' => 'Van', 'passenger_capacity' => 12, 'status' => 'available']);
    $driver = Driver::create(['name' => 'Sample Driver', 'status' => 'available']);
    if ($demo) {
        $vehicle->forceFill(['is_demo' => true])->save();
        $driver->forceFill(['is_demo' => true])->save();
    }

    return [$vehicle, $driver];
}

it('notifies only the motor pool staff whose demo status matches the requester', function () {
    Notification::fake();
    $demoStaff = notifyStaff(Role::MotorPool, demo: true);
    $realStaff = notifyStaff(Role::MotorPool);
    $dean = notifyStaff(Role::Dean);

    notifyTrip(notifyRequester(demo: true));
    Notification::assertSentToTimes($demoStaff, TripRequestUpdated::class, 1);
    Notification::assertNotSentTo($realStaff, TripRequestUpdated::class);
    Notification::assertNotSentTo($dean, TripRequestUpdated::class);

    notifyTrip(notifyRequester());
    Notification::assertSentToTimes($realStaff, TripRequestUpdated::class, 1);
    Notification::assertSentToTimes($demoStaff, TripRequestUpdated::class, 1);
});

it('notifies each next approver, keeps mail for non-demo accounts, and never the actor', function (bool $demo) {
    Notification::fake();
    $requester = notifyRequester($demo);
    notifyFleet($demo);
    $trip = notifyTrip($requester);
    $motorPool = notifyStaff(Role::MotorPool, $demo);
    $dean = notifyStaff(Role::Dean, $demo);
    $vp = notifyStaff(Role::VicePresident, $demo);
    $president = notifyStaff(Role::President, $demo);

    app(TransitionTripRequest::class)->handle($motorPool, $trip->id, RequestState::Submitted, 'approve');
    app(TransitionTripRequest::class)->handle($dean, $trip->id, RequestState::AwaitingDean, 'approve');
    app(TransitionTripRequest::class)->handle($vp, $trip->id, RequestState::AwaitingVp, 'approve');
    app(TransitionTripRequest::class)->handle($president, $trip->id, RequestState::AwaitingPresident, 'approve');
    app(TransitionTripRequest::class)->handle($motorPool, $trip->id, RequestState::AwaitingMotorPool, 'approve');

    $channels = $demo ? ['database'] : ['database', 'mail'];
    Notification::assertSentTo($dean, TripRequestUpdated::class, fn ($notification, $sent) => $sent === $channels);
    Notification::assertSentTo($vp, TripRequestUpdated::class);
    Notification::assertSentTo($president, TripRequestUpdated::class);
    Notification::assertSentToTimes($motorPool, TripRequestUpdated::class, 1); // only the president's hand-off
    Notification::assertSentToTimes($requester, TripRequestUpdated::class, 1); // only all-approvals-complete
    Notification::assertSentTo($requester, TripRequestUpdated::class, fn ($notification) => $notification->title === 'All approvals complete');
    Notification::assertNotSentTo($motorPool, TripRequestUpdated::class, fn ($notification) => $notification->title === 'All approvals complete');
})->with([true, false]);

it('tells the requester when the trip is scheduled and completed', function () {
    Notification::fake();
    $requester = notifyRequester();
    $trip = notifyTrip($requester);
    $trip->state = RequestState::ReadyToSchedule;
    $trip->save();
    [$vehicle, $driver] = notifyFleet();
    $staff = notifyStaff(Role::MotorPool);

    app(AssignTrip::class)->handle($staff, $trip->id, $vehicle->id, $driver->id);
    Notification::assertSentTo($requester, TripRequestUpdated::class, fn ($notification) => $notification->title === 'Vehicle and driver assigned');

    app(CompleteTrip::class)->handle($staff, $trip->id);
    Notification::assertSentTo($requester, TripRequestUpdated::class, fn ($notification) => $notification->title === 'Trip completed');
});

it('notifies the requester about rejection and cancellation with the reason, but never the actor', function () {
    Notification::fake();
    $requester = notifyRequester();
    $trip = notifyTrip($requester);
    $staff = notifyStaff(Role::MotorPool);
    $otherStaff = notifyStaff(Role::MotorPool);

    app(TransitionTripRequest::class)->handle($staff, $trip->id, RequestState::Submitted, 'reject', 'No vehicle available.');
    Notification::assertSentTo($requester, TripRequestUpdated::class, fn ($notification) => str_contains($notification->message, 'No vehicle available.'));
    Notification::assertNotSentTo($staff, TripRequestUpdated::class, fn ($notification) => $notification->title === 'Request rejected');

    $scheduled = notifyTrip(notifyRequester());
    $scheduled->state = RequestState::ReadyToSchedule;
    $scheduled->save();
    [$vehicle, $driver] = notifyFleet();
    app(AssignTrip::class)->handle($staff, $scheduled->id, $vehicle->id, $driver->id);

    app(TransitionTripRequest::class)->handle($staff, $scheduled->id, RequestState::Scheduled, 'cancel', 'Vehicle recalled.');
    Notification::assertSentTo($otherStaff, TripRequestUpdated::class, fn ($notification) => $notification->title === 'Request cancelled');
    Notification::assertNotSentTo($staff, TripRequestUpdated::class, fn ($notification) => $notification->title === 'Request cancelled');
});

it('keeps decisions committed and in-app rows when email delivery fails', function () {
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1,
        'mail.mailers.smtp.timeout' => 1,
    ]);
    $requester = notifyRequester();
    notifyFleet();
    $trip = notifyTrip($requester);
    $motorPool = notifyStaff(Role::MotorPool);
    $dean = notifyStaff(Role::Dean);

    app(TransitionTripRequest::class)->handle($motorPool, $trip->id, RequestState::Submitted, 'approve');

    expect($trip->fresh()->state)->toBe(RequestState::AwaitingDean)
        ->and($dean->notifications()->count())->toBe(1)
        ->and($dean->notifications()->sole()->data['title'])->toBe('Request awaiting your decision');
});

it('marks only the signed-in user’s notifications as read', function () {
    $user = notifyRequester();
    $user->notify(new TripRequestUpdated('Update', 'Message', '/requests'));
    $notification = $user->notifications()->sole();

    $this->actingAs(notifyRequester())->patch(route('notifications.read', $notification->id))->assertNotFound();

    $this->actingAs($user)->patch(route('notifications.read', $notification->id))->assertRedirect();
    expect($notification->fresh()->read_at)->not->toBeNull();

    $user->notify(new TripRequestUpdated('Update', 'Message', '/requests'));
    $this->actingAs($user)->patch(route('notifications.read-all'))->assertRedirect();
    expect($user->unreadNotifications()->count())->toBe(0);
});

it('shows recent updates on the dashboard and enables the Filament notification bell', function () {
    expect(Filament::getPanel('admin')->hasDatabaseNotifications())->toBeTrue();

    $user = notifyRequester();
    $user->notify(new TripRequestUpdated('Awaiting your decision', 'A request needs your review.', '/requests'));
    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()->assertSee('Recent updates')->assertSee('Awaiting your decision');
});

it('lists workflow notifications in the Filament bell', function () {
    $staff = notifyStaff(Role::MotorPool);
    $staff->notify(new TripRequestUpdated('Awaiting your decision', 'A request needs your review.', '/requests'));
    $this->actingAs($staff);

    Livewire::test(DatabaseNotifications::class)
        ->assertSee('Awaiting your decision')
        ->assertSee('A request needs your review.');
});

it('does not notify for a rolled-back enclosing transaction', function () {
    Notification::fake();
    notifyStaff(Role::MotorPool);
    $requester = notifyRequester();
    DB::beginTransaction();
    notifyTrip($requester);
    Notification::assertNothingSent();
    DB::rollBack();
    Notification::assertNothingSent();
});

it('sends notifications only after the enclosing transaction commits', function () {
    Notification::fake();
    $staff = notifyStaff(Role::MotorPool);
    $requester = notifyRequester();
    DB::beginTransaction();
    notifyTrip($requester);
    Notification::assertNothingSent();
    DB::commit();
    Notification::assertSentToTimes($staff, TripRequestUpdated::class, 1);
});

it('renders requester text as text rather than notification HTML', function () {
    $staff = notifyStaff(Role::MotorPool, demo: true);
    $message = '<a href="https://example.com">Injected destination</a>';
    $staff->notify(new TripRequestUpdated('Update', $message, '/requests'));
    $this->actingAs($staff);
    Livewire::test(DatabaseNotifications::class)->assertDontSeeHtml('<a href="https://example.com">');
    expect($staff->notifications()->sole()->data['body'])->toBe(e($message));
    $this->get(route('dashboard'))->assertSee($message)->assertDontSee('<a href="https://example.com">', false);
});

it('builds the configured Brevo HTTPS transport without a network request', function () {
    config(['mail.mailers.brevo.key' => 'test-key']);
    expect(Mail::mailer('brevo')->getSymfonyTransport())
        ->toBeInstanceOf(BrevoApiTransport::class);
});
