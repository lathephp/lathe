<?php

namespace Lathe\Permission\Tests\Fixtures;

use Lathe\Permission\PermissionPlugin;

/**
 * The plugin in an app that has not installed spatie/laravel-permission.
 */
class PluginWithoutSpatie extends PermissionPlugin
{
    protected string $registrar = 'Spatie\Permission\MissingRegistrar';
}
