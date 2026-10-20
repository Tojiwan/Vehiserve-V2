<?php

use App\Actions\DeleteAttachment;
use App\Actions\GenerateTripDocument;
use App\Actions\StoreAttachment;
use App\Actions\SubmitTripRequest;
use App\Actions\TransitionTripRequest;
use App\Enums\AttachmentCategory;
use App\Enums\RequestState;
use App\Enums\Role;
use App\Enums\TripDocument;
use App\Livewire\Requests\ShowRequest;
use App\Models\Attachment;
use App\Models\Driver;
use App\Models\TripRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function documentRequester(bool $demo = false): User
{
    return User::factory()->create(['role' => Role::User, 'is_demo' => $demo]);
}

function documentTrip(?User $requester = null): TripRequest
{
    return app(SubmitTripRequest::class)->handle($requester ?? documentRequester(), [
        'office' => 'Demo College', 'purpose' => 'Fictional campus workshop', 'destination' => 'Sample Campus',
        'departure_at' => '2026-12-01T08:00', 'return_at' => '2026-12-01T17:00', 'passengers' => ['Alex Example'],
    ]);
}

function documentPdf(string $name = 'evidence.pdf', int $kilobytes = 10): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, str_pad("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n", $kilobytes * 1024));
}

function documentFleet(): void
{
    Vehicle::create(['plate' => 'DOC-'.str()->random(8), 'name' => 'Demo van', 'type' => 'Van', 'passenger_capacity' => 12, 'status' => 'available']);
    Driver::create(['name' => 'Sample Driver', 'status' => 'available']);
}

it('stores a valid attachment with an opaque path and category', function () {
    Storage::fake(Attachment::DISK);
    $requester = documentRequester();
    $trip = documentTrip($requester);

    Livewire::actingAs($requester)->test(ShowRequest::class, ['trip' => $trip])
        ->set('newAttachmentCategory', 'endorsement_letter')
        ->set('newAttachment', documentPdf('endorsement.pdf', 120))
        ->call('uploadAttachment')
        ->assertHasNoErrors();

    $attachment = $trip->attachments()->sole();
    expect($attachment->category)->toBe(AttachmentCategory::Endorsement)
        ->and($attachment->original_name)->toBe('endorsement.pdf')
        ->and($attachment->uploader_id)->toBe($requester->id)
        ->and($attachment->disk)->toBe(Attachment::DISK)
        ->and($attachment->path)->toStartWith('requests/'.$trip->request_number.'/')
        ->and($attachment->path)->not->toContain('endorsement');
    Storage::disk(Attachment::DISK)->assertExists($attachment->path);
});

it('rejects disallowed file types and oversized uploads without storing anything', function (string $name, int $kilobytes) {
    Storage::fake(Attachment::DISK);
    $requester = documentRequester();
    $trip = documentTrip($requester);

    Livewire::actingAs($requester)->test(ShowRequest::class, ['trip' => $trip])
        ->set('newAttachment', UploadedFile::fake()->create($name, $kilobytes, 'application/octet-stream'))
        ->call('uploadAttachment')
        ->assertHasErrors('newAttachment');

    expect($trip->attachments()->count())->toBe(0)
        ->and(Storage::disk(Attachment::DISK)->allFiles())->toBe([]);
})->with([['malware.exe', 10], ['report.pdf', 6000]]);

it('caps attachments at three per request in the shared action', function () {
    Storage::fake(Attachment::DISK);
    $requester = documentRequester();
    $trip = documentTrip($requester);
    $store = app(StoreAttachment::class);

    foreach (range(1, StoreAttachment::MAX_PER_REQUEST) as $index) {
        $store->handle($requester, $trip->id, documentPdf("file{$index}.pdf"), AttachmentCategory::Supporting);
    }
    expect($trip->attachments()->count())->toBe(3);

    expect(fn () => $store->handle($requester, $trip->id, documentPdf('fourth.pdf'), AttachmentCategory::Supporting))
        ->toThrow(ValidationException::class);
    expect($trip->attachments()->count())->toBe(3);
});

