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

        @if (auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')))
            <flux:button variant="primary" href="{{ url('/admin') }}">Open staff panel</flux:button>
            <flux:link :href="\App\Filament\Resources\TripRequests\TripRequestResource::getUrl('index')">Open request queue</flux:link>
        @endif
    </section>
</x-layouts::app>
