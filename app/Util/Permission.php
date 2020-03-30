<?php

namespace App\Util;

use App\Exception;
use Illuminate\Support\Facades\Auth;

class Permission
{
    /**
     * Permission checking function
     * ----------------------------
     * Compares the currenlty logged in user's role to the one specified
     * A "true" retun value means that the user meets the required role or surpasses it.
     * If user isn't logged in, and $compareAgainst is missing, returns false
     * If $compareAgainst isn't missing, compare it to $role
     *
     * @param  string  $role
     * @param  string|null  $compareAgainst
     *
     * @return bool
     */
    public static function Sufficient($role, $compareAgainst = null)
    {
        if (!\is_string($role)) {
            return false;
        }

        if (empty($compareAgainst)) {
            if (Auth::guest()) {
                return false;
            }
            /** @var $user \App\User */
            $user = Auth::user();
            $checkRole = $user->role;
        } else {
            $checkRole = $compareAgainst;
        }

        if (!isset(self::ROLES[$role])) {
            throw new Exception('Invalid role: '.$role);
        }
        $targetRole = $role;

        return self::ROLES[$checkRole] >= self::ROLES[$targetRole];
    }

    public const ROLES = [
        'ban' => 0,
        'guest' => 1,
        'user' => 2,
        'upload' => 3,
        'developer' => 255,
    ];

    /**
     * Save as above, except the return value is inverted
     * Added for better code readability
     *
     * @param  string  $role
     * @param  string|null  $compareAgainst
     *
     * @return bool
     */
    public static function Insufficient($role, $compareAgainst = null)
    {
        return !self::Sufficient($role, $compareAgainst);
    }

    public static function LocalizedRoleName($role)
    {
        return __("permission.role-$role");
    }
}
