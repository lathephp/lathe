<?php

namespace Lathe\Permission;

use Lathe\Plugins\Plugin;

/**
 * Roles and permissions from spatie/laravel-permission, managed in the panel.
 */
class PermissionPlugin extends Plugin
{
    public function name(): string
    {
        return 'Access Control';
    }

    public static function uriKey(): string
    {
        return 'permission';
    }
}
