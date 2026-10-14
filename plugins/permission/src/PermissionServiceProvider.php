<?php

namespace Lathe\Permission;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class PermissionServiceProvider extends ServiceProvider
{
    /**
     * Flush Spatie's permission cache when a role or permission is saved or deleted, so a
     * change made in the panel applies on the next request.
     */
    public function boot(): void
    {
        if (! class_exists(PermissionRegistrar::class)) {
            return;
        }

        $registrar = $this->app->make(PermissionRegistrar::class);
        // The registrar is resolved on each event, so a rebound instance gets the call. The
        // listener returns nothing: forget() answers false on a cold cache, and a false from
        // a model event listener stops the listeners after it, Spatie's own among them.
        $flush = function (): void {
            $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        };

        /** @var class-string<Model> $class */
        foreach ([$registrar->getRoleClass(), $registrar->getPermissionClass()] as $class) {
            $class::saved($flush);
            $class::deleted($flush);
        }
    }
}
