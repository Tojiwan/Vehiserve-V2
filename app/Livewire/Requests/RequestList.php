<?php

namespace App\Livewire\Requests;

use App\Enums\RequestState;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Transport requests')]
class RequestList extends Component
{
    use WithPagination;

    #[Url]
    public string $state = '';

    public function updatedState(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $user = User::query()->findOrFail(Auth::id());
        $query = TripRequest::query()->visibleTo($user)->with('requester')->latest('id');
        if ($this->state !== '') {
            $query->where('state', $this->state);
        }

        return view('livewire.requests.request-list', ['requests' => $query->paginate(15), 'states' => RequestState::cases()]);
    }
}
