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

    private static function _userHasPermission($user, $perm)
    {
        static $_PERM_CACHE = [];
        $group = _G('GROUP');
        if (!isset($_PERM_CACHE[$perm])) {
            $permission = a('user/permission', ['group' => $group, 'name' => $perm]);
            foreach ($permission->users as $u) {
                $_PERM_CACHE[$perm][$u->id] = true;
            }
        }

        return (bool) $_PERM_CACHE[$perm][$user->id];
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

        if (self::_userHasPermission($user, 'inventory_admin')) {
            return true;
        }

        return false;
    }
}
