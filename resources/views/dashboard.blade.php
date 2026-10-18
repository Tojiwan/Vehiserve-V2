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
            <flux:heading size="lg" level="2">Your workspace is ready</flux:heading>
            <flux:text class="mt-2">Request submission and approval queues will be added in Phase 2. University trip times will use Asia/Manila.</flux:text>
        </div>

        @if (auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')))
            <flux:button variant="primary" href="{{ url('/admin') }}">Open staff panel</flux:button>
        @endif
    </section>
</x-layouts::app>