it('allows only the requester to upload, and never on terminal requests', function () {
    Storage::fake(Attachment::DISK);
    $requester = documentRequester();
    $trip = documentTrip($requester);
    $store = app(StoreAttachment::class);

    expect(fn () => $store->handle(documentRequester(), $trip->id, documentPdf(), AttachmentCategory::Supporting))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $store->handle(User::factory()->create(['role' => Role::MotorPool]), $trip->id, documentPdf(), AttachmentCategory::Supporting))
        ->toThrow(AuthorizationException::class);

    $trip->state = RequestState::Cancelled;
    $trip->save();
    expect(fn () => $store->handle($requester, $trip->id, documentPdf(), AttachmentCategory::Supporting))
        ->toThrow(AuthorizationException::class);
});

it('serves attachments only to authorized users through short-lived signed links', function () {
    Storage::fake(Attachment::DISK);
    $requester = documentRequester();
    $trip = documentTrip($requester);
    $attachment = app(StoreAttachment::class)->handle($requester, $trip->id, documentPdf(), AttachmentCategory::Supporting);
    $url = $attachment->downloadUrl();

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($requester)->get($url)->assertOk()->assertDownload('evidence.pdf');
    $this->actingAs(documentRequester())->get($url)->assertForbidden();
    $this->actingAs($requester)->get($url.'x')->assertForbidden();

    $expired = URL::temporarySignedRoute('attachments.download', now()->subMinute(), ['trip' => $trip->id, 'attachment' => $attachment->id]);
    $this->actingAs($requester)->get($expired)->assertForbidden();

    $otherTrip = documentTrip(documentRequester());
    $mismatched = URL::temporarySignedRoute('attachments.download', now()->addMinutes(5), ['trip' => $otherTrip->id, 'attachment' => $attachment->id]);
    $this->actingAs($requester)->get($mismatched)->assertNotFound();
});

it('lets the uploader delete an attachment and removes the stored file', function () {
    Storage::fake(Attachment::DISK);
    $requester = documentRequester();
    $trip = documentTrip($requester);
    $attachment = app(StoreAttachment::class)->handle($requester, $trip->id, documentPdf(), AttachmentCategory::Supporting);

    expect(fn () => app(DeleteAttachment::class)->handle(documentRequester(), $attachment))
        ->toThrow(AuthorizationException::class);

    Livewire::actingAs($requester)->test(ShowRequest::class, ['trip' => $trip])
        ->call('deleteAttachment', $attachment->id);

    expect(Attachment::query()->whereKey($attachment->id)->exists())->toBeFalse();
    Storage::disk(Attachment::DISK)->assertMissing($attachment->path);
});

it('renders document templates from the shared trip record with recorded approvals', function () {
    $requester = documentRequester();
    documentFleet();
    $trip = documentTrip($requester);
    $motorPool = User::factory()->create(['role' => Role::MotorPool]);
    app(TransitionTripRequest::class)->handle($motorPool, $trip->id, RequestState::Submitted, 'approve');
    $trip = $trip->fresh()->load(['requester', 'passengers', 'events']);

    $data = ['trip' => $trip, 'document' => TripDocument::VehicleRequest, 'timezone' => 'Asia/Manila'];
    $vehicleRequest = view('pdf.vehicle-request', $data)->render();
    $travelOrder = view('pdf.travel-order', ['trip' => $trip, 'document' => TripDocument::TravelOrder, 'timezone' => 'Asia/Manila'])->render();

    expect($vehicleRequest)->toContain($trip->request_number)->toContain('Alex Example')
        ->toContain(e($motorPool->name))->toContain('Availability check')
        ->toContain('not cryptographic digital signatures');
    expect($travelOrder)->toContain('Travel request for')->not->toContain('Travel is authorized')->toContain('Alex Example')->toContain(e($motorPool->name));
});

