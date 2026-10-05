<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\Auth;

class TenantContext
{
    private static ?Business $currentBusiness = null;

    public static function set(?Business $business): void
    {
        self::$currentBusiness = $business;
    }

    public static function get(): ?Business
    {
        $user = Auth::user();
        if ($user && $user->business_id) {
            if (self::$currentBusiness && self::$currentBusiness->id === $user->business_id) {
                return self::$currentBusiness;
            }

            self::$currentBusiness = $user->business;

            return self::$currentBusiness;
        }

        return self::$currentBusiness;
    }

    public static function id(): ?int
    {
        return self::get()?->id;
    }

    public static function clear(): void
    {
        self::$currentBusiness = null;
    }
}
