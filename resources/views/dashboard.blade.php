<x-layouts::app :title="__('Dashboard')">
    <section class="max-w-3xl space-y-6">
        <div>
            <flux:heading size="xl" level="1">Welcome, {{ auth()->user()->name }}</flux:heading>
            <flux:text class="mt-2">Signed in as {{ auth()->user()->role->label() }}.</flux:text>
        </div>

        @if (auth()->user()->is_demo)
            <flux:callout icon="information-circle">
                <flux:callout.heading>Shared portfolio demo</flux:callout.heading>
                <flux:callout.text>Use fictional information only. Other visitors using this account can see its activity. Account credentials are fixed.</flux:callout.text>
            </flux:callout>
        @endif

        <div class="border-t border-zinc-200 pt-6 dark:border-zinc-700">
            <flux:heading size="lg" level="2">Your transport workspace</flux:heading>
            <flux:text class="mt-2">Submit a trip, follow its approval history, or review requests waiting for your decision. All trip times are displayed in Asia/Manila.</flux:text>
        </div>

        <div class="flex flex-wrap gap-3">
            @can('create', \App\Models\TripRequest::class)
                <flux:button variant="primary" :href="route('requests.create')" wire:navigate>New request</flux:button>
            @endcan
            <flux:button :href="route('requests.index')" wire:navigate>View requests</flux:button>
        </div>

        @php
            $notifications = auth()->user()->notifications()->latest()->take(10)->get();
        @endphp
        @if ($notifications->isNotEmpty())
            <section class="border-t border-zinc-200 pt-6 dark:border-zinc-700" aria-labelledby="updates">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg" level="2" id="updates">Recent updates</flux:heading>
                    @if ($notifications->whereNull('read_at')->isNotEmpty())
                        <form method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            @method('patch')
                            <flux:button size="sm" type="submit">Mark all as read</flux:button>
                        </form>
                    @endif
                </div>
                <ul class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($notifications as $notification)
                        <li class="space-y-1 py-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading level="3">{{ $notification->data['title'] ?? 'Request updated' }}</flux:heading>
                                @if ($notification->read_at === null)<flux:badge color="amber" size="sm">New</flux:badge>@endif
                            </div>
                            <flux:text>{{ $notification->data['message'] ?? $notification->data['body'] ?? '' }}</flux:text>
                            <flux:text>{{ $notification->created_at->setTimezone(config('vehiserve.display_timezone'))->format('M j, Y g:i A') }} Manila</flux:text>
                            <div class="flex flex-wrap items-center gap-4 pt-1">
                                <flux:link :href="$notification->data['url'] ?? route('requests.index')" wire:navigate>View request</flux:link>
                                @if ($notification->read_at === null)
                                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                        @csrf
                                        @method('patch')
                                        <button type="submit" class="cursor-pointer text-sm underline">Mark as read</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')))
            <flux:button variant="primary" href="{{ url('/admin') }}">Open staff panel</flux:button>
            <flux:link :href="\App\Filament\Resources\TripRequests\TripRequestResource::getUrl('index')">Open request queue</flux:link>
        @endif
    </section>
</x-layouts::app>
