<?php

namespace App\Actions;

use App\Models\Attachment;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeleteAttachment
{
    public function handle(User $actor, Attachment $attachment): void
    {
        DB::transaction(function () use ($actor, $attachment): void {
            $trip = TripRequest::query()->lockForUpdate()->findOrFail($attachment->trip_request_id);
            $attachment = Attachment::query()->lockForUpdate()->findOrFail($attachment->id);
            $attachment->setRelation('tripRequest', $trip);
            Gate::forUser($actor)->authorize('delete', $attachment);
            $attachment->delete();

            DB::afterCommit(function () use ($attachment): void {
                try {
                    Storage::disk($attachment->disk)->delete($attachment->path);
                } catch (Throwable $exception) {
                    // The row is gone, so the orphaned file can no longer be authorized; log cleanup failure.
                    report($exception);
                }
            });
        });
    }
}
