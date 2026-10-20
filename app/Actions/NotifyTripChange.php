<?php

namespace App\Actions;

use App\Enums\RequestState;
use App\Enums\Role;
use App\Models\TripRequest;
use App\Models\User;
use App\Notifications\TripRequestUpdated;
use Illuminate\Support\Collection;
use Throwable;

/**
 * In-app notifications for meaningful workflow changes, sent after the decision
 * transaction commits. Delivery failures are logged and never roll back a decision.
 */
class NotifyTripChange
{
    public function handle(TripRequest $trip, User $actor, string $action, ?RequestState $previous = null, ?string $reason = null): void
    {
        $trip->loadMissing(['requester', 'booking.vehicle', 'booking.driver']);
        [$title, $message] = $this->message($trip, $action, $reason);
        $url = route('requests.show', $trip->id);

        foreach ($this->recipients($trip, $previous, $action) as $recipient) {
            if ($recipient->is($actor)) {
                continue; // never notify the actor about their own action
            }

            try {
                $recipient->notify(new TripRequestUpdated($title, $message, $url));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    /** @return Collection<int, User> */
    private function recipients(TripRequest $trip, ?RequestState $previous, string $action): Collection
    {
        $requester = $trip->requester;
        $demo = (bool) $requester->is_demo;
        $motorPool = fn (): Collection => $this->staff(Role::MotorPool, $demo);

        return match ($action) {
            'submitted' => $motorPool(),
            'approve' => $trip->state === RequestState::ReadyToSchedule
                ? $motorPool()->push($requester)
                : $this->staff($trip->state->reviewer(), $demo),
            'reject', 'scheduled', 'completed' => collect([$requester]),
            'cancel' => collect([$requester])->merge(
                in_array($previous, [RequestState::ReadyToSchedule, RequestState::Scheduled], true) ? $motorPool() : [],
            ),
            default => collect(),
        };
    }

    /** @phpstan-ignore-next-line reviewer() is only null for states that never receive approvals */
    private function staff(?Role $role, bool $demo): Collection
    {
        if ($role === null) {
            return collect();
        }

        return User::query()->where('role', $role)->where('is_demo', $demo)->get();
    }

    /** @return array{0: string, 1: string} */
    private function message(TripRequest $trip, string $action, ?string $reason): array
    {
        $departure = $trip->departure_at->setTimezone(config('vehiserve.display_timezone'))->format('M j, Y g:i A');
        $id = "{$trip->request_number} ({$trip->destination}, departs {$departure} Manila)";
        $assignment = $trip->booking?->vehicle !== null
            ? " {$trip->booking->vehicle->plate} · {$trip->booking->vehicle->name} with {$trip->booking->driver->name}."
            : '';

        return match ($action) {
            'submitted' => ['New request awaiting availability check', "{$trip->requester->name} submitted {$id}."],
            'approve' => $trip->state === RequestState::ReadyToSchedule
                ? ['All approvals complete', "All approvals are complete for {$id}. Motor Pool staff will schedule the trip."]
                : ['Request awaiting your decision', "{$id} is now at: {$trip->state->label()}."],
            'reject' => ['Request rejected', "{$id} was rejected.".($reason ? " Reason: {$reason}" : '')],
            'cancel' => ['Request cancelled', "{$id} was cancelled.".($reason ? " Reason: {$reason}" : '')],
            'scheduled' => ['Vehicle and driver assigned', "{$id} has been scheduled.".$assignment],
            'completed' => ['Trip completed', "{$id} is complete."],
            default => ['Request updated', "{$id} was updated."],
        };
    }
}
