<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AttachmentPolicy
{
    public function download(User $user, Attachment $attachment): bool
    {
        return $user->hasVerifiedEmail() && Gate::forUser($user)->allows('view', $attachment->tripRequest);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $user->hasVerifiedEmail()
            && $attachment->uploader_id === $user->id
            && Gate::forUser($user)->allows('uploadAttachment', $attachment->tripRequest);
    }
}
