@php
    $approvals = $trip->events->where('action', 'approve');
@endphp
<h2>Recorded approvals</h2>
@if ($approvals->isEmpty())
    <p>No approvals recorded yet.</p>
@else
    <table class="data">
        <tr>
            <th>Stage</th>
            <th>Approver</th>
            <th>Date and time (Manila)</th>
        </tr>
        @foreach ($approvals as $event)
            @php
                $isAvailability = $event->from_state === \App\Enums\RequestState::Submitted;
                $role = $event->from_state?->reviewer()?->label() ?? 'Approver';
            @endphp
            <tr>
                <td>{{ $isAvailability ? 'Availability check' : 'Approved' }} — {{ $role }}</td>
                <td>{{ $event->actor_name }}</td>
                <td>{{ $event->created_at->setTimezone($timezone)->format('M j, Y g:i A') }}</td>
            </tr>
        @endforeach
    </table>
    <p class="note">Recorded automatically from the system's approval workflow. These are workflow records, not cryptographic digital signatures.</p>
@endif
