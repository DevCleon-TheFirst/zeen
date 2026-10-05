<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Agent = 'agent';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Administrator',
            self::Manager => 'Manager',
            self::Agent => 'Agent',
            self::Support => 'Support Staff',
        };
    }

    public function isElevated(): bool
    {
        return in_array($this, [self::Owner, self::Admin]);
    }
}
