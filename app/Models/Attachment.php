<?php

namespace App\Models;

use App\Enums\AttachmentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property AttachmentCategory $category
 */
class Attachment extends Model
{
    public const DISK = 'attachments';

    protected $fillable = ['disk', 'path', 'original_name', 'mime_type', 'size', 'category'];

    protected function casts(): array
    {
        return [
            'category' => AttachmentCategory::class,
            'size' => 'integer',
        ];
    }

    /** @return BelongsTo<TripRequest, $this> */
    public function tripRequest(): BelongsTo
    {
        return $this->belongsTo(TripRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /** Short-lived signed link; the download route still re-checks authorization. */
    public function downloadUrl(): string
    {
        return URL::temporarySignedRoute('attachments.download', now()->addMinutes(15), [
            'trip' => $this->trip_request_id,
            'attachment' => $this->id,
        ]);
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }
}
