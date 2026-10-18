<?php

namespace App\Models;

use App\Enums\RequestState;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripRequest extends Model
{
    protected $fillable = ['request_number', 'office', 'purpose', 'destination', 'departure_at', 'return_at'];

    protected function casts(): array
    {
        return [
            'state' => RequestState::class,
            'departure_at' => 'immutable_datetime',
            'return_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @param Builder<TripRequest> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role === Role::User) {
            $query->where('requester_id', $user->id);
        }

        if ($user->is_demo) {
            $query->whereHas('requester', fn (Builder $users) => $users->where('is_demo', true));
        }
    }
}
