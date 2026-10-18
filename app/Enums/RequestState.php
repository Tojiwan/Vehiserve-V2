<?php

namespace App\Enums;

enum RequestState: string
{
    case Submitted = 'submitted';
    case AwaitingDean = 'awaiting_dean';
    case AwaitingVp = 'awaiting_vp';
    case AwaitingPresident = 'awaiting_president';
    case AwaitingMotorPool = 'awaiting_motor_pool';
    case ReadyToSchedule = 'ready_to_schedule';
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function reviewer(): ?Role
    {
        return match ($this) {
            self::Submitted, self::AwaitingMotorPool => Role::MotorPool,
            self::AwaitingDean => Role::Dean,
            self::AwaitingVp => Role::VicePresident,
            self::AwaitingPresident => Role::President,
            default => null,
        };
    }
}
