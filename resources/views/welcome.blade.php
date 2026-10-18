<x-layouts::auth :title="__('VEHISERVE')">
    <div class="space-y-6">
        <x-auth-header title="University transport, organized" description="VEHISERVE is an independent portfolio recreation of a university motor pool system." />
        <flux:text>This development build provides account access and the staff workspace. Requests and approvals are coming next. All demo records are fictional.</flux:text>
        @auth
            <flux:button variant="primary" :href="route('dashboard')" class="w-full">Open workspace</flux:button>
        @else
            <flux:button variant="primary" :href="route('login')" class="w-full">Sign in</flux:button>
        @endauth
    </div>
</x-layouts::auth>
