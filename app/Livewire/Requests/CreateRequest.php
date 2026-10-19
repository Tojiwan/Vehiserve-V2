<?php

namespace App\Livewire\Requests;

use App\Actions\SubmitTripRequest;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app')]
#[Title('New transport request')]
class CreateRequest extends Component
{
    public string $office = '';

    public string $purpose = '';

    public string $destination = '';

    public string $departure_at = '';

    public string $return_at = '';

    public string $passengerNames = '';

    public function mount(): void
    {
        $this->authorize('create', TripRequest::class);
    }

    public function submit(SubmitTripRequest $submit): void
    {
        $this->authorize('create', TripRequest::class);
        $this->validate(['passengerNames' => ['required', 'string', 'max:7500']]);
        $trip = $submit->handle(User::query()->findOrFail(Auth::id()), [
            'office' => $this->office, 'purpose' => $this->purpose,
            'destination' => $this->destination, 'departure_at' => $this->departure_at,
            'return_at' => $this->return_at,
            'passengers' => array_map('trim', preg_split('/\R/u', trim($this->passengerNames)) ?: []),
        ]);
        session()->flash('status', 'Request submitted. Motor Pool will check availability first.');
        $this->redirectRoute('requests.show', ['trip' => $trip->id], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.requests.create-request');
    }
}
