<?php

namespace Gini\Module;

class AdminChemicalLimits
{
    public static function setup()
    {
    }
    public static function diagnose()
    {
    }

    public static function commonACL($e, $user, $action, $project, $when, $where)
    {
        if (!$user->id) {
            return false;
        }

        $group = _G('GROUP');
        if ($user->isAdminOf($group)) {
            return true;
        }

        if (self::_userHasPermission($user, 'admin')) {
            return true;
        }

        return false;
    }
}
