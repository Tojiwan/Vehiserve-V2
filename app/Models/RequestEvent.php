<?php

namespace App\Models;

use App\Enums\RequestState;
use Illuminate\Database\Eloquent\Model;

/**
 * @property RequestState|null $from_state
 * @property RequestState $to_state
 */
class RequestEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'from_state' => RequestState::class,
            'to_state' => RequestState::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Request history is append-only.'));
        static::deleting(fn () => throw new \LogicException('Request history is append-only.'));
    }
}
