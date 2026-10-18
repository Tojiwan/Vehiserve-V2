<?php

namespace App\Enums;

enum Role: string
{
    case User = 'user';
    case Admin = 'admin';
    case MotorPool = 'motor_pool';
    case Dean = 'dean';
    case VicePresident = 'vice_president';
    case President = 'president';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Admin => 'Admin',
            self::MotorPool => 'Motor Pool Staff',
            self::Dean => 'Dean',
            self::VicePresident => 'Vice President',
            self::President => 'SUC President',
            self::SuperAdmin => 'Super Admin',
        };
    }
}