it('labels demo documents as samples but not real accounts’ documents', function () {
    $demoTrip = documentTrip(documentRequester(demo: true))->load(['requester', 'passengers', 'events']);
    $realTrip = documentTrip(documentRequester())->load(['requester', 'passengers', 'events']);

    $render = fn (TripRequest $trip) => view('pdf.vehicle-request', ['trip' => $trip, 'document' => TripDocument::VehicleRequest, 'timezone' => 'Asia/Manila'])->render();

    expect($render($demoTrip))->toContain('SAMPLE — NOT AN OFFICIAL DOCUMENT');
    expect($render($realTrip))->toContain('not an official university document')
        ->not->toContain('SAMPLE — NOT AN OFFICIAL DOCUMENT');
});

it('protects document downloads with the view policy', function () {
    $requester = documentRequester();
    $trip = documentTrip($requester);
    $url = route('requests.document', ['trip' => $trip->id, 'type' => TripDocument::VehicleRequest->value]);

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(documentRequester())->get($url)->assertForbidden();
    $this->actingAs($requester)->get($url)->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload(TripDocument::VehicleRequest->filename($trip));
});

it('generates PDFs with remote resource fetching disabled', function () {
    $trip = documentTrip(documentRequester());

    foreach (TripDocument::cases() as $document) {
        $pdf = app(GenerateTripDocument::class)->handle($trip, $document);
        expect($pdf->getDomPDF()->getOptions()->getIsRemoteEnabled())->toBeFalse()
            ->and($pdf->getDomPDF()->getOptions()->getTempDir())->toBe(storage_path('app/private/dompdf'))
            ->and(strlen($pdf->output()))->toBeGreaterThan(1000);
    }
});

it('rejects missing or replaced attachment contents on download', function () {
    Storage::fake(Attachment::DISK);
    $requester = documentRequester();
    $trip = documentTrip($requester);
    $attachment = app(StoreAttachment::class)->handle($requester, $trip->id, documentPdf(), AttachmentCategory::Supporting);
    $disk = Storage::disk(Attachment::DISK);
    $url = $attachment->downloadUrl();

    $disk->put($attachment->path, str_repeat('x', $attachment->size));
    $this->actingAs($requester)->get($url)->assertNotFound();
    $disk->delete($attachment->path);
    $this->get($url)->assertNotFound();
});

it('rechecks terminal state when deleting a previously loaded attachment', function () {
    Storage::fake(Attachment::DISK);
    $requester = documentRequester();
    $trip = documentTrip($requester);
    $attachment = app(StoreAttachment::class)->handle($requester, $trip->id, documentPdf(), AttachmentCategory::Supporting);
    $attachment->load('tripRequest');
    $trip->state = RequestState::Cancelled;
    $trip->save();

    expect(fn () => app(DeleteAttachment::class)->handle($requester, $attachment))->toThrow(AuthorizationException::class);
    expect($attachment->fresh())->not->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($attachment->path);
});

it('preserves the file when an enclosing deletion transaction rolls back', function () {
    Storage::fake(Attachment::DISK);
    $requester = documentRequester();
    $trip = documentTrip($requester);
    $attachment = app(StoreAttachment::class)->handle($requester, $trip->id, documentPdf(), AttachmentCategory::Supporting);

    DB::beginTransaction();
    app(DeleteAttachment::class)->handle($requester, $attachment);
    DB::rollBack();

    expect($attachment->fresh())->not->toBeNull();
    Storage::disk(Attachment::DISK)->assertExists($attachment->path);
});

it('cleans up uploaded files if saving the attachment fails', function () {
    Storage::fake(Attachment::DISK);
    $requester = documentRequester();
    $trip = documentTrip($requester);
    Attachment::creating(fn () => throw new RuntimeException('Simulated insert failure'));
    try {
        expect(fn () => app(StoreAttachment::class)->handle($requester, $trip->id, documentPdf(), AttachmentCategory::Supporting))
            ->toThrow(RuntimeException::class, 'Simulated insert failure');
        expect($trip->attachments()->count())->toBe(0)
            ->and(Storage::disk(Attachment::DISK)->allFiles())->toBe([]);
    } finally {
        Attachment::flushEventListeners();
    }
});
