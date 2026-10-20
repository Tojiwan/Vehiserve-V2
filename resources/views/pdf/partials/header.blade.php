@php
    $demo = (bool) $trip->requester->is_demo;
    $logo = 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('dhvsu-logo-small.png')));
@endphp
<table class="header">
    <tr>
        <td class="logo"><img src="{{ $logo }}" class="logo" alt=""></td>
        <td>
            <div class="org">VEHISERVE · Motor Pool Services</div>
            <div class="org-sub">University transport administration · All times Asia/Manila (UTC+8)</div>
        </td>
    </tr>
</table>

@if ($demo)
    <div class="notice">SAMPLE — NOT AN OFFICIAL DOCUMENT</div>
@else
    <div class="notice-plain">Portfolio recreation for demonstration only — not an official university document.</div>
@endif

<div class="title">{{ $document->label() }}</div>
<div class="subtitle">{{ $trip->request_number }} · Status: {{ $trip->state->label() }} · Generated {{ now()->setTimezone($timezone)->format('M j, Y g:i A') }} Manila</div>
