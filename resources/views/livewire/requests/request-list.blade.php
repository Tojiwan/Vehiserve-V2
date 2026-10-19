<section class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading level="1" size="xl">Transport requests</flux:heading>
            <flux:text class="mt-2">Track trip details and the decisions recorded at each stage.</flux:text>
        </div>
        @can('create', \App\Models\TripRequest::class)
            <flux:button variant="primary" :href="route('requests.create')" wire:navigate>New request</flux:button>
        @endcan
    </div>
    <flux:select wire:model.live="state" label="Filter by status" class="max-w-sm">
        <flux:select.option value="">All statuses</flux:select.option>
        @foreach ($states as $option)
            <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
        @endforeach
    </flux:select>
    <div class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700" wire:loading.class="opacity-60">
        @forelse ($requests as $trip)
            <article class="flex flex-wrap items-start justify-between gap-4 py-5">
                <div class="min-w-0 space-y-2">
                    <flux:heading level="2"><flux:link :href="route('requests.show', $trip)" wire:navigate>{{ $trip->destination }}</flux:link></flux:heading>
                    <flux:text class="break-all">{{ $trip->request_number }}</flux:text>
                    <flux:text>{{ $trip->requester->name }} · {{ $trip->departure_at->setTimezone(config('vehiserve.display_timezone'))->format('M j, Y · g:i A') }} Manila</flux:text>
                </div>
                <flux:badge>{{ $trip->state->label() }}</flux:badge>
            </article>
        @empty
            <div class="py-10">
                <flux:heading level="2">No requests {{ $state ? 'match this status' : 'yet' }}</flux:heading>
                <flux:text class="mt-2">{{ $state ? 'Choose another status or show all requests.' : 'Submitted trips will appear here with their latest approval status.' }}</flux:text>
            </div>
        @endforelse
    </div>
    {{ $requests->links() }}
</section>
