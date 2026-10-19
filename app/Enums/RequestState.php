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

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Awaiting availability check',
            self::AwaitingDean => 'Awaiting Dean',
            self::AwaitingVp => 'Awaiting Vice President',
            self::AwaitingPresident => 'Awaiting SUC President',
            self::AwaitingMotorPool => 'Awaiting final Motor Pool approval',
            self::ReadyToSchedule => 'Ready to schedule',
            self::Scheduled => 'Scheduled',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function nextApproval(): ?self
    {
        return match ($this) {
            self::Submitted => self::AwaitingDean,
            self::AwaitingDean => self::AwaitingVp,
            self::AwaitingVp => self::AwaitingPresident,
            self::AwaitingPresident => self::AwaitingMotorPool,
            self::AwaitingMotorPool => self::ReadyToSchedule,
            default => null,
        };
    }

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
