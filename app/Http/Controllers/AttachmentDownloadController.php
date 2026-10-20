<?php

namespace App\Http\Controllers;

use App\Actions\StoreAttachment;
use App\Models\Attachment;
use App\Models\TripRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentDownloadController extends Controller
{
    /** The route is signed and short-lived, but authorization is re-checked on every download. */
    public function __invoke(TripRequest $trip, Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->trip_request_id === $trip->id, 404);
        Gate::authorize('download', $attachment);

        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);
        abort_unless(
            $disk->size($attachment->path) === $attachment->size
            && $attachment->size <= StoreAttachment::MAX_KILOBYTES * 1024
            && in_array((new \finfo(FILEINFO_MIME_TYPE))->buffer($disk->get($attachment->path)), ['application/pdf', 'image/jpeg', 'image/png'], true),
            404,
        );

        return $disk->response($attachment->path, $attachment->original_name, [], 'attachment');
    }
}
