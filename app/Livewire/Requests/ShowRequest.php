<?php

namespace App\Livewire\Requests;

use App\Actions\CheckAvailability;
use App\Actions\DeleteAttachment;
use App\Actions\StoreAttachment;
use App\Actions\TransitionTripRequest;
use App\Enums\AttachmentCategory;
use App\Enums\RequestState;
use App\Enums\Role;
use App\Models\Attachment;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts::app')]
#[Title('Request details')]
class ShowRequest extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $tripId;

    #[Locked]
    public string $expectedState;

    public string $reason = '';

    public ?TemporaryUploadedFile $newAttachment = null;

    public string $newAttachmentCategory = 'supporting_document';

    public function mount(TripRequest $trip): void
    {
        $this->authorize('view', $trip);
        $this->tripId = $trip->id;
        $this->expectedState = $trip->state->value;
    }

    public function decide(string $action, TransitionTripRequest $transition): void
    {
        $trip = $transition->handle(User::query()->findOrFail(Auth::id()), $this->tripId, RequestState::from($this->expectedState), $action, $this->reason);
        $this->expectedState = $trip->state->value;
        $this->reason = '';
        $this->resetValidation();
        session()->flash('status', 'Decision recorded. '.$trip->state->label().'.');
    }

    public function uploadAttachment(StoreAttachment $store): void
    {
        $this->validate([
            'newAttachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.StoreAttachment::MAX_KILOBYTES],
            'newAttachmentCategory' => ['required', Rule::enum(AttachmentCategory::class)],
        ]);
        $trip = TripRequest::query()->findOrFail($this->tripId);
        $store->handle(
            User::query()->findOrFail(Auth::id()),
            $trip->id,
            $this->newAttachment,
            AttachmentCategory::from($this->newAttachmentCategory),
        );
        $this->reset('newAttachment', 'newAttachmentCategory');
        $this->resetValidation();
        session()->flash('status', 'Attachment uploaded.');
    }

    public function deleteAttachment(int $attachmentId, DeleteAttachment $delete): void
    {
        $delete->handle(User::query()->findOrFail(Auth::id()), Attachment::query()->findOrFail($attachmentId));
        session()->flash('status', 'Attachment deleted.');
    }

    public function reloadRequest(): void
    {
        $trip = TripRequest::query()->findOrFail($this->tripId);
        $this->authorize('view', $trip);
        $this->expectedState = $trip->state->value;
        $this->resetValidation();
    }

    public function render(): View
    {
        $trip = TripRequest::with(['requester', 'passengers', 'events', 'booking.vehicle', 'booking.driver', 'attachments.uploader'])->findOrFail($this->tripId);
        $this->authorize('view', $trip);
        $user = User::query()->findOrFail(Auth::id());
        $availability = $user->role === Role::MotorPool && $trip->state === RequestState::Submitted
            ? app(CheckAvailability::class)->handle($user, $trip) : null;

        return view('livewire.requests.show-request', compact('trip', 'availability'));
    }
}
