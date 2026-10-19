<section class="max-w-5xl space-y-8">
    <div>
        <flux:link :href="route('requests.index')" wire:navigate>Back to requests</flux:link>
        @if (auth()->user()->role !== \App\Enums\Role::User)
            <flux:link :href="\App\Filament\Resources\TripRequests\TripRequestResource::getUrl('index')" class="ms-4">Staff queue</flux:link>
        @endif
        <flux:heading level="1" size="xl" class="mt-4 break-words">{{ $trip->destination }}</flux:heading>
        <flux:text class="mt-2 break-all">{{ $trip->request_number }}</flux:text>
        <flux:badge class="mt-3">{{ $trip->state->label() }}</flux:badge>
    </div>

    @if (session('status'))
        <flux:callout role="status" icon="check-circle">{{ session('status') }}</flux:callout>
    @endif
    @if ($expectedState !== $trip->state->value)
        <flux:callout icon="information-circle">
            <flux:callout.heading>A newer decision has been recorded</flux:callout.heading>
            <flux:callout.text>Review the history below, then reload before making another decision.</flux:callout.text>
            <flux:button wire:click="reloadRequest" class="mt-3">Reload request</flux:button>
        </flux:callout>
    @endif
    <div role="alert"><flux:error name="decision" /><flux:error name="action" /></div>

    <div class="grid gap-10 lg:grid-cols-2">
        <div class="space-y-8">
            <section class="space-y-4" aria-labelledby="trip-details">
                <flux:heading level="2" size="lg" id="trip-details">Trip details</flux:heading>
                <dl class="space-y-4">
                    <div><dt class="font-medium">Requested by</dt><dd class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $trip->requester->name }} · {{ $trip->office }}</dd></div>
                    <div><dt class="font-medium">Purpose</dt><dd class="mt-1 whitespace-pre-line break-words text-zinc-600 dark:text-zinc-300">{{ $trip->purpose }}</dd></div>
                    <div><dt class="font-medium">Departure · Manila time</dt><dd class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $trip->departure_at->setTimezone(config('vehiserve.display_timezone'))->format('M j, Y · g:i A') }}</dd></div>
                    <div><dt class="font-medium">Return · Manila time</dt><dd class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $trip->return_at->setTimezone(config('vehiserve.display_timezone'))->format('M j, Y · g:i A') }}</dd></div>
                </dl>
            </section>
            <section aria-labelledby="passengers">
                <flux:heading level="2" size="lg" id="passengers">Passengers ({{ $trip->passengers->count() }})</flux:heading>
                <ul class="mt-3 list-inside list-disc space-y-1 text-zinc-600 dark:text-zinc-300">
                    @foreach ($trip->passengers as $passenger)<li>{{ $passenger->name }}</li>@endforeach
                </ul>
            </section>
        </div>

        <section aria-labelledby="history">
            <flux:heading level="2" size="lg" id="history">Approval history</flux:heading>
            <ol class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($trip->events as $event)
                    <li class="space-y-2 py-4 first:pt-0">
                        <flux:heading level="3">{{ match ($event->action) { 'submitted' => 'Request submitted', 'approve' => $event->from_state === \App\Enums\RequestState::Submitted ? 'Availability checked' : 'Approved', 'reject' => 'Rejected', 'cancel' => 'Cancelled', default => ucfirst($event->action) } }}</flux:heading>
                        <flux:text>{{ $event->actor_name }} · {{ $event->created_at->setTimezone(config('vehiserve.display_timezone'))->format('M j, Y · g:i:s A') }} Manila</flux:text>
                        <flux:text>{{ $event->to_state->label() }}</flux:text>
                        @if ($event->reason)<flux:text class="whitespace-pre-line break-words">{{ $event->reason }}</flux:text>@endif
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    @if ($availability)
        <flux:callout icon="truck">
            <flux:callout.heading>Preliminary availability</flux:callout.heading>
            <flux:callout.text>{{ $availability['vehicles'] }} suitable vehicle(s) and {{ $availability['drivers'] }} driver(s) are currently free for these times. This check does not reserve them; scheduling will check again.</flux:callout.text>
        </flux:callout>
    @endif

    @if (auth()->user()->can('review', $trip) || auth()->user()->can('cancel', $trip))
        <section class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700" aria-labelledby="decision">
            <flux:heading level="2" size="lg" id="decision">Record a decision</flux:heading>
            <flux:textarea wire:model="reason" label="Reason or note" description="A reason is required for rejection or cancellation. Decisions are recorded permanently." rows="3" maxlength="2000" />
            <div class="flex flex-wrap gap-3">
                @can('review', $trip)
                    <flux:button variant="primary" wire:click="decide('approve')" wire:loading.attr="disabled" :disabled="$expectedState !== $trip->state->value">{{ $trip->state === \App\Enums\RequestState::Submitted ? 'Confirm availability and send to Dean' : 'Approve request' }}</flux:button>
                    <flux:button variant="danger" wire:click="decide('reject')" wire:confirm="Reject this request permanently? The requester will need to submit a new request." wire:loading.attr="disabled" :disabled="$expectedState !== $trip->state->value">Reject request</flux:button>
                @endcan
                @can('cancel', $trip)
                    <flux:button variant="danger" wire:click="decide('cancel')" wire:confirm="Cancel this request? This cannot be undone." wire:loading.attr="disabled" :disabled="$expectedState !== $trip->state->value">Cancel request</flux:button>
                @endcan
            </div>
        </section>
    @endif
    @if ($trip->state === \App\Enums\RequestState::ReadyToSchedule)
        <flux:text>All approvals are complete. Vehicle and driver assignment will be available in Phase 3.</flux:text>
    @elseif ($trip->state->reviewer())
        <flux:text>Next action: {{ $trip->state->reviewer()->label() }} will review this request.</flux:text>
    @endif
</section>
