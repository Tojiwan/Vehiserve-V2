<section class="max-w-3xl space-y-8">
    <div>
        <flux:link :href="route('requests.index')" wire:navigate>Back to requests</flux:link>
        <flux:heading level="1" size="xl" class="mt-4">New transport request</flux:heading>
        <flux:text class="mt-2">Provide the trip details for Motor Pool and your approvers. Submitted details cannot be edited; cancel and submit a new request if plans change.</flux:text>
    </div>
    <form wire:submit="submit" class="space-y-6">
        <flux:input wire:model="office" label="Office or college" maxlength="255" required />
        <flux:input wire:model="destination" label="Destination" maxlength="255" required />
        <flux:textarea wire:model="purpose" label="Purpose of travel" rows="3" maxlength="5000" required />
        <fieldset class="space-y-3">
            <legend class="font-medium">Trip schedule</legend>
            <flux:text>All times are in Asia/Manila (UTC+8). Return must be after departure.</flux:text>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="departure_at" type="datetime-local" label="Departure" required />
                <flux:input wire:model="return_at" type="datetime-local" label="Return" required />
            </div>
        </fieldset>
        <flux:textarea wire:model="passengerNames" label="Passenger names" description="One name per line, up to 60 passengers. Exclude the assigned driver. Use fictional names in the demo." rows="6" maxlength="7500" required />
        <div role="alert">
            @foreach ($errors->get('passengers*') as $messages)
                @foreach ($messages as $message)
                    <flux:text class="text-red-700 dark:text-red-400">{{ $message }}</flux:text>
                @endforeach
            @endforeach
            <flux:error name="submission" />
        </div>
        <flux:text>Supporting memos and downloads will be available in the document phase.</flux:text>
        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="submit">Submit request</flux:button>
    </form>
</section>
