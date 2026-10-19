<x-filament-panels::page>
    <x-filament::section>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:0.5rem">
                <x-filament::button color="gray" size="sm" wire:click="previousMonth">&larr; Previous</x-filament::button>
                <x-filament::button color="gray" size="sm" wire:click="currentMonth">Today</x-filament::button>
                <x-filament::button color="gray" size="sm" wire:click="nextMonth">Next &rarr;</x-filament::button>
            </div>
            <div style="font-size:1.05rem;font-weight:600">{{ $monthLabel }}</div>
        </div>

        <div style="overflow-x:auto">
            <div style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:0.5rem;margin-top:1rem;min-width:48rem">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                    <div style="font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;text-align:center;color:rgba(120,120,120,.9)">{{ $weekday }}</div>
                @endforeach
                @foreach ($days as $day)
                    <div style="min-height:7rem;border:1px solid {{ $day['isToday'] ? 'rgb(217 119 6)' : 'rgba(128,128,128,.25)' }};border-radius:.5rem;padding:.4rem;{{ $day['inMonth'] ? '' : 'opacity:.45;' }}">
                        <div style="font-size:.75rem;font-weight:600;text-align:right">{{ $day['date']->format('j') }}</div>
                        <div style="display:flex;flex-direction:column;gap:.3rem;margin-top:.25rem">
                            @foreach ($day['bookings'] as $booking)
                                <div style="border-radius:.375rem;background:rgba(128,128,128,.1);padding:.3rem .35rem;font-size:.7rem;line-height:1.1rem;overflow-wrap:anywhere">
                                    <div style="font-weight:600">{{ $booking->vehicle->plate }}</div>
                                    <div>{{ $booking->departure_at->setTimezone($timezone)->format('g:i A') }} &ndash; {{ $booking->return_at->setTimezone($timezone)->format('g:i A') }}</div>
                                    <div>{{ $booking->tripRequest->destination }}</div>
                                    <div>{{ $booking->driver->name }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-filament::section>

    <x-filament::section heading="Bookings in {{ $monthLabel }}">
        @if ($bookings->isEmpty())
            <p style="font-size:.875rem;color:rgba(120,120,120,.9)">No bookings in this month. Scheduled trips appear here and on the calendar.</p>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:.8rem">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:.5rem .4rem;border-bottom:1px solid rgba(128,128,128,.3)">Trip</th>
                            <th style="text-align:left;padding:.5rem .4rem;border-bottom:1px solid rgba(128,128,128,.3)">Departure · Manila</th>
                            <th style="text-align:left;padding:.5rem .4rem;border-bottom:1px solid rgba(128,128,128,.3)">Return · Manila</th>
                            <th style="text-align:left;padding:.5rem .4rem;border-bottom:1px solid rgba(128,128,128,.3)">Vehicle</th>
                            <th style="text-align:left;padding:.5rem .4rem;border-bottom:1px solid rgba(128,128,128,.3)">Driver</th>
                            <th style="text-align:left;padding:.5rem .4rem;border-bottom:1px solid rgba(128,128,128,.3)">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $booking)
                            <tr>
                                <td style="padding:.5rem .4rem;border-bottom:1px solid rgba(128,128,128,.15)">
                                    <div style="font-weight:600">{{ $booking->tripRequest->destination }}</div>
                                    <div style="color:rgba(120,120,120,.9)">{{ $booking->tripRequest->request_number }} · {{ $booking->tripRequest->requester->name }}</div>
                                </td>
                                <td style="padding:.5rem .4rem;border-bottom:1px solid rgba(128,128,128,.15)">{{ $booking->departure_at->setTimezone($timezone)->format('M j, Y · g:i A') }}</td>
                                <td style="padding:.5rem .4rem;border-bottom:1px solid rgba(128,128,128,.15)">{{ $booking->return_at->setTimezone($timezone)->format('M j, Y · g:i A') }}</td>
                                <td style="padding:.5rem .4rem;border-bottom:1px solid rgba(128,128,128,.15)">{{ $booking->vehicle->plate }} · {{ $booking->vehicle->name }}</td>
                                <td style="padding:.5rem .4rem;border-bottom:1px solid rgba(128,128,128,.15)">{{ $booking->driver->name }}</td>
                                <td style="padding:.5rem .4rem;border-bottom:1px solid rgba(128,128,128,.15)">
                                    <x-filament::badge :color="$booking->state === \App\Enums\BookingState::Active ? 'success' : 'gray'">{{ $booking->state->label() }}</x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
