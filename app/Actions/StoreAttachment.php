<?php

namespace App\Actions;

use App\Enums\AttachmentCategory;
use App\Models\Attachment;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreAttachment
{
    public const MAX_PER_REQUEST = 3;

    public const MAX_KILOBYTES = 5120;

    public function handle(User $actor, int $tripId, UploadedFile $file, AttachmentCategory $category): Attachment
    {
        Validator::make(['file' => $file, 'category' => $category], [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_KILOBYTES],
            'category' => ['required', Rule::enum(AttachmentCategory::class)],
        ])->validate();

        $path = null;
        try {
            return DB::transaction(function () use ($actor, $tripId, $file, $category, &$path): Attachment {
                // Serialize uploads and workflow decisions on the same request row.
                $trip = TripRequest::query()->lockForUpdate()->findOrFail($tripId);
                Gate::forUser($actor)->authorize('uploadAttachment', $trip);
                if ($trip->attachments()->count() >= self::MAX_PER_REQUEST) {
                    throw ValidationException::withMessages(['attachment' => 'A request can hold at most '.self::MAX_PER_REQUEST.' attachments.']);
                }
                $path = 'requests/'.$trip->request_number.'/'.Str::ulid().'.'.$file->guessExtension();
                Storage::disk(Attachment::DISK)->putFileAs(dirname($path), $file, basename($path));
                $attachment = $trip->attachments()->make([
                    'disk' => Attachment::DISK,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => (string) $file->getMimeType(),
                    'size' => $file->getSize(),
                    'category' => $category,
                ]);
                $attachment->uploader()->associate($actor);
                $attachment->save();

                return $attachment;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                try {
                    Storage::disk(Attachment::DISK)->delete($path);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            throw $exception;
        }
    }
}
